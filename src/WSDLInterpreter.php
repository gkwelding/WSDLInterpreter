<?php
require 'WSDLException.php';

/**
 * The main class for handling WSDL interpretation
 * The WSDLInterpreter is utilized for the parsing of a WSDL document for rapid
 * and flexible use within the context of PHP 5 scripts.
 * Example Usage:
 * <code>
 * require_once 'WSDLInterpreter.php';
 * $myWSDLlocation = 'http://www.example.com/ExampleService?wsdl';
 * $wsdlInterpreter = new WSDLInterpreter($myWSDLlocation);
 * $wsdlInterpreter->savePHP('/example/output/directory/');
 * </code>
 *
 * @category WebServices
 * @package  WSDLInterpreter
 */
class WSDLInterpreter {

    const BASE_NAMESPACE = 'WSDLI';
    const STUBS_NAMESPACE = 'WSDLI\\Stubs';
    /**
     * The WSDL document's URI
     *
     * @var string
     * @access protected
     */
    protected $_wsdl = NULL;
    /**
     * A SoapClient for loading the WSDL
     *
     * @var SoapClient
     * @access protected
     */
    protected $_client = NULL;
    /**
     * DOM document representation of the wsdl and its translation
     *
     * @var DOMDocument
     * @access protected
     */
    protected $_dom = NULL;
    /**
     * Array of classes and members representing the WSDL message types
     *
     * @var array
     * @access protected
     */
    protected $_classmap = array();
    /**
     * Array of sources for WSDL message classes
     *
     * @var array
     * @access protected
     */
    protected $_classPHPSources = array();
    /**
     * Array of sources for WSDL services
     *
     * @var array
     * @access protected
     */
    protected $_servicePHPSources = array();

    /**
     * Parses the target wsdl and loads the interpretation into object members
     *
     * @param string $wsdl the URI of the wsdl to interpret
     * @param string $xslt
     *
     * @throws WSDLInterpreterException
     * @todo Create plug in model to handle extendability of WSDL files
     */
    public function __construct( $wsdl, $xslt = NULL ) {
        $xslt = empty( $xslt ) ? dirname( __FILE__ ) . "/wsdl2php.xsl" : $xslt;
        try {
            $this->_wsdl = $wsdl;
            $this->_client = new SoapClient( $this->_wsdl );
            $this->_dom = new DOMDocument();
            $this->_dom->load( $this->_wsdl, LIBXML_DTDLOAD | LIBXML_DTDATTR | LIBXML_NOENT | LIBXML_XINCLUDE );
            $xpath = new DOMXPath( $this->_dom );
            /**
             * wsdl:import
             */
            $query = "//*[local-name()='import' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']";
            /** @var DOMElement[] $entries */
            $entries = $xpath->query( $query );
            foreach ( $entries as $entry ) {
                $parent = $entry->parentNode;
                $wsdl = new DOMDocument();
                $wsdl->load( $entry->getAttribute( "location" ), LIBXML_DTDLOAD | LIBXML_DTDATTR | LIBXML_NOENT | LIBXML_XINCLUDE );
                foreach ( $wsdl->documentElement->childNodes as $node ) {
                    $newNode = $this->_dom->importNode( $node, TRUE );
                    $parent->insertBefore( $newNode, $entry );
                }
                $parent->removeChild( $entry );
            }
            /**
             * xsd:import
             */
            $query = "//*[local-name()='import' and namespace-uri()='http://www.w3.org/2001/XMLSchema']";
            $entries = $xpath->query( $query );
            foreach ( $entries as $entry ) {
                $parent = $entry->parentNode;
                $xsd = new DOMDocument();
                $result = $xsd->load( dirname( $this->_wsdl ) . "/" . $entry->getAttribute( "schemaLocation" ), LIBXML_DTDLOAD | LIBXML_DTDATTR | LIBXML_NOENT | LIBXML_XINCLUDE );
                if ( $result ) {
                    foreach ( $xsd->documentElement->childNodes as $node ) {
                        $newNode = $this->_dom->importNode( $node, TRUE );
                        $parent->insertBefore( $newNode, $entry );
                    }
                    $parent->removeChild( $entry );
                }
            }
            $this->_dom->formatOutput = TRUE;
        } catch ( Exception $e ) {
            throw new WSDLInterpreterException( "Error loading WSDL document (" . $e->getMessage() . ")" );
        }
        try {
            $xsl = new XSLTProcessor();
            $xslDom = new DOMDocument();
            $xslDom->load( $xslt );
            $xsl->registerPHPFunctions();
            $xsl->importStyleSheet( $xslDom );
            $this->_dom = $xsl->transformToDoc( $this->_dom );
            $this->_dom->formatOutput = TRUE;
        } catch ( Exception $e ) {
            throw new WSDLInterpreterException( "Error interpreting WSDL document (" . $e->getMessage() . ")" );
        }
        $this->_loadClasses();
        $this->_loadServices();
    }

