<?php
define('HOST', 'localhost');
define('DB_NAME', 'club_management');
define('USER', 'dev'); 
define('PASS', 'dev');

$host = "localhost";
$dbname = "club_management";
$user = "dev";
$pass = "dev";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log($e->getMessage()); // Log the error
    die("Database connection failed. Please try again later.");
}
?>