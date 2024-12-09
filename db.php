<?php
$host = 'localhost';
$dbname = 'siva_game';
$username = 'root';
$password = '';

$conn = new mysqli(hostname: $host, username: $username, password: $password, database: $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>