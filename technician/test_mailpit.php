<?php
// -----------------------------------------------------
// --- Pastikan Laragon Start All, dan Mailpit Aktif ---
// -----------------------------------------------------

$to      = 'test-penerima@mailpit.dev'; // Alamat penerima tidak penting
$subject = 'MAILPIT TEST: Berjaya Berfungsi! ' . date('H:i:s');
$message = 'Ini adalah emel ujian dari skrip PHP asas anda. Jika anda melihat ini di http://localhost:8025, Mailpit berfungsi!';
$headers = 'From: test-pengirim@local.test' . "\r\n" .
           'Reply-To: no-reply@local.test' . "\r\n" .
           'X-Mailer: PHP/' . phpversion();

// Fungsi mail() akan cuba hantar emel ke port 1025 Mailpit
if (mail($to, $subject, $message, $headers)) {
    echo "✅ Emel asas berjaya dipanggil (sepatutnya dipintas oleh Mailpit).";
} else {
    echo "❌ Ralat! Fungsi mail() gagal dipanggil. Semak error log anda.";
}

?>