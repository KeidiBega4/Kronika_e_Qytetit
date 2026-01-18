
<?php
require_once __DIR__ . '/helper.php';
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';   //K3idib3g4..1.@
$DB_NAME = 'news_portal';     

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($mysqli->connect_errno) {
    die("Database connection failed: " . $mysqli->connect_error);
}

$mysqli->set_charset('utf8mb4');


$conn = $mysqli;
