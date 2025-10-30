<?php
session_start();
include 'config.php';

// Pastikan hanya pengguna yang log masuk boleh akses
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$events = [];

// Query untuk dapatkan semua tempahan bagi pengguna ini
$sql = "SELECT i.item_name, ri.reserve_date, ri.return_date, ri.status
        FROM reservations r
        JOIN reservation_items ri ON r.reserve_id = ri.reserve_id
        JOIN item i ON ri.item_id = i.item_id
        WHERE r.user_id = ? AND ri.status IN ('approved', 'pending')"; // Hanya papar yang relevan

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Tetapkan warna berdasarkan status
    $color = '';
    if (strtolower($row['status']) === 'approved') {
        $color = '#22c55e'; // Hijau
    } else {
        $color = '#f59e0b'; // Kuning
    }

    // FullCalendar memerlukan tarikh tamat sehari selepas tarikh sebenar untuk paparan yang betul
    $endDate = date('Y-m-d', strtotime($row['return_date'] . ' +1 day'));

    $events[] = [
        'title' => $row['item_name'],
        'start' => $row['reserve_date'],
        'end'   => $endDate,
        'color' => $color,
        'borderColor' => $color
    ];
}

$stmt->close();

// Hantar data dalam format JSON
header('Content-Type: application/json');
echo json_encode($events);
?>