    /**
     * Validates a name against standard PHP naming conventions
     *
     * @param string $name the name to validate
     *
     * @return string the validated version of the submitted name
     * @access protected
     */
    protected function _validateNamingConvention( $name ) {
        return preg_replace( '#[^a-zA-Z0-9_\x7f-\xff]*#', '', preg_replace( '#^[^a-zA-Z_\x7f-\xff]*#', '', $name ) );
    }

    /**
     * Validates a class name against PHP naming conventions and already defined
     * classes, and optionally stores the class as a member of the interpreted classmap.
     *
     * @param string  $className     the name of the class to test
     * @param boolean $addToClassMap whether to add this class name to the classmap
     *
     * @return string the validated version of the submitted class name
     * @throws Exception
     * @access protected
     * @todo   Add reserved keyword checks
     */
    protected function _validateClassName( $className, $addToClassMap = TRUE ) {
        $validClassName = $this->_validateNamingConvention( $className );
        if ( class_exists( $validClassName ) ) {
            throw new Exception( "Class " . $validClassName . " already defined." . " Cannot redefine class with class loaded." );
        }
        if ( $addToClassMap ) {
            $this->_classmap[$className] = self::STUBS_NAMESPACE . '\\' . $validClassName;
        }
        return $validClassName;
    }

    /**
     * Validates a wsdl type against known PHP primitive types, or otherwise
     * validates the namespace of the type to PHP naming conventions
     *
     * @param string $type the type to test
     *
     * @return string the validated version of the submitted type
     * @access protected
     * @todo   Extend type handling to gracefully manage extendability of wsdl definitions, add reserved keyword checking
     */
    protected function _validateType( $type ) {
        $array = FALSE;
        if ( substr( $type, -2 ) == "[]" ) {
            $array = TRUE;
            $type = substr( $type, 0, -2 );
        }
        switch ( strtolower( $type ) ) {
            case "int":
            case "integer":
            case "long":
            case "byte":
            case "short":
            case "negativeInteger":
            case "nonNegativeInteger":
            case "nonPositiveInteger":
            case "positiveInteger":
            case "unsignedByte":
            case "unsignedInt":
            case "unsignedLong":
            case "unsignedShort":
                $validType = "integer";
                break;
            case "float":
            case "long":
            case "double":
            case "decimal":
                $validType = "double";
                break;
            case "string":
            case "token":
            case "normalizedString":
            case "hexBinary":
                $validType = "string";
                break;
            default:
                $validType = $this->_validateNamingConvention( $type );
                break;
        }
        if ( $array ) {
            $validType .= "[]";
        }
        return $validType;
    }

