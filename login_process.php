<?php

session_start();
include 'config.php';

if ($conn->connect_error) {
    // Jika ada masalah, pastikan mesej ralat ini dipaparkan
    die("STATUS DB: CONNECTION FAILED. Please check database server and credentials."); 
} else {
    // Jika berjaya, pastikan mesej ini dipaparkan
    die("STATUS DB: CONNECTION SUCCESS. Problem is in the logger/login logic below.");
}
?>