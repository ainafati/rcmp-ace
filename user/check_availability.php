<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../config.php'; // Gantikan dengan fail sambungan DB anda

header('Content-Type: application/json');

// PERUBAHAN UTAMA: Menerima item_id, reserve_date, dan return_date
if (!isset($_POST['item_id'], $_POST['reserve_date'], $_POST['return_date'])) {
    echo json_encode(['success' => false, 'message' => 'Incomplete data received (Missing item ID or dates).', 'available_quantity' => 0]);
    exit();
}

$item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
$reserve_date = $_POST['reserve_date'];
$return_date = $_POST['return_date'];

if (!$item_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid item ID received.', 'available_quantity' => 0]);
    exit();
}

try {
    
    // 1. Dapatkan Nama Item (untuk logging/mesej)
    $item_name = 'Unknown Item';
    $stmt_item = $conn->prepare("SELECT item_name FROM item WHERE item_id = ?");
    if (!$stmt_item) throw new Exception("Prepare failed (select item name): " . $conn->error);
    
    $stmt_item->bind_param("i", $item_id);
    $stmt_item->execute();
    $result_item = $stmt_item->get_result();
    
    if ($item_row = $result_item->fetch_assoc()) {
        $item_name = $item_row['item_name'];
    } else {
        throw new Exception("Item ID " . $item_id . " not found in the system.");
    }
    $stmt_item->close();

    // 2. Kira Stok Fungsian Keseluruhan
    // Aset yang Available, Reserved, atau Checked Out dikira fungsian.
    // Aset yang Maintenance, Broken, atau Missing dikecualikan.
    $total_functional_stock = 0;
    $stmt_stock = $conn->prepare("
        SELECT COUNT(asset_id) as total_stock
        FROM assets
        WHERE item_id = ? AND status NOT IN ('Maintenance', 'Broken', 'Missing')
    ");
    if (!$stmt_stock) throw new Exception("Prepare failed (count stock): " . $conn->error);
    
    $stmt_stock->bind_param("i", $item_id);
    $stmt_stock->execute();
    $result_stock = $stmt_stock->get_result();
    if ($stock_row = $result_stock->fetch_assoc()) {
        $total_functional_stock = (int)$stock_row['total_stock'];
    }
    $stmt_stock->close();
    
    // 3. Kira Kuantiti Ditempah (Bertindih dengan tempoh masa diminta)
    // Status 'Approved' dan 'Checked Out' dianggap telah mengambil stok.
    $booked_during_period = 0;
    $sql_booked = "
        SELECT COALESCE(SUM(ri.quantity), 0) as booked_qty
        FROM reservation_items ri
        WHERE ri.item_id = ?
        AND ri.status IN ('Approved', 'Checked Out')
        AND ri.reserve_date <= ?
        AND ri.return_date >= ?";
    
    $stmt_booked = $conn->prepare($sql_booked);
    if (!$stmt_booked) throw new Exception("Prepare failed (count booked): " . $conn->error);
    
    // Urutan bind_param: item_id (i), return_date (s), reserve_date (s)
    $stmt_booked->bind_param("iss", $item_id, $return_date, $reserve_date);
    $stmt_booked->execute();
    $result_booked = $stmt_booked->get_result();
    if ($booked_row = $result_booked->fetch_assoc()) {
        $booked_during_period = (int)$booked_row['booked_qty'];
    }
    $stmt_booked->close();

    // 4. Kira Kuantiti Tersedia
    $effective_available_stock = $total_functional_stock - $booked_during_period;
    $effective_available_stock = max(0, $effective_available_stock); // Pastikan tidak negatif

    // 5. Hasilkan Respons JSON
    // JavaScript HANYA memerlukan 'success: true' dan 'available_quantity' untuk diproses.
    $response_data = [
        'success' => true,
        'available_quantity' => $effective_available_stock,
        'message' => 'Availability check successful.'
    ];
    
    echo json_encode($response_data);

} catch (Exception $e) {
    // Tangani semua ralat (DB atau logik)
    error_log("Availability Check Error for item ID $item_id: " . $e->getMessage());
    // Pulangkan respons gagal
    echo json_encode([
        'success' => false,
        'message' => 'System error during availability check: ' . $e->getMessage(),
        'available_quantity' => 0
    ]);
}

$conn->close();
?>
