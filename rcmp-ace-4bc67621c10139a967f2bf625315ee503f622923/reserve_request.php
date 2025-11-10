<?php
session_start();
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
    $all_items = json_decode($_POST['all_items'], true);  // Decode the JSON string into an array

    // Insert into the reservations table first
    $reserve_date = $_POST['reserveDate'];
    $return_date = $_POST['endDate'];
    $reason = $_POST['reason'];

    $sql = "INSERT INTO reservations (user_id, reserve_date, return_date, reason, status) VALUES (?, ?, ?, ?, 'pending')";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("isss", $user_id, $reserve_date, $return_date, $reason);
        $stmt->execute();
        $reserve_id = $stmt->insert_id;  // Get the inserted reservation ID
        $stmt->close();

        // Now insert the reservation items
        foreach ($all_items as $item) {
            $item_id = (int)$item['item_id'];
            $quantity = (int)$item['quantity'];

            // Check available quantity and insert reservation items
            $sql_item = "INSERT INTO reservation_items (reserve_id, item_id, quantity) VALUES (?, ?, ?)";
            if ($stmt = $conn->prepare($sql_item)) {
                $stmt->bind_param("iii", $reserve_id, $item_id, $quantity);
                $stmt->execute();
                $stmt->close();
            }

            // Optionally update item quantity in the items table after reservation
            $sql_update_item = "UPDATE item SET quantity = quantity - ? WHERE item_id = ?";
            if ($stmt = $conn->prepare($sql_update_item)) {
                $stmt->bind_param("ii", $quantity, $item_id);
                $stmt->execute();
                $stmt->close();
            }
        }

        // Redirect or give feedback
        header("Location: history.php?status=success");
        exit();
    } else {
        // Handle SQL error
        echo "Error: " . $stmt->error;
    }
}
?>
