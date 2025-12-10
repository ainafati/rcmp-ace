<?php
// send_preparation_reminders_TEST.php

// Sertakan fail konfigurasi dan pangkalan data anda
include __DIR__ . '/../config.php'; 

// Tentukan alamat e-mel Admin/Teknisi yang perlu menerima peringatan
// GANTIKAN DENGAN ALAMAT E-MEL SEBENAR ANDA UNTUK UJIAN
$technician_email = "ainafthhj@gmail.com"; 

// ---------------------------------------------------------------------
// 1. Tentukan Tarikh Sasaran (HARI INI UNTUK UJIAN)
// ---------------------------------------------------------------------

// *** PENTING: Untuk ujian, kita cari tempahan hari ini ***
$target_date = date('Y-m-d'); 
$is_test = true;

// ---------------------------------------------------------------------
// 2. SQL Query: Cari tempahan untuk hari ini
// ---------------------------------------------------------------------

$sql = "SELECT 
            r.reserve_id, 
            r.reserve_date, 
            u.name AS user_name,
            u.email AS user_email,
            i.item_name,
            a.asset_code
        FROM reservations r
        JOIN person u ON r.person_id = u.person_id
        JOIN reservation_items ri ON r.reserve_id = ri.reserve_id
        JOIN item i ON ri.item_id = i.item_id
        LEFT JOIN reservation_assets ra ON ri.id = ra.reservation_item_id
        LEFT JOIN assets a ON ra.asset_id = a.asset_id
        
        WHERE r.reserve_date = ? 
        AND ri.status = 'Pending'  
        GROUP BY ri.id  
        ORDER BY r.reserve_id ASC";


$stmt = $conn->prepare($sql);
if ($stmt === false) {
    echo "SQL Prepare Error: " . $conn->error;
    exit();
}

$stmt->bind_param("s", $target_date);
$stmt->execute();
$result = $stmt->get_result();
$records = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();


// ---------------------------------------------------------------------
// 3. Proses dan Hantar E-mel
// ---------------------------------------------------------------------

if (count($records) > 0) {
    
    $subject = "[TEST] PERINGATAN: Persediaan Item untuk Pengambilan Hari Ini ({$target_date})";
    
    $body = "Salam hormat Admin/Juruteknik,\n\n";
    $body .= "LAPORAN UJIAN: Terdapat item yang perlu disediakan untuk pengambilan pada hari ini ({$target_date}).\n\n";
    $body .= "=================================================\n";
    
    // ... [Logic Grouping kekal sama] ...
    $grouped_reservations = [];
    foreach ($records as $record) {
        $reserve_id = $record['reserve_id'];
        if (!isset($grouped_reservations[$reserve_id])) {
            $grouped_reservations[$reserve_id] = [
                'user_name' => $record['user_name'],
                'reserve_date' => $record['reserve_date'],
                'items' => []
            ];
        }
        $grouped_reservations[$reserve_id]['items'][] = [
            'item_name' => $record['item_name'],
            'asset_code' => $record['asset_code'] ?: 'N/A'
        ];
    }
    
    foreach ($grouped_reservations as $id => $data) {
        $body .= "Tempahan ID: #{$id}\n";
        $body .= "Nama Peminjam: {$data['user_name']}\n";
        $body .= "Tarikh Pengambilan: {$data['reserve_date']}\n";
        $body .= "Item yang diperlukan:\n";
        
        foreach ($data['items'] as $item) {
            $body .= "  - {$item['item_name']} (Asset Code: {$item['asset_code']})\n";
        }
        $body .= "-------------------------------------------------\n";
    }
    
    $body .= "\nSila log masuk ke sistem untuk meluluskan dan menyediakan item ini.\n";
    $body .= "Terima kasih.\n";

    // Header standard untuk fungsi mail()
    $headers = 'From: Inventory System <no-reply@inventori.com>' . "\r\n" .
               'Reply-To: no-reply@inventori.com' . "\r\n" .
               'X-Mailer: PHP/' . phpversion();

    // Hantar e-mel
    $mail_success = mail($technician_email, $subject, $body, $headers);
    
    if ($mail_success) {
        echo "SUCCESS: E-mel peringatan telah dihantar ke {$technician_email} untuk tempahan hari ini ({$target_date}). Sila semak peti masuk anda.";
    } else {
        echo "ERROR: Gagal menghantar e-mel peringatan. Sila semak log ralat PHP/server anda.";
    }

} else {
    echo "INFO: Tiada persediaan diperlukan untuk hari ini ({$target_date}). Sila pastikan anda mempunyai tempahan 'Pending' yang ditetapkan untuk tarikh hari ini dalam pangkalan data anda.";
}

$conn->close();
?>