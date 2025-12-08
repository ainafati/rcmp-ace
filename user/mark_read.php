<?php

// Tetapkan laluan yang betul ke fail konfigurasi DB anda!
include '../config.php';

header('Content-Type: application/json');

// Matikan paparan ralat pada persekitaran produksi
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$response = ["success" => false];

if (!$conn) {
    $response["error"] = "Database connection failed.";
    echo json_encode($response);
    exit();
}

if (isset($_POST['reservation_id'])) {
    // Pastikan menggunakan nama kolum yang betul: reserve_id
  $reservationId = intval($_POST['reservation_id']);

    // Gunakan reserve_id (seperti dalam skema DB anda)
  $stmt = $conn->prepare("UPDATE reservations SET status = 'read' WHERE reserve_id = ?");
    
    // Perhatian: Status 'read' ini adalah untuk notifikasi, bukan status tempahan itu sendiri.
    // Pastikan kolum 'status' dalam jadual 'reservations' sesuai untuk tujuan ini.
    
  if ($stmt) {
    $stmt->bind_param("i", $reservationId);

    if ($stmt->execute()) {
      $response["success"] = true;
    } else {
      $response["error"] = "Database execution failed: " . $stmt->error;
    }
        $stmt->close();
  } else {
        $response["error"] = "SQL prepare failed: " . $conn->error;
    }
} else {
  $response["error"] = "Missing reservation_id parameter.";
}

if ($conn) {
    $conn->close();
}

echo json_encode($response);

?>