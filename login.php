<?php
// LoginTestThing
// http://github.com/ShedoSurashu/LoginTestThing
// by: @ShedoSurashu

if ((!isset($_REQUEST["username"])) || ($_REQUEST["username"] == "")) {
    die("Please provide a username.");
}

if ((!isset($_REQUEST["password"])) || ($_REQUEST["password"] == "")) {
    die("Please provide a password.");
}

// Edit these to YOUR values. They should be pretty self explanatory.
define("MYSQL_HOSTNAME","localhost");
define("MYSQL_USERNAME","root");
define("MYSQL_PASSWORD","");
define("MYSQL_DATABASE","logintestthing");
define("MYSQL_CHARTYPE","utf8");

include_once("mysql.php");
$db = new MySQL();
$sql = "SELECT * FROM users WHERE username='".mysql_real_escape_string($_REQUEST["username"])."' AND userpass='".mysql_real_escape_string($_REQUEST["password"])."'";
if (!$db->ExecuteSQL($sql)) {
    die("An error occured while logging in.");
}
$account = $db->GetResult();
if ($account === null) {
    die("Account information invalid.");
}

echo "Logged in successfully as user &quot;".$account["username"]."&quot;.";
