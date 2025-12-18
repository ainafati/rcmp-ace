<?php
include '../config.php';

if (isset($_POST['asset_code'])) {
    $code = trim($_POST['asset_code']);
    
    $stmt = $conn->prepare("SELECT asset_id FROM assets WHERE asset_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "exists"; // Kod dah ada
    } else {
        echo "available"; // Kod boleh guna
    }
    $stmt->close();
}
?>