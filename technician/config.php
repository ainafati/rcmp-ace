<?php
$servername = "localhost";
$username = "nexcheck_dbuser";
$password = "nexcheck_dbuser";
$database = "inventory"; // nama database kamu

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>