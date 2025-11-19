<?php
define('SMTP_HOST', 'smtp.office365.com'); 

define('SMTP_USER', 'nexcheck.rcmp@unikl.edu.my'); 
define('SMTP_PASS', '#InventorySystem2025'); 

// Port standard untuk STARTTLS (seperti yang diperlukan oleh O365)
define('SMTP_PORT', 587); 

// Protokol keselamatan yang digunakan dengan Port 587
define('SMTP_SECURE', 'tls'); 

// Nama yang akan dipaparkan sebagai pengirim
define('SMTP_FROM_NAME', 'UniKL NEXCHECK Inventory System');

?>