    /**
     * Loads classes from the translated wsdl document's message types
     *
     * @access protected
     */
    protected function _loadClasses() {
        $sources = array();
        $classes = $this->_dom->getElementsByTagName( "class" );
        foreach ( $classes as $class ) {
            $class->setAttribute( "validatedName", $this->_validateClassName( $class->getAttribute( "name" ) ) );
            $extends = $class->getElementsByTagName( "extends" );
            if ( $extends->length > 0 ) {
                $extends->item( 0 )->nodeValue = $this->_validateClassName( $extends->item( 0 )->nodeValue );
                $classExtension = $extends->item( 0 )->nodeValue;
            } else {
                $classExtension = FALSE;
            }
            $properties = $class->getElementsByTagName( "entry" );
            foreach ( $properties as $property ) {
                $property->setAttribute( "validatedName", $this->_validateNamingConvention( $property->getAttribute( "name" ) ) );
                $property->setAttribute( "type", $this->_validateType( $property->getAttribute( "type" ) ) );
            }
            $sources[$class->getAttribute( "validatedName" )] = array(
                "extends" => $classExtension,
                "source" => $this->_generateClassPHP( $class )
            );
        }
        while ( sizeof( $sources ) > 0 ) {
            $classesLoaded = 0;
            foreach ( $sources as $className => $classInfo ) {
                if ( !$classInfo["extends"] || ( isset( $this->_classPHPSources[$classInfo["extends"]] ) ) ) {
                    $this->_classPHPSources[$className] = $classInfo["source"];
                    unset( $sources[$className] );
                    $classesLoaded++;
                }
            }
            if ( ( $classesLoaded == 0 ) && ( sizeof( $sources ) > 0 ) ) {
                throw new WSDLInterpreterException( "Error loading PHP classes: " . join( ", ", array_keys( $sources ) ) );
            }
        }
    }

    /**
     * Generates the PHP code for a WSDL message type class representation
     * This gets a little bit fancy as the magic methods __get and __set in
     * the generated classes are used for properties that are not named
     * according to PHP naming conventions (e.g., "MY-VARIABLE").  These
     * variables are set directly by SoapClient within the target class,
     * and could normally be retrieved by $myClass->{"MY-VARIABLE"}.  For
     * convenience, however, this will be available as $myClass->MYVARIABLE.
     *
     * @param DOMElement $class the interpreted WSDL message type node
     *
     * @return string the php source code for the message type class
     * @access protected
     * @todo   Include any applicable annotation from WSDL
     */
    protected function _generateClassPHP( $class ) {
        $return = 'namespace ' . self::STUBS_NAMESPACE . ';' . "\n\n";
        $return .= '/**' . "\n";
        $return .= ' * ' . $class->getAttribute( "validatedName" ) . "\n";
        $return .= ' */' . "\n";
        $return .= "class " . $class->getAttribute( "validatedName" );
        $extends = $class->getElementsByTagName( "extends" );
        if ( $extends->length > 0 ) {
            $return .= " extends " . $extends->item( 0 )->nodeValue;
        }
        $return .= " {\n";
        /** @var DOMElement[] $properties */
        $properties = $class->getElementsByTagName( "entry" );
        foreach ( $properties as $property ) {
            $return .= "\t/**\n" . "\t * @access public\n" . "\t * @var " . $property->getAttribute( "type" ) . "\n" . "\t */\n" . "\t" . 'public $' . $property->getAttribute( "validatedName" ) . ";\n";
        }
        $extraParams = FALSE;
        $paramMapReturn = "\t" . 'private $_parameterMap = array (' . "\n";
        $properties = $class->getElementsByTagName( "entry" );
        foreach ( $properties as $property ) {
            if ( $property->getAttribute( "name" ) != $property->getAttribute( "validatedName" ) ) {
                $extraParams = TRUE;
                $paramMapReturn .= "\t\t" . '"' . $property->getAttribute( "name" ) . '" => "' . $property->getAttribute( "validatedName" ) . '",' . "\n";
            }
        }
        $paramMapReturn .= "\t" . ');' . "\n";
        $paramMapReturn .= "\t" . '/**' . "\n";
        $paramMapReturn .= "\t" . ' * Provided for setting non-php-standard named variables' . "\n";
        $paramMapReturn .= "\t" . ' * @param $var Variable name to set' . "\n";
        $paramMapReturn .= "\t" . ' * @param $value Value to set' . "\n";
        $paramMapReturn .= "\t" . ' */' . "\n";
        $paramMapReturn .= "\t" . 'public function __set($var, $value) ' . '{ $this->{$this->_parameterMap[$var]} = $value; }' . "\n";
        $paramMapReturn .= "\t" . '/**' . "\n";
        $paramMapReturn .= "\t" . ' * Provided for getting non-php-standard named variables' . "\n";
        $paramMapReturn .= "\t" . ' * @param $var Variable name to get' . "\n";
        $paramMapReturn .= "\t" . ' * @return mixed Variable value' . "\n";
        $paramMapReturn .= "\t" . ' */' . "\n";
        $paramMapReturn .= "\t" . 'public function __get($var) ' . '{ return $this->{$this->_parameterMap[$var]}; }' . "\n";
        if ( $extraParams ) {
            $return .= $paramMapReturn;
        }
        $return .= "}";
        return $return;
    }

