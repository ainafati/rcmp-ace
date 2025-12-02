<?php
session_start();

// PASTIKAN LALUAN KE FAIL INI BETUL
include '../config.php'; 
include '../technician/config_email.php'; 
require '../technician/send_email.php';

// Header JSON mesti berada di awal sebelum sebarang output lain
header('Content-Type: application/json');

function send_error($message) {
    // Pastikan JSON yang sah dicetak
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit();
}

// Semak sambungan DB
if (!$conn) {
    send_error('Database connection failed. Check config.php path and settings.');
}

// Laporkan ralat MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// --- 1. Pengesahan Sesi Pengguna ---
if (!isset($_SESSION['person_id'])) { 
    send_error('Sesi tamat. Sila log masuk semula.'); 
}
$user_id = (int)$_SESSION['person_id'];

// --- 2. BACA INPUT JSON MENTAH DARI FRONTEND ---
$json_input = file_get_contents('php://input');
$submission_data = json_decode($json_input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_error('Data tempahan tidak sah (JSON Error).');
}

// Ambil data dari payload JSON
$items_to_reserve = $submission_data['items'] ?? [];
$priority = (int)($submission_data['program_type'] ?? 3);
$request_reason = trim($submission_data['reason'] ?? '');
$reserve_date_context = $submission_data['reserve_date'] ?? null;
$return_date_context = $submission_data['return_date'] ?? null;

// --- 3. Pengesahan Data Tempahan ---
if (empty($items_to_reserve)) { 
    send_error('Senarai item tempahan kosong. Sila tambah item dalam Langkah 2.'); 
}

if (empty($request_reason) || empty($reserve_date_context) || empty($return_date_context)) {
    send_error('Data konteks tempahan tidak lengkap (Tarikh Pinjam/Pulang atau Tujuan Pinjaman diperlukan).');
}

// --- 4. Mulakan Transaksi DB ---
$conn->begin_transaction();

try {
    // --- 4a. INSERT ke dalam jadual 'reservations' (Header Tempahan) ---
    $stmt_res = $conn->prepare("INSERT INTO reservations (person_id, created_at, priority) VALUES (?, NOW(), ?)");
    if (!$stmt_res) {
        throw new Exception("Error preparing reservation statement: " . $conn->error);
    }
    $stmt_res->bind_param("ii", $user_id, $priority);
    $stmt_res->execute();
    $reserve_id = $conn->insert_id;
    $stmt_res->close();

    // --- 4b. INSERT ke dalam jadual 'reservation_items' (Item Tempahan) ---
    foreach ($items_to_reserve as $item_data) {
        
        $item_id = (int)($item_data['item_id'] ?? 0); 
        $item_name = $item_data['item_name'] ?? 'Unknown Item';
        $quantity = (int)($item_data['quantity'] ?? 0);
        
        // Pengesahan Kuantiti
        if ($quantity <= 0) { 
            throw new Exception("Kuantiti untuk '" . htmlspecialchars($item_name) . "' mesti lebih besar daripada sifar."); 
        }

        // Pengesahan Item ID (Optional but recommended)
        if ($item_id <= 0) {
            throw new Exception("Item ID tidak sah untuk item: " . htmlspecialchars($item_name));
        }
        
        // INSERT INTO reservation_items
        $stmt_item = $conn->prepare(
            "INSERT INTO reservation_items (reserve_id, item_id, quantity, reserve_date, return_date, reason, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')"
        );
        if (!$stmt_item) {
            throw new Exception("Error preparing reservation item statement: " . $conn->error);
        }
        
        // Guna tarikh dan sebab dari konteks keseluruhan tempahan
        $stmt_item->bind_param("iiisss", 
            $reserve_id, 
            $item_id, 
            $quantity, 
            $reserve_date_context, // Dari Konteks
            $return_date_context, // Dari Konteks
            $request_reason 
        );
        $stmt_item->execute();
        $stmt_item->close();
    }

    // --- 5. Commit Transaksi dan Hantar Notifikasi ---
    $conn->commit();
    
    // Dapatkan nama pengguna untuk e-mel
    $technician_email = TECHNICIAN_GROUP_EMAIL; 
    $stmt_user_name = $conn->prepare("SELECT name FROM person WHERE person_id = ?");
    $stmt_user_name->bind_param("i", $user_id);
    $stmt_user_name->execute();
    $user_data = $stmt_user_name->get_result()->fetch_assoc();
    $user_name = $user_data['name'] ?? 'User Unknown';
    $stmt_user_name->close();
    
    // LOGIK BARU UNTUK RINGKASAN ITEM DALAM E-MEL (Mesra Pengguna)
    $count = count($items_to_reserve);
    $item_summary = '';

    if ($count === 1) {
        // 1 item: Tunjukkan nama item tersebut
        $item_summary = $items_to_reserve[0]['item_name'] ?? 'Single Item';
    } elseif ($count >= 2) {
        // 2 atau lebih item: Tunjukkan 2 item pertama + kiraan
        $first_item = htmlspecialchars($items_to_reserve[0]['item_name']);
        
        // Cuba dapatkan item kedua
        $second_item = $items_to_reserve[1]['item_name'] ?? null;
        if ($second_item) {
            $second_item = htmlspecialchars($second_item);
        }
        
        if ($count === 2) {
            // Hanya 2 item: Tunjukkan kedua-duanya
            $item_summary = "2 Items ($first_item, $second_item)";
        } else {
            // 3 atau lebih item: Tunjukkan 2 yang pertama dan "..."
            $other_count = $count - 2;
            $item_summary = "$count Items ($first_item, $second_item, +$other_count more)";
        }
    } else {
        $item_summary = 'No Items';
    }

    // Data untuk e-mel notifikasi
    $reserve_date_str = $reserve_date_context;
    $link_to_approval = BASE_URL . 'index.php?page=approvals&reserve_id=' . $reserve_id; 
    
    $email_sent = false;
    
    if (defined('TECHNICIAN_GROUP_EMAIL') && defined('BASE_URL')) {
        $email_sent = sendNewReservationNotification(
            $technician_email, 
            $reserve_id, 
            $user_name, 
            $item_summary, // <--- Guna ringkasan baru yang betul
            $reserve_date_str, 
            $link_to_approval
        );
    }
    
    $email_message = $email_sent ? ' Notifikasi juruteknik dihantar.' : ' Amaran: Gagal hantar notifikasi e-mel juruteknik. Sila semak log ralat.';
    
    // Cetak mesej kejayaan (Ini adalah output JSON yang perlu dibaca oleh JS)
    echo json_encode(['status' => 'success', 'message' => 'Tempahan berjaya dihantar!' . $email_message]);

} catch (Exception $e) {
    // --- 6. Rollback jika ada ralat ---
    $conn->rollback();
    
    send_error("Submission failed: " . $e->getMessage()); 
}

$conn->close();
// TIADA tag penutup ?>