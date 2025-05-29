<?php
/*

Authentication_Init.php contains the settings for Authentication.php

*/

// ----- $valid_passwords contains valid username-password combinations

$adminuser1 = "admin1";
$adminuser1pwd = "secret1" ;
$adminuser2 = "q";
$adminuser2pwd = "z";
$valid_passwords = array($adminuser1 => $adminuser1pwd,
                         $adminuser2 => $adminuser2pwd,
                         "usera" => "pwda",
                         "userz" => "pwdz",
                         "Sonos" => "sonoS");

// ----- $adminusers_1 is an array with valid administrator users
// ----- In Collection Manager these can manage collections

$adminusers_1 = array($adminuser1, $adminuser2);

// ----- $adminusers_2 is an array with administrator users with other rights
// ----- In Collection Manager these can start the Sonos Inventory Scan

$adminusers_2 = array($adminuser2);

// The next would create an array with the names of all valid users
//$valid_users = array_keys($valid_passwords);

// ----- Timeout in seconds before user is logged out 

$timeout_duration = 15 * 60; // (15 * 60 = 15 minutes)

// ----- enable / disable logging by Authentication.php

$Authentication_Logging = false;

// ----- Limit the Authentication.php logging for these ip addresses

$my_ips = array("192.168.2.161", "86.83.23.123");

?>
