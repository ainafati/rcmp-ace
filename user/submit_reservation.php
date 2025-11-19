<?php
session_start();
// *** SEMAK PATH INI ***
// Guna '../config.php' jika submit_reservation.php berada dalam subfolder (cth: user/)
include '../config.php'; 
header('Content-Type: application/json');

function send_error($message) {
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit();
}

// >>> 1. TAMBAH SEMAKAN SAMBUNGAN KUAT <<<
if (!$conn) {
    send_error('Database connection failed. Check config.php path and settings.');
}

// >>> 2. TAMBAH MOD LAPORAN RALAT UNTUK EXCEPTION <<<
// Ini akan memaksa MySQLi membuang exception pada ralat SQL, membolehkan try/catch berfungsi.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


// 1. Validasi Sesi Pengguna dan Ambil person_id
if (!isset($_SESSION['person_id'])) { 
    send_error('Sesi tamat.'); 
}
$user_id = (int)$_SESSION['person_id'];

if (!isset($_POST['all_items']) || empty($_POST['all_items'])) { 
    send_error('Tiada item dihantar.'); 
}
$items_to_reserve = json_decode($_POST['all_items'], true);

if (empty($items_to_reserve)) { 
    send_error('Senarai item tempahan kosong.'); 
}

// Ambil Priority (program_type)
$priority = isset($_POST['program_type']) ? (int)$_POST['program_type'] : 3;

// Ambil Reason (untuk validasi sahaja)
$request_reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

if (empty($request_reason)) {
    send_error('Sebab tempahan (Purpose of Loan) diperlukan.');
}


$conn->begin_transaction();

try {
    // 2. INSERT INTO reservations
    $stmt_res = $conn->prepare("INSERT INTO reservations (person_id, created_at, priority) VALUES (?, NOW(), ?)");
    if (!$stmt_res) {
        throw new Exception("Error preparing reservation statement: " . $conn->error);
    }
    $stmt_res->bind_param("ii", $user_id, $priority);
    $stmt_res->execute();
    $reserve_id = $conn->insert_id;
    $stmt_res->close();

    
    // 3. INSERT INTO reservation_items
    foreach ($items_to_reserve as $item_data) {
        $item_name = $item_data['item_name'];
        $quantity = (int)$item_data['quantity'];
        
        // Validasi Kuantiti
        if ($quantity <= 0) { 
            throw new Exception("Kuantiti untuk '" . htmlspecialchars($item_name) . "' mesti lebih besar daripada sifar."); 
        }

        // Validasi data penting dari JS
        if (!isset($item_data['reserve_date']) || !isset($item_data['return_date']) || !isset($item_data['reason'])) {
            throw new Exception("Data tarikh atau sebab tidak lengkap untuk item " . htmlspecialchars($item_name) . ".");
        }


        // Dapatkan item_id
        $stmt_find_id = $conn->prepare("SELECT item_id FROM item WHERE item_name = ? LIMIT 1");
        if (!$stmt_find_id) {
            throw new Exception("Error preparing item_id statement: " . $conn->error);
        }
        $stmt_find_id->bind_param("s", $item_name);
        $stmt_find_id->execute();
        $result_id = $stmt_find_id->get_result()->fetch_assoc();
        $stmt_find_id->close();
        
        if (!$result_id) { 
            throw new Exception("Item '" . htmlspecialchars($item_name) . "' tidak wujud dalam pangkalan data master."); 
        }
        $item_id = $result_id['item_id'];

        // Masukkan item tempahan
        $stmt_item = $conn->prepare(
            "INSERT INTO reservation_items (reserve_id, item_id, quantity, reserve_date, return_date, reason, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')"
        );
        if (!$stmt_item) {
            throw new Exception("Error preparing reservation item statement: " . $conn->error);
        }
        
        // Menggunakan reason dari array item yang dihantar oleh JS.
        $stmt_item->bind_param("iiisss", 
            $reserve_id, 
            $item_id, 
            $quantity, 
            $item_data['reserve_date'], 
            $item_data['return_date'], 
            $item_data['reason'] // Diambil dari array item
        );
        $stmt_item->execute();
        $stmt_item->close();
    }

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Tempahan berjaya dihantar!']);

} catch (Exception $e) {
    $conn->rollback();
    // Jika ralat berlaku, ia akan dihantar ke frontend sebagai status: 'error'
    send_error("Submission failed: " . $e->getMessage()); 
}

$conn->close();
?>