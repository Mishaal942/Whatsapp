<?php
// DEBUG: show all errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// adjust these to your actual credentials:
$host     = 'localhost';
$user     = 'ucgngywmqggyj';
$password = 'stf3gpnewews';
$dbname   = 'db5liukkq0vga7';

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}
?>
