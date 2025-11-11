<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "localhost";
$user = "root";
$pass = ""; 
$db = "inventory";

$conn = new mysqli($host, $user, $pass, $db);

if( !$db ) {
    die("Gagal terhubung dengan database:" . mysqli_connect_error());

}

?>