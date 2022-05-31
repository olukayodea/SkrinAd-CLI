<?php
include_once("cred.php");

$argData = $_SERVER['argv'][1];

if ($argData == "dev") {
    define("URL", $URL_d);
    define("servername",  $servername_d);
    define("dbusername",  $dbusername_d);
    define("dbpassword",  $dbpassword_d);
    define("dbname",  $dbname_d);
} else if ($argData == "live") {
    define("URL", $URL_l);
    define("servername",  $servername_l);
    define("dbusername",  $dbusername_l);
    define("dbpassword",  $dbpassword_l);
    define("dbname",  $dbname_l);
} else {
    define("URL", $URL);
    define("servername",  $servername);
    define("dbusername",  $dbusername);
    define("dbpassword",  $dbpassword);
    define("dbname",  $dbname);
}
?>