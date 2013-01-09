WSDLInterpreter
===============

The WSDLInterpreter package is used for the interpretation of a WSDL document into PHP classes that represent the messages using inheritance and typing as defined by the WSDL rather than SoapClient's limited interpretation.  PHP classes are also created for each service that represent the methods with any appropriate overloading and strict variable type checking as defined by the WSDL.

This package was originally created by Kevin Vaughan and is located at [http://code.google.com/p/wsdl2php-interpreter/](http://code.google.com/p/wsdl2php-interpreter/).

This fork of the original package has some modifications to support separate class files, located under the /classes directory and namespacing of the generated files.

This package is released under the original LGPL License 2.1.

Usage
===============

    php WSDLI.php {url to WSDL document}