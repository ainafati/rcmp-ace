<?php
session_start();
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)$_POST['user_id'];
    $all_items = json_decode($_POST['all_items'], true);

    if (!$all_items || count($all_items) === 0) {
        header("Location: reserve.php?error=No items selected");
        exit();
    }

    foreach ($all_items as $item) {
        $sql = "INSERT INTO reservations 
                (user_id, item_id, reserve_date, return_date, reason, status, quantity)
                VALUES (?, ?, ?, ?, ?, 'Pending', ?)";
        $stmt = $conn->prepare($sql);
        if(!$stmt){
            die("SQL Error: " . $conn->error);
        }
        $stmt->bind_param("iisssi",
            $user_id,
            $item['item_id'],
            $item['reserve_date'],
            $item['return_date'],
            $item['reason'],
            $item['quantity']
        );
        $stmt->execute();
        $stmt->close();
    }

    header("Location: history.php?success=1");
    exit();
}
?>