    /**
     * Loads services from the translated wsdl document
     *
     * @access protected
     */
    protected function _loadServices() {
        /** @var DOMElement[] $services */
        $services = $this->_dom->getElementsByTagName( "service" );
        foreach ( $services as $service ) {
            $service->setAttribute( "validatedName", $this->_validateClassName( $service->getAttribute( "name" ), FALSE ) );
            /** @var DOMElement[] $functions */
            $functions = $service->getElementsByTagName( "function" );
            foreach ( $functions as $function ) {
                $function->setAttribute( "validatedName", $this->_validateNamingConvention( $function->getAttribute( "name" ) ) );
                $parameters = $function->getElementsByTagName( "parameters" );
                if ( $parameters->length > 0 ) {
                    /** @var DOMElement[] $parameterList */
                    $parameterList = $parameters->item( 0 )->getElementsByTagName( "entry" );
                    foreach ( $parameterList as $variable ) {
                        $variable->setAttribute( "validatedName", $this->_validateNamingConvention( $variable->getAttribute( "name" ) ) );
                        $variable->setAttribute( "type", $this->_validateType( $variable->getAttribute( "type" ) ) );
                    }
                }
            }
            $this->_servicePHPSources[$service->getAttribute( "validatedName" )] = $this->_generateServicePHP( $service );
        }
    }

