<?php
$password = 'Ainafthh12'; // tukar ikut apa kau nak
$hashed = password_hash($password, PASSWORD_DEFAULT);

echo "Hashed password: " . $hashed;
?>