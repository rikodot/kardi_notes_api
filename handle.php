<?php
//define("DEBUG_SHOW_ERRORS", "TRUE"); //make sure to define this only in endpoints
require_once("../error_handler.php");

$version = "latest";
if (isset($_GET["version"]) && !empty($_GET["version"])) { $version = $_GET["version"]; }

switch ($version)
{
    default:
    case "2.1.1":
    {
        require_once("2.1.1.php");
        break;
    }
}