    /**
     * Generates the PHP code for a WSDL service class representation
     * This method, in combination with generateServiceFunctionPHP, create a PHP class
     * representation capable of handling overloaded methods with strict parameter
     * type checking.
     *
     * @param DOMElement $service the interpreted WSDL service node
     *
     * @return string the php source code for the service class
     * @access protected
     * @todo   Include any applicable annotation from WSDL
     */
    protected function _generateServicePHP( $service ) {
        $return = 'namespace ' . self::BASE_NAMESPACE . ';' . "\n\n";
        $return .= '/**' . "\n";
        $return .= ' * ' . $service->getAttribute( "validatedName" ) . "\n";
        $return .= ' * @author WSDLInterpreter' . "\n";
        $return .= ' */' . "\n";
        $return .= "class " . $service->getAttribute( "validatedName" ) . " extends \\SoapClient {\n";
        if ( sizeof( $this->_classmap ) > 0 ) {
            $return .= "\t" . '/**' . "\n";
            $return .= "\t" . ' * Default class map for wsdl=>php' . "\n";
            $return .= "\t" . ' * @access private' . "\n";
            $return .= "\t" . ' * @var array' . "\n";
            $return .= "\t" . ' */' . "\n";
            $return .= "\t" . 'private static $classmap = array(' . "\n";
            foreach ( $this->_classmap as $className => $validClassName ) {
                $return .= "\t\t" . '"' . $className . '" => "' . str_replace( '\\', '\\\\', $validClassName ) . '",' . "\n";
            }
            $return .= "\t);\n\n";
        }
        $return .= "\t" . '/**' . "\n";
        $return .= "\t" . ' * Constructor using wsdl location and options array' . "\n";
        $return .= "\t" . ' * @param string $wsdl WSDL location for this service' . "\n";
        $return .= "\t" . ' * @param array $options Options for the SoapClient' . "\n";
        $return .= "\t" . ' */' . "\n";
        $return .= "\t" . 'public function __construct($wsdl="' . $this->_wsdl . '", $options=array()) {' . "\n";
        $return .= "\t\t" . 'foreach(self::$classmap as $wsdlClassName => $phpClassName) {' . "\n";
        $return .= "\t\t" . '    if(!isset($options[\'classmap\'][$wsdlClassName])) {' . "\n";
        $return .= "\t\t" . '        $options[\'classmap\'][$wsdlClassName] = $phpClassName;' . "\n";
        $return .= "\t\t" . '    }' . "\n";
        $return .= "\t\t" . '}' . "\n";
        $return .= "\t\t" . 'parent::__construct($wsdl, $options);' . "\n";
        $return .= "\t}\n\n";
        $return .= "\t" . '/**' . "\n";
        $return .= "\t" . ' * Checks if an argument list matches against a valid ' . 'argument type list' . "\n";
        $return .= "\t" . ' * @param array $arguments The argument list to check' . "\n";
        $return .= "\t" . ' * @param array $validParameters A list of valid argument ' . 'types' . "\n";
        $return .= "\t" . ' * @return boolean true if arguments match against ' . 'validParameters' . "\n";
        $return .= "\t" . ' * @throws Exception invalid function signature message' . "\n";
        $return .= "\t" . ' */' . "\n";
        $return .= "\t" . 'public function _checkArguments($arguments, $validParameters) {' . "\n";
        $return .= "\t\t" . '$variables = "";' . "\n";
        $return .= "\t\t" . 'foreach ($arguments as $arg) {' . "\n";
        $return .= "\t\t" . '    $type = gettype($arg);' . "\n";
        $return .= "\t\t" . '    if ($type == "object") {' . "\n";
        $return .= "\t\t" . '        $type = get_class($arg);' . "\n";
        $return .= "\t\t" . '    }' . "\n";
        $return .= "\t\t" . '    $variables .= "(".$type.")";' . "\n";
        $return .= "\t\t" . '}' . "\n";
        $return .= "\t\t" . 'if (!in_array($variables, $validParameters)) {' . "\n";
        $return .= "\t\t" . '    throw new Exception("Invalid parameter types: ' . '".str_replace(")(", ", ", $variables));' . "\n";
        $return .= "\t\t" . '}' . "\n";
        $return .= "\t\t" . 'return true;' . "\n";
        $return .= "\t}\n\n";
        $functionMap = array();
        /** @var DOMElement[] $functions */
        $functions = $service->getElementsByTagName( "function" );
        foreach ( $functions as $function ) {
            if ( !isset( $functionMap[$function->getAttribute( "validatedName" )] ) ) {
                $functionMap[$function->getAttribute( "validatedName" )] = array();
            }
            $functionMap[$function->getAttribute( "validatedName" )][] = $function;
        }
        foreach ( $functionMap as $functionName => $functionNodeList ) {
            $return .= $this->_generateServiceFunctionPHP( $functionName, $functionNodeList ) . "\n\n";
        }
        $return .= "}";
        return $return;
    }

