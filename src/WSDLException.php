<?php
/**
 * Interprets WSDL documents for the purposes of PHP 5 object creation
 * The WSDLInterpreter package is used for the interpretation of a WSDL
 * document into PHP classes that represent the messages using inheritance
 * and typing as defined by the WSDL rather than SoapClient's limited
 * interpretation.  PHP classes are also created for each service that
 * represent the methods with any appropriate overloading and strict
 * variable type checking as defined by the WSDL.
 * PHP version 5
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
 */

/**
 * A lightweight wrapper of Exception to provide basic package specific
 * unrecoverable program states.
 *
 * @category WebServices
 * @package  WSDLInterpreter
 */
class WSDLInterpreterException extends Exception {

}
