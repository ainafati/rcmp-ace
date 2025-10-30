<?php
// config.php
$servername = "localhost";
$username = "root";
$password = ""; // KOSONGKAN JIKA GUNA XAMPP DEFAULT
$dbname = "inventory"; // PASTIKAN NAMA DATABASE BETUL

// Buat sambungan
$conn = new mysqli($servername, $username, $password, $dbname);

// Semak sambungan
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>