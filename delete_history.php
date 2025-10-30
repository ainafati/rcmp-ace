<?php
include 'config.php';

// Pastikan loan_id dihantar
if (!isset($_POST['loan_id'])) {
    header("Location: check_out.php");
    exit();
}

$loan_id = intval($_POST['loan_id']);

// Delete loan from database
$sql = "DELETE FROM loans WHERE loan_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $loan_id);

if ($stmt->execute()) {
    $stmt->close();
    header("Location: check_out.php");
    exit();
} else {
    die("Error deleting record: " . $conn->error);
}
?>
