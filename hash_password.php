<?php
$password = 'Ainafthh12'; 
$hashed = password_hash($password, PASSWORD_DEFAULT);

echo "Hashed password: " . $hashed;
?>