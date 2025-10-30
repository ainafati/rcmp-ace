<?php
session_start();
session_unset();    // buang semua session variable
session_destroy();  // hancurkan session sepenuhnya
header("Location: ../index.php"); // balik ke login page utama
exit();
?>