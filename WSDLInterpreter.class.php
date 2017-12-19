<?php
/**
 * Interprets WSDL documents for the purposes of PHP 5 object creation
 * 
 * The WSDLInterpreter package is used for the interpretation of a WSDL 
 * document into PHP classes that represent the messages using inheritance
 * and typing as defined by the WSDL rather than SoapClient's limited
 * interpretation.  PHP classes are also created for each service that
 * represent the methods with any appropriate overloading and strict
 * variable type checking as defined by the WSDL.
 *
 * PHP version 5 
 * 
 * LICENSE: This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category    WebServices 
 * @package     WSDLInterpreter  
 * @author      Kevin Vaughan kevin@kevinvaughan.com
 * @copyright   2007 Kevin Vaughan
 * @license     http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * 
 */

/**
 * A lightweight wrapper of Exception to provide basic package specific 
 * unrecoverable program states.
 * 
 * @category WebServices
 * @package WSDLInterpreter
 */
class WSDLInterpreterException extends Exception { } 

/**
 * The main class for handling WSDL interpretation
 * 
 * The WSDLInterpreter is utilized for the parsing of a WSDL document for rapid
 * and flexible use within the context of PHP 5 scripts.
 * 
 * @category WebServices
 * @package WSDLInterpreter
 */
class WSDLInterpreter 
{
    /**
     * The WSDL document's URI
     * @var string
     * @access private
     */
    private $_wsdl = null;

    /**
     * A SoapClient for loading the WSDL
     * @var SoapClient
     * @access private
     */
    private $_client = null;
    
    /**
     * DOM document representation of the wsdl and its translation
     * @var DOMDocument
     * @access private
     */
    private $_dom = null;
    
    /**
     * Array of classes and members representing the WSDL message types
     * @var array
     * @access private
     */
    private $_classmap = array();
    
    /**
     * Array of sources for WSDL message classes
     * @var array
     * @access private
     */
    private $_classPHPSources = array();
    
    /**
     * Array of sources for WSDL services
     * @var array
     * @access private
     */
    private $_servicePHPSources = array();
    
    /**
     * The target PHP namespace to put in generated classes
     * 
     * When empty, no namespace is added
     * 
     * @var string
     * @access protected
     */
    protected $target_namespace = '';
    
    /**
     * Should we generate one big file or separate classfiles
     * 
     * One big file is the historical behaviour, but now we generate
     * separate files for each class.
     * 
     * @var boolean
     * @access protected
     */
    protected $one_big_file = false;
    
    /**
     * Should we add class_exists statements to generated classes?
     * 
     * Set true to have historical behaviour
     * 
     * @var boolean
     * @access protected
     */
    protected $add_class_exists = false;
    
    /**
     * Should we output logging
     * 
     * @var boolean
     * @access protected
     */
    protected $logging = false;
    
    /**
     * The soap version used to validate the wsdl
     * 
     * @var int
     * @access protected
     */
    protected $soap_version = SOAP_1_1;
    
    /**
     * Parses the target wsdl and loads the interpretation into object members
     * 
     * @param string $wsdl  the URI of the wsdl to interpret
     * @throws WSDLInterpreterException Container for all WSDL interpretation problems
     * @todo Create plug in model to handle extendability of WSDL files
     */
    public function __construct($wsdl) 
    {
        $this->_wsdl = $wsdl;
    }
    
    /**
     * Logs a message
     * 
     * @param string $message
     * 
     * @access private
     */
    protected function _debugLog($message) 
    {
        if($this->logging){
            error_log($message);
        }
    }
    
