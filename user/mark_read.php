<?php



header('Content-Type: application/json');


ini_set('display_errors', 0);
ini_set('log_errors', 1);


include 'db_connect.php'; 

$response = ["success" => false];

if (isset($_POST['reservation_id'])) {
    $reservationId = intval($_POST['reservation_id']);

    $stmt = $conn->prepare("UPDATE reservations SET status = 'read' WHERE id = ?");
    $stmt->bind_param("i", $reservationId);

    if ($stmt->execute()) {
        $response["success"] = true;
    } else {
        $response["error"] = "Database update failed";
    }
} else {
    $response["error"] = "Missing reservation_id";
}

echo json_encode($response);