    /**
     * Generates the PHP code for a WSDL service operation function representation
     * The function code that is generated examines the arguments that are passed and
     * performs strict type checking against valid argument combinations for the given
     * function name, to allow for overloading.
     *
     * @param string       $functionName     the php function name
     * @param DOMElement[] $functionNodeList array of DOMElement interpreted WSDL function nodes
     *
     * @return string the php source code for the function
     * @access protected
     * @todo   Include any applicable annotation from WSDL
     */
    protected function _generateServiceFunctionPHP( $functionName, $functionNodeList ) {
        $return = "";
        $return .= "\t" . '/**' . "\n";
        $return .= "\t" . ' * Service Call: ' . $functionName . "\n";
        $parameterComments = array();
        $variableTypeOptions = array();
        $returnOptions = array();
        foreach ( $functionNodeList as $functionNode ) {
            /** @var DOMNodeList $parameters */
            $parameters = $functionNode->getElementsByTagName( "parameters" );
            if ( $parameters->length > 0 ) {
                /** @var DOMElement $parameter */
                $parameter = $parameters->item( 0 );
                $parameters = $parameter->getElementsByTagName( "entry" );
                $parameterTypes = "";
                $parameterList = array();
                foreach ( $parameters as $parameter ) {
                    if ( substr( $parameter->getAttribute( "type" ), 0, -2 ) == "[]" ) {
                        $parameterTypes .= "(array)";
                    } else {
                        $parameterTypes .= "(" . $parameter->getAttribute( "type" ) . ")";
                    }
                    $parameterList[] = "(" . $parameter->getAttribute( "type" ) . ") " . $parameter->getAttribute( "validatedName" );
                }
                if ( sizeof( $parameterList ) > 0 ) {
                    $variableTypeOptions[] = $parameterTypes;
                    $parameterComments[] = "\t" . ' * ' . join( ", ", $parameterList );
                }
            }
            $returns = $functionNode->getElementsByTagName( "returns" );
            if ( $returns->length > 0 ) {
                $returns = $returns->item( 0 )->getElementsByTagName( "entry" );
                if ( $returns->length > 0 ) {
                    $returnOptions[] = $returns->item( 0 )->getAttribute( "type" );
                }
            }
        }
        $return .= "\t" . ' * Parameter options:' . "\n";
        $return .= join( "\n", $parameterComments ) . "\n";
        $return .= "\t" . ' * @param mixed,... See function description for parameter options' . "\n";
        $return .= "\t" . ' * @return ' . join( "|", array_unique( $returnOptions ) ) . "\n";
        $return .= "\t" . ' * @throws Exception invalid function signature message' . "\n";
        $return .= "\t" . ' */' . "\n";
        $return .= "\t" . 'public function ' . $functionName . '($mixed = null) {' . "\n";
        $return .= "\t\t" . '$validParameters = array(' . "\n";
        foreach ( $variableTypeOptions as $variableTypeOption ) {
            $return .= "\t\t\t" . '"' . $variableTypeOption . '",' . "\n";
        }
        $return .= "\t\t" . ');' . "\n";
        $return .= "\t\t" . '$args = func_get_args();' . "\n";
        $return .= "\t\t" . '$this->_checkArguments($args, $validParameters);' . "\n";
        $return .= "\t\t" . 'return $this->__soapCall("' . $functionNodeList[0]->getAttribute( "name" ) . '", $args);' . "\n";
        $return .= "\t" . '}' . "\n";
        return $return;
    }

    /**
     * Saves the PHP source code that has been loaded to a target directory.
     * Services will be saved by their validated name, and classes will be included
     * with each service file so that they can be utilized independently.
     *
     * @param string $outputDirectory the destination directory for the source code
     *
     * @return array array of source code files that were written out
     * @throws WSDLInterpreterException problem in writing out service sources
     * @access public
     * @todo   Add split file options for more efficient output
     */
    public function savePHP( $outputDirectory ) {
        if ( sizeof( $this->_servicePHPSources ) == 0 ) {
            throw new WSDLInterpreterException( "No services loaded" );
        }
        $outputDirectory = rtrim( $outputDirectory, "/" );
        $outputFiles = array();
        $baseNsDir = $outputDirectory . '/' . str_replace( '\\', '/', self::BASE_NAMESPACE ) . '/';
        $stubsNsDir = $outputDirectory . '/' . str_replace( '\\', '/', self::STUBS_NAMESPACE ) . '/';
        if ( !is_dir( $baseNsDir ) ) {
            mkdir( $baseNsDir, 0777, TRUE );
        }
        if ( !is_dir( $stubsNsDir ) ) {
            mkdir( $stubsNsDir, 0777, TRUE );
        }
        foreach ( $this->_classPHPSources as $className => $classCode ) {
            $filename = $stubsNsDir . $className . ".php";
            if ( file_put_contents( $filename, "<?php\n\n" . $classCode ) ) {
                $outputFiles[] = $filename;
            }
        }
        foreach ( $this->_servicePHPSources as $serviceName => $serviceCode ) {
            $filename = $baseNsDir . "/" . $serviceName . ".php";
            if ( file_put_contents( $filename, "<?php\n\n" . $serviceCode ) ) {
                $outputFiles[] = $filename;
            }
        }
        if ( sizeof( $outputFiles ) == 0 ) {
            throw new WSDLInterpreterException( "Error writing PHP source files." );
        }
        return $outputFiles;
    }
}


