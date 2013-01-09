<?php
/**
 * Command line wrapper for the WSDLInterpreter class.
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
 * @package     WSDLI  
 * @author      Garry Welding gkwelding@gmail.com
 * @copyright   2013 Garry Welding
 * @license     http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * 
 */
 
require_once 'WSDLInterpreter.class.php';

$wsdlInterpreter = new WSDLInterpreter($argv[1]);

try {
    $results = $wsdlInterpreter->savePHP(dirname( __FILE__ ).'/output/');
    
    echo "Written: \n";
    
    foreach($results as $result) {
        echo "$result \n";
    }
} catch(WSDLInterpreterException $e) {
    echo $e->getMessage();
}