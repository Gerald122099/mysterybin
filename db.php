<?php
$host = "localhost";   // change if not localhost
$user = "root";        // your MySQL user
$pass = "";            // your MySQL password
$db   = "mysterybin";  // database name

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
