<?php
// Gantikan "KataLaluanAdminYangSayaMahu!" dengan kata laluan sebenar anda
$password_sebenar = "#Ainafthh12"; 

// Menjana hash menggunakan algoritma BCRYPT (yang diperlukan oleh kod anda)
$hash_selamat = password_hash($password_sebenar, PASSWORD_DEFAULT); 

echo "Kata Laluan Sebenar: " . $password_sebenar . "<br>";
echo "SALIN HASH INI (termasuk \$2y\$): " . $hash_selamat;
?>