    /**
     * Parse the WSDL file
     * 
     * @param string $outputDirectory the destination directory for the intermediate files
     * @param string $wsdl  optionally give wsdl to parse, if null use the wsdl from constructor
     * @access public
     */
    public function parseWSDL($outputDirectory, $wsdl=null)
    {
        if( !is_null($wsdl) ){
            $this->_wsdl = $wsdl;
        }
        
        // initialize
        $this->_classmap=array();
        $this->_classPHPSources=array();
        $this->_servicePHPSources=array();
        
        // parse
        try {
            
            $this->_dom = new DOMDocument();
            $this->_debugLog('loading wsdl: '.$this->_wsdl);
            $this->_dom->load($this->_wsdl, LIBXML_DTDLOAD|LIBXML_DTDATTR|LIBXML_NOENT|LIBXML_XINCLUDE);
            
            $xpath = new DOMXPath($this->_dom);
            
            /**
             * wsdl:import
             */
            $query = "//*[local-name()='import' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']";
            $entries = $xpath->query($query);
            foreach ($entries as $entry) {
                $parent = $entry->parentNode;
                $wsdl = new DOMDocument();
                $this->_debugLog('loading wsdl: '.$entry->getAttribute("location"));
                $wsdl->load($entry->getAttribute("location"), LIBXML_DTDLOAD|LIBXML_DTDATTR|LIBXML_NOENT|LIBXML_XINCLUDE);
                foreach ($wsdl->documentElement->childNodes as $node) {
                    $newNode = $this->_dom->importNode($node, true);
                    $parent->insertBefore($newNode, $entry);
                }
                $parent->removeChild($entry);
            }
            
            /**
             * xsd:import
             */
            $query = "//*[local-name()='import' and namespace-uri()='http://www.w3.org/2001/XMLSchema']";
            $entries = $xpath->query($query);
            foreach ($entries as $entry) {
                $parent = $entry->parentNode;
                $xsd = new DOMDocument();
                $this->_debugLog('loading wsdl: '.dirname($this->_wsdl) . "/" . $entry->getAttribute("schemaLocation"));
                $result = @$xsd->load(dirname($this->_wsdl) . "/" . $entry->getAttribute("schemaLocation"), 
                    LIBXML_DTDLOAD|LIBXML_DTDATTR|LIBXML_NOENT|LIBXML_XINCLUDE);
                if ($result) {
                    foreach ($xsd->documentElement->childNodes as $node) {
                        $newNode = $this->_dom->importNode($node, true);
                        $parent->insertBefore($newNode, $entry);
                    }
                    $parent->removeChild($entry);
                }
            }
            
            
            $this->_dom->formatOutput = true;
            // okay, $this->_dom is now the wsdl with all import statements handled
            // lets save it and see if its valid wsdl by passing it to SoapClient
            $this->_dom->save($outputDirectory.'/sanitized.wsdl');
            
            // now open in soapclient to see if it is valid wsdl
            $this->_debugLog('starting SoapClient with wsdl: '.$outputDirectory.'/sanitized.wsdl');
            $this->_client = new SoapClient($outputDirectory.'/sanitized.wsdl', array('soap_version'   => $this->soap_version, 'cache_wsdl' => WSDL_CACHE_NONE));
        } catch (Exception $e) {
            throw new WSDLInterpreterException("Error loading WSDL document (".$e->getMessage().")");
        }
        
        try {
            $xsl = new XSLTProcessor();
            $xslDom = new DOMDocument();
            $xslDom->load(dirname(__FILE__)."/wsdl2php.xsl");
            $xsl->registerPHPFunctions();
            $xsl->importStyleSheet($xslDom);
            $this->_dom = $xsl->transformToDoc($this->_dom);
            $this->_dom->formatOutput = true;
            // save the transformed wsdl so we can inspect it if we want
            $this->_dom->save($outputDirectory.'/transformed.xml');
        } catch (Exception $e) {
            throw new WSDLInterpreterException("Error interpreting WSDL document (".$e->getMessage().")");
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
     * 
     * @access private
     */
    private function _validateNamingConvention($name) 
    {
        return preg_replace('#[^a-zA-Z0-9_\x7f-\xff]*#', '',
            preg_replace('#^[^a-zA-Z_\x7f-\xff]*#', '', $name));
    }
    
    /**
     * Validates a class name against PHP naming conventions and already defined
     * classes, and optionally stores the class as a member of the interpreted classmap.
     * 
     * @param string $className the name of the class to test
     * @param boolean $addToClassMap whether to add this class name to the classmap
     * 
     * @return string the validated version of the submitted class name
     * 
     * @access private
     * @todo Add reserved keyword checks
     */
    private function _validateClassName($className, $addToClassMap = true) 
    {
        $validClassName = $this->_validateNamingConvention($className);
        
        if (class_exists($validClassName)) {
            throw new Exception("Class ".$validClassName." already defined.".
                " Cannot redefine class with class loaded.");
        }
        
        if ($addToClassMap) {
            $this->_classmap[$className] = $validClassName;
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
     * 
     * @access private
     * @todo Extend type handling to gracefully manage extendability of wsdl definitions, add reserved keyword checking
     */    
    private function _validateType($type) 
    {
        $array = false;
        if (substr($type, -2) == "[]") {
            $array = true;
            $type = substr($type, 0, -2);
        }
        switch (strtolower($type)) {
        case "int": case "integer": case "long": case "byte": case "short":
        case "negativeInteger": case "nonNegativeInteger": 
        case "nonPositiveInteger": case "positiveInteger":
        case "unsignedByte": case "unsignedInt": case "unsignedLong": case "unsignedShort":
            $validType = "integer";
            break;
            
        case "float": case "long": case "double": case "decimal":
            $validType = "double";
            break;
            
        case "string": case "token": case "normalizedString": case "hexBinary":
            $validType = "string";
            break;
            
        default:
            $validType = $this->_validateNamingConvention($type);
            break;
        }
        if ($array) {
            $validType .= "[]";
        }
        return $validType;
    }        
    
    /**
     * Loads classes from the translated wsdl document's message types 
     * 
     * @access private
     */
    private function _loadClasses() 
    {
        $classes = $this->_dom->getElementsByTagName("class");
        $sources = array();
        foreach ($classes as $class) {
            $class->setAttribute("validatedName", 
                $this->_validateClassName($class->getAttribute("name")));
            $extends = $class->getElementsByTagName("extends");
            if ($extends->length > 0) {
                $extends->item(0)->nodeValue = 
                    $this->_validateClassName($extends->item(0)->nodeValue);
                $classExtension = $extends->item(0)->nodeValue;
            } else {
                $classExtension = false;
            }
            $properties = $class->getElementsByTagName("entry");
            foreach ($properties as $property) {
                $property->setAttribute("validatedName", 
                    $this->_validateNamingConvention($property->getAttribute("name")));
                $property->setAttribute("type", 
                    $this->_validateType($property->getAttribute("type")));
            }
            
            $sources[$class->getAttribute("validatedName")] = array(
                "extends" => $classExtension,
                "source" => $this->_generateClassPHP($class)
            );
        }
        
        while (count($sources) > 0)
        {
            $classesLoaded = 0;
            foreach ($sources as $className => $classInfo) {
                if (!$classInfo["extends"] || (isset($this->_classPHPSources[$classInfo["extends"]]))) {
                    $this->_classPHPSources[$className] = $classInfo["source"];
                    unset($sources[$className]);
                    $classesLoaded++;
                }
            }
            if (($classesLoaded == 0) && (count($sources) > 0)) {
                throw new WSDLInterpreterException("Error loading PHP classes: ".join(", ", array_keys($sources)));
            }
        }
    }
    
    /**
     * Generates the PHP code for a WSDL message type class representation
     * 
     * This gets a little bit fancy as the magic methods __get and __set in
     * the generated classes are used for properties that are not named 
     * according to PHP naming conventions (e.g., "MY-VARIABLE").  These
     * variables are set directly by SoapClient within the target class,
     * and could normally be retrieved by $myClass->{"MY-VARIABLE"}.  For
     * convenience, however, this will be available as $myClass->MYVARIABLE.
     * 
     * @param DOMElement $class the interpreted WSDL message type node
     * @return string the php source code for the message type class
     * 
     * @access private
     * @todo Include any applicable annotation from WSDL
     */
    private function _generateClassPHP($class) 
    {
        $return = '';
        if($this->target_namespace){
          $return .= 'namespace '.$this->target_namespace.';'."\n";
        }
        if($this->add_class_exists){
          $return .= 'if (!class_exists("'.$class->getAttribute("validatedName").'")) {';
        }
        $return .= "\n";
        $return .= '/**'."\n";
        $return .= ' * '.$class->getAttribute("validatedName")."\n";
        $return .= ' */'."\n";
        $return .= "class ".$class->getAttribute("validatedName");
        $extends = $class->getElementsByTagName("extends");
        if ($extends->length > 0) {
            $return .= " extends ".$extends->item(0)->nodeValue;
        }
        $return .= " {\n";
    
        $properties = $class->getElementsByTagName("entry");
        foreach ($properties as $property) {
            $return .= "\t/**\n"
                     . "\t * @access public\n"
                     . "\t * @var ".$property->getAttribute("type")."\n"
                     . "\t */\n"
                     . "\t".'public $'.$property->getAttribute("validatedName").";\n";
        }
    
        $extraParams = false;
        $paramMapReturn = "\t".'private $_parameterMap = array ('."\n";
        $properties = $class->getElementsByTagName("entry");
        foreach ($properties as $property) {
            if ($property->getAttribute("name") != $property->getAttribute("validatedName")) {
                $extraParams = true;
                $paramMapReturn .= "\t\t".'"'.$property->getAttribute("name").
                    '" => "'.$property->getAttribute("validatedName").'",'."\n";
            }
        }
        $paramMapReturn .= "\t".');'."\n";
        $paramMapReturn .= "\t".'/**'."\n";
        $paramMapReturn .= "\t".' * Provided for setting non-php-standard named variables'."\n";
        $paramMapReturn .= "\t".' * @param $var Variable name to set'."\n";
        $paramMapReturn .= "\t".' * @param $value Value to set'."\n";
        $paramMapReturn .= "\t".' */'."\n";
        $paramMapReturn .= "\t".'public function __set($var, $value) '.
            '{ $this->{$this->_parameterMap[$var]} = $value; }'."\n";
        $paramMapReturn .= "\t".'/**'."\n";
        $paramMapReturn .= "\t".' * Provided for getting non-php-standard named variables'."\n";
        $paramMapReturn .= "\t".' * @param $var Variable name to get'."\n";
        $paramMapReturn .= "\t".' * @return mixed Variable value'."\n";
        $paramMapReturn .= "\t".' */'."\n";
        $paramMapReturn .= "\t".'public function __get($var) '.
            '{ return $this->{$this->_parameterMap[$var]}; }'."\n";
        
        if ($extraParams) {
            $return .= $paramMapReturn;
        }
    
        $return .= "}";
        if($this->add_class_exists){
          $return .= '}';
        }
        
        return $return;
    }
    
    /**
     * Loads services from the translated wsdl document
     * 
     * @access private
     */
    private function _loadServices() 
    {
        $services = $this->_dom->getElementsByTagName("service");
        foreach ($services as $service) {
            $service->setAttribute("validatedName", 
                $this->_validateClassName($service->getAttribute("name"), false));
            $functions = $service->getElementsByTagName("function");
            foreach ($functions as $function) {
                $function->setAttribute("validatedName", 
                    $this->_validateNamingConvention($function->getAttribute("name")));
                $parameters = $function->getElementsByTagName("parameters");
                if ($parameters->length > 0) {
                    $parameterList = $parameters->item(0)->getElementsByTagName("entry");
                    foreach ($parameterList as $variable) {
                        $variable->setAttribute("validatedName", 
                            $this->_validateNamingConvention($variable->getAttribute("name")));
                        $variable->setAttribute("type", 
                            $this->_validateType($variable->getAttribute("type")));
                    }
                }
            }
            
            $this->_servicePHPSources[$service->getAttribute("validatedName")] = 
                $this->_generateServicePHP($service);
        }
    }
    
    /**
     * Generates the PHP code for a WSDL service class representation
     * 
     * This method, in combination with generateServiceFunctionPHP, create a PHP class
     * representation capable of handling overloaded methods with strict parameter
     * type checking.
     * 
     * @param DOMElement $service the interpreted WSDL service node
     * @return string the php source code for the service class
     * 
     * @access private
     * @todo Include any applicable annotation from WSDL
     */
    private function _generateServicePHP($service) 
    {
        $return = '';
        if($this->target_namespace){
          $return .= 'namespace '.$this->target_namespace.';'."\n";
        }
        if($this->add_class_exists){
          $return .= 'if (!class_exists("'.$service->getAttribute("validatedName").'")) {';
        }
        $return .= "\n";
        $return .= '/**'."\n";
        $return .= ' * '.$service->getAttribute("validatedName")."\n";
        $return .= ' * @author WSDLInterpreter'."\n";
        $return .= ' */'."\n";
        $return .= "class ".$service->getAttribute("validatedName")." extends \SoapClient {\n";

        if (count($this->_classmap) > 0) {
            $return .= "\t".'/**'."\n";
            $return .= "\t".' * Default class map for wsdl=>php'."\n";
            $return .= "\t".' * @access private'."\n";
            $return .= "\t".' * @var array'."\n";
            $return .= "\t".' */'."\n";
            $return .= "\t".'private static $classmap = array('."\n";
            foreach ($this->_classmap as $className => $validClassName)    {
                $return .= "\t\t".'"'.$className.'" => "'.$validClassName.'",'."\n";
            }
            $return .= "\t);\n\n";
        }
        
        $return .= "\t".'/**'."\n";
        $return .= "\t".' * Constructor using wsdl location and options array'."\n";
        $return .= "\t".' * @param string $wsdl WSDL location for this service'."\n";
        $return .= "\t".' * @param array $options Options for the SoapClient'."\n";
        $return .= "\t".' */'."\n";
        $return .= "\t".'public function __construct($wsdl="'.
            $this->_wsdl.'", $options=array()) {'."\n";
        $return .= "\t\t".'foreach(self::$classmap as $wsdlClassName => $phpClassName) {'."\n";
        $return .= "\t\t".'    if(!isset($options[\'classmap\'][$wsdlClassName])) {'."\n";
        $return .= "\t\t".'        $options[\'classmap\'][$wsdlClassName] = "\\\".__NAMESPACE__."\\\$phpClassName";'."\n";
        $return .= "\t\t".'    }'."\n";
        $return .= "\t\t".'}'."\n";
        $return .= "\t\t".'parent::__construct($wsdl, $options);'."\n";
        $return .= "\t}\n\n";
        $return .= "\t".'/**'."\n";
        $return .= "\t".' * Checks if an argument list matches against a valid '.
            'argument type list'."\n";
        $return .= "\t".' * @param array $arguments The argument list to check'."\n";
        $return .= "\t".' * @param array $validParameters A list of valid argument '.
            'types'."\n";
        $return .= "\t".' * @return boolean true if arguments match against '.
            'validParameters'."\n";
        $return .= "\t".' * @throws \Exception invalid function signature message'."\n"; 
        $return .= "\t".' */'."\n";
        $return .= "\t".'public function _checkArguments($arguments, $validParameters) {'."\n";
        $return .= "\t\t".'$variables = "";'."\n";
        $return .= "\t\t".'foreach ($arguments as $arg) {'."\n";
        $return .= "\t\t".'    $type = gettype($arg);'."\n";
        $return .= "\t\t".'    if ($type == "object") {'."\n";
        $return .= "\t\t".'        $type = preg_replace(\'/^\'.__NAMESPACE__.\'\\\\\/\', \'\', get_class($arg));'."\n";
        $return .= "\t\t".'    }'."\n";
        $return .= "\t\t".'    $variables .= "(".$type.")";'."\n";
        $return .= "\t\t".'}'."\n";
        $return .= "\t\t".'if (!in_array($variables, $validParameters)) {'."\n";
        $return .= "\t\t".'    throw new \Exception("Invalid parameter types: '.
            '".str_replace(")(", ", ", $variables));'."\n";
        $return .= "\t\t".'}'."\n";
        $return .= "\t\t".'return true;'."\n";
        $return .= "\t}\n\n";

        $functionMap = array();        
        $functions = $service->getElementsByTagName("function");
        foreach ($functions as $function) {
            if (!isset($functionMap[$function->getAttribute("validatedName")])) {
                $functionMap[$function->getAttribute("validatedName")] = array();
            }
            $functionMap[$function->getAttribute("validatedName")][] = $function;
        }    
        foreach ($functionMap as $functionName => $functionNodeList) {
            $return .= $this->_generateServiceFunctionPHP($functionName, $functionNodeList)."\n\n";
        }
    
        $return .= "}";
        if($this->add_class_exists){
          $return .= '}';
        }
        return $return;
    }

    /**
     * Generates the PHP code for a WSDL service operation function representation
     * 
     * The function code that is generated examines the arguments that are passed and
     * performs strict type checking against valid argument combinations for the given
     * function name, to allow for overloading.
     * 
     * @param string $functionName the php function name
     * @param array $functionNodeList array of DOMElement interpreted WSDL function nodes
     * @return string the php source code for the function
     * 
     * @access private
     * @todo Include any applicable annotation from WSDL
     */    
    private function _generateServiceFunctionPHP($functionName, $functionNodeList) 
    {
        $return = "";
        $return .= "\t".'/**'."\n";
        $return .= "\t".' * Service Call: '.$functionName."\n";
        $parameterComments = array();
        $variableTypeOptions = array();
        $returnOptions = array();
        foreach ($functionNodeList as $functionNode) {
            $parameters = $functionNode->getElementsByTagName("parameters");
            if ($parameters->length > 0) {
                $parameters = $parameters->item(0)->getElementsByTagName("entry");
                $parameterTypes = "";
                $parameterList = array();
                foreach ($parameters as $parameter) {
                    if (substr($parameter->getAttribute("type"), 0, -2) == "[]") {
                        $parameterTypes .= "(array)";
                    } else {
                        $parameterTypes .= "(".$parameter->getAttribute("type").")";
                    }
                    $parameterList[] = "(".$parameter->getAttribute("type").") ".
                        $parameter->getAttribute("validatedName");
                }
                if (count($parameterList) > 0) {
                    $variableTypeOptions[] = $parameterTypes;
                    $parameterComments[] = "\t".' * '.join(", ", $parameterList);
                }
            }
            $returns = $functionNode->getElementsByTagName("returns");
            if ($returns->length > 0) {
                $returns = $returns->item(0)->getElementsByTagName("entry");
                if ($returns->length > 0) {
                    $returnOptions[] = $returns->item(0)->getAttribute("type");
                }
            }
        }
        $return .= "\t".' * Parameter options:'."\n";
        $return .= join("\n", $parameterComments)."\n";
        $return .= "\t".' * @param mixed,... See function description for parameter options'."\n";
        $return .= "\t".' * @return '.join("|", array_unique($returnOptions))."\n";
        $return .= "\t".' * @throws \Exception invalid function signature message'."\n"; 
        $return .= "\t".' */'."\n";
        $return .= "\t".'public function '.$functionName.'($mixed = null) {'."\n";
        $return .= "\t\t".'$validParameters = array('."\n";
        foreach ($variableTypeOptions as $variableTypeOption) {
            $return .= "\t\t\t".'"'.$variableTypeOption.'",'."\n";
        }
        $return .= "\t\t".');'."\n";
        $return .= "\t\t".'$args = func_get_args();'."\n";
        $return .= "\t\t".'$this->_checkArguments($args, $validParameters);'."\n";
        $return .= "\t\t".'return $this->__soapCall("'.
            $functionNodeList[0]->getAttribute("name").'", $args);'."\n";
        $return .= "\t".'}'."\n";
        
        return $return;
    }
    
    /**
     * Saves the PHP source code that has been loaded to a target directory.
     * 
     * Services will be saved by their validated name, and classes will be included
     * with each service file so that they can be utilized independently.
     * 
     * @param string $outputDirectory the destination directory for the source code
     * @return array array of source code files that were written out
     * @throws WSDLInterpreterException problem in writing out service sources
     * @access public
     * @todo Add split file options for more efficient output
     */
    public function savePHP($outputDirectory) 
    {
        $outputDirectory = rtrim($outputDirectory,"/");
        
        $outputFiles = array();
        
        if(!is_dir($outputDirectory."/")) {
            mkdir($outputDirectory."/");
        }
        
        if( is_null($this->_dom) ){
            // DOM is still null so wsdl is not parsed yet
            // auto parse wsdl from class constructor
            $this->parseWSDL($outputDirectory);
        }
        
        if (count($this->_servicePHPSources) == 0) {
            throw new WSDLInterpreterException("No services loaded");
        }
        
        if(!is_dir($outputDirectory."/classes/")) {
            mkdir($outputDirectory."/classes/");
        }
        
        if($this->one_big_file){
            // legacy behavior
            $classSource = join("\n\n", $this->_classPHPSources);
            
            foreach ($this->_servicePHPSources as $serviceName => $serviceCode) {
                $filename = $outputDirectory."/".$serviceName.".php";
                if (file_put_contents($filename, 
                        "<?php\n\n".$classSource."\n\n".$serviceCode."\n\n?>")) {
                    $outputFiles[] = $filename;
                }
            }
        } else {
            // each class its own file
            foreach($this->_classPHPSources as $className => $classCode) {
                $filename = $outputDirectory."/classes/".$className.".class.php";
                if (file_put_contents($filename, "<?php\n\n".$classCode)) {
                    $outputFiles[] = $filename;
                }
            }
            
            foreach ($this->_servicePHPSources as $serviceName => $serviceCode) {
                $filename = $outputDirectory."/".$serviceName.".php";
                if (file_put_contents($filename, "<?php\n\n".$serviceCode)) {
                    $outputFiles[] = $filename;
                }
            }
        }
        
        if (count($outputFiles) == 0) {
            throw new WSDLInterpreterException("Error writing PHP source files.");
        }
        
        return $outputFiles;
    }
    
    /**
     * Set which PHP namespace to use.
     * 
     * @param string $namespace thePHP namespace name
     * @access public
     */
    public function setPHPNamespace($namespace) 
    {
        $this->target_namespace = $namespace;
    }
    
    /**
     * Set if to use class_exists checks around generated classes
     * 
     * @param boolean $bool
     * @access public
     */
    public function setAddClassExistCheck($bool=true) 
    {
        $this->add_class_exists = $bool;
    }
    
    /**
     * Set if to generate one big file
     * 
     * @param boolean $bool
     * @access public
     */
    public function setOneBigFile($bool=true) 
    {
        $this->one_big_file = $bool;
    }
    
    /**
     * Enable or disable logging
     * 
     * @param boolean $bool
     * @access public
     */
    public function setLogging($bool=true) 
    {
        $this->logging = $bool;
    }
}
?>
