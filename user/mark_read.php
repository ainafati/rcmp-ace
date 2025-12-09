<?php


include '../config.php';

header('Content-Type: application/json');


ini_set('display_errors', 0);
ini_set('log_errors', 1);

$response = ["success" => false];

if (!$conn) {
    $response["error"] = "Database connection failed.";
    echo json_encode($response);
    exit();
}

if (isset($_POST['reservation_id'])) {
    
  $reservationId = intval($_POST['reservation_id']);

    
  $stmt = $conn->prepare("UPDATE reservations SET status = 'read' WHERE reserve_id = ?");
    
    
    
    
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