<?php
if (!defined('LOCAL_MAIL_TEST_CHECK')) {

    define('LOCAL_MAIL_TEST', true); // <--- Tukar ke false untuk hantar email betul

    define('LIVE_SMTP_HOST', 'smtp.office365.com'); 
    define('LIVE_SMTP_USER', 'nexcheck.rcmp@unikl.edu.my'); 
    define('LIVE_SMTP_PASS', '#InventorySystem2025'); 
    define('LIVE_SMTP_PORT', 587); 
    define('LIVE_SMTP_SECURE', 'tls'); 
    define('LIVE_SMTP_FROM_NAME', 'UniKL NEXCHECK Inventory System');

    define('LOCAL_SMTP_HOST', 'localhost'); 
    define('LOCAL_SMTP_PORT', 1025);        
    define('LOCAL_SMTP_USER', 'test-system@unikl.edu.my');          
    define('LOCAL_SMTP_PASS', '');          
    define('LOCAL_SMTP_SECURE', false);     
    define('LOCAL_SMTP_FROM_NAME', 'UniKL NEXCHECK Inventory System (Testing)');

    if (LOCAL_MAIL_TEST === true) {
        define('SMTP_HOST', LOCAL_SMTP_HOST);
        define('SMTP_USER', LOCAL_SMTP_USER);
        define('SMTP_PASS', LOCAL_SMTP_PASS);
        define('SMTP_PORT', LOCAL_SMTP_PORT);
        define('SMTP_SECURE', LOCAL_SMTP_SECURE); 
        define('SMTP_FROM_NAME', LOCAL_SMTP_FROM_NAME);
        define('SMTP_AUTH', false); 
        define('SMTP_DEBUG_LEVEL', 4); 
    } else {
        define('SMTP_HOST', LIVE_SMTP_HOST);
        define('SMTP_USER', LIVE_SMTP_USER);
        define('SMTP_PASS', LIVE_SMTP_PASS);
        define('SMTP_PORT', LIVE_SMTP_PORT);
        define('SMTP_SECURE', LIVE_SMTP_SECURE); 
        define('SMTP_FROM_NAME', LIVE_SMTP_FROM_NAME);
        define('SMTP_AUTH', true); 
        define('SMTP_DEBUG_LEVEL', 0); 
    }

    define('LOCAL_MAIL_TEST_CHECK', true);
}

// --- PEMBETULAN DI SINI ---
// Kita guna "if (!defined(...))" supaya tak ralat kalau fail ni dipanggil 2 kali
if (!defined('TECHNICIAN_GROUP_EMAIL')) {
    define('TECHNICIAN_GROUP_EMAIL', 'it.rcmp@unikl.edu.my'); 
}

if (!defined('BASE_URL')) {
    define('BASE_URL', 'https://nexcheck.rcmp.edu.my/'); // Sesuaikan dengan URL localhost kau
}
?>