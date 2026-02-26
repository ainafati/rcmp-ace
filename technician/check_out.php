<?php
session_start();
include '../config.php';
include '../logger.php';


function get_pending_count($conn) {
    // Guna TRIM dan LOWER supaya dia tak kisah huruf besar/kecil atau space terbiar
    $sql = "SELECT COUNT(id) AS total FROM reservation_items WHERE LOWER(TRIM(status)) = 'pending'";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        return (int)$row['total'];
    }
    return 0;
}

// --- 1. SET VARIABLES ---
date_default_timezone_set('Asia/Kuala_Lumpur');
$currentDate = date('Y-m-d');
$currentTime = date('H:i');

// --- PROSES PEMBERSIHAN (Hanya jalan selepas 6 petang) ---
if ($currentTime >= '10:00') {

    // SITUASI 1: Part Approve (Pending -> Voided/Lapsed)
    // Reason: Technician tak approve sampai hari kejadian berlalu
    $sql_void_pending = "UPDATE reservation_items ri
                         JOIN reservations r ON ri.reserve_id = r.reserve_id
                         SET ri.status = 'Voided', 
                             ri.rejection_reason = 'System: Your request was not processed within the required timeframe. Please submit a new request if the items are still needed.'
                         WHERE ri.status = 'Pending' 
                         AND r.reserve_date < '$currentDate'";
    
    $conn->query($sql_void_pending);

    // SITUASI 2: Part Check Out (Approved -> Rejected/Auto-Cancelled)
    // Reason: User tak datang ambil barang
    $sql_find_abandoned = "SELECT ri.id FROM reservation_items ri
                           JOIN reservations r ON ri.reserve_id = r.reserve_id
                           WHERE ri.status = 'Approved' 
                           AND r.reserve_date < '$currentDate'";
    
    $abandoned_res = $conn->query($sql_find_abandoned);

    if ($abandoned_res && $abandoned_res->num_rows > 0) {
        while ($row = $abandoned_res->fetch_assoc()) {
            $ri_id = $row['id'];
            
            // Update status & Beri alasan spesifik
            $conn->query("UPDATE reservation_items SET 
                            status = 'Rejected', 
                            rejection_reason = 'System: Auto-cancelled due to non-collection by user' 
                          WHERE id = '$ri_id'");
            
            // Lepaskan Asset
            $conn->query("UPDATE assets SET status = 'Available' WHERE asset_id IN (SELECT asset_id FROM reservation_assets WHERE reservation_item_id = '$ri_id')");
        }
    }
}

// Ambil data session untuk logger
$user_role = $_SESSION['logged_in_role'] ?? 'System';
$person_id = $_SESSION['person_id'] ?? 0;

if (!isset($_SESSION['person_id'])) {
    header("Location: ../login.php");
    exit();
}
$person_id = (int) $_SESSION['person_id']; 

// 2. Tarik Data Technician (Satu query sahaja)
$stmt = $conn->prepare("SELECT name, email FROM person WHERE person_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $person_id);
    $stmt->execute();
    $tech = $stmt->get_result()->fetch_assoc(); // Simpan dalam $tech
    $stmt->close();
}

if (!$tech) {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// 3. Logik Nama Pendek (Gunakan $tech, bukan $user)
$fullName = $tech['name'] ?? 'Guest User';
$lowerName = strtolower($fullName);

$posBinti = strpos($lowerName, ' binti ');
$posBin = strpos($lowerName, ' bin ');

if ($posBinti !== false) {
    $shortName = substr($fullName, 0, $posBinti);
} elseif ($posBin !== false) {
    $shortName = substr($fullName, 0, $posBin);
} else {
    $shortName = $fullName;
}

$displayName = trim($shortName);


$filter_date = isset($_GET['filter_date']) && !empty($_GET['filter_date']) ? $_GET['filter_date'] : null;



function fetch_reservations_by_status($conn, $statuses, $filter_date) {
    $status_placeholders = implode(',', array_fill(0, count($statuses), '?'));

// Cari bahagian ni dalam function fetch_reservations_by_status
$sql = "SELECT
            ri.id AS reservation_item_id, ri.status, ri.rejection_reason, ri.quantity, 
            ri.approved_by, ri.checked_out_by, ri.checked_in_by,
            ri.approved_on, ri.checked_out_on,   -- Tambah ni
            ri.checked_in_on,  -- <--- TAMBAH COLUMN INI DI SINI
            r.reserve_date, r.return_date, r.created_at AS apply_date, 
            r.priority, r.reserve_id, r.reason AS reservation_reason,
            r.location, 
            u.name AS user_name, u.phoneNum AS user_phone,
            u.person_id AS user_person_id,
            i.item_name, i.item_id,            
            -- TARIK NAMA ADMIN (Audit Trail) --
            adm1.name AS approved_by_name,
            adm2.name AS checked_out_by_name,
            adm3.name AS checked_in_by_name,

            (SELECT GROUP_CONCAT(CONCAT(a.asset_code, ' (', a.brand, ')') SEPARATOR ', ') 
             FROM reservation_assets ra 
             JOIN assets a ON ra.asset_id = a.asset_id 
             WHERE ra.reservation_item_id = ri.id) AS assigned_assets
        FROM reservation_items ri
        JOIN reservations r ON ri.reserve_id = r.reserve_id
        JOIN person u ON r.person_id = u.person_id 
        JOIN item i ON ri.item_id = i.item_id 
        -- JOIN UNTUK NAMA ADMIN --
        LEFT JOIN person adm1 ON ri.approved_by = adm1.person_id
        LEFT JOIN person adm2 ON ri.checked_out_by = adm2.person_id
        LEFT JOIN person adm3 ON ri.checked_in_by = adm3.person_id
        WHERE ri.status IN ($status_placeholders)";

		
    $bind_types = str_repeat('s', count($statuses));
    $bind_values = $statuses;

  if ($filter_date) {
    // Guna DATE() supaya dia tak keliru dengan format timestamp
    $sql .= " AND DATE(r.reserve_date) = ?"; 
    $bind_types .= 's';
    $bind_values[] = $filter_date;
}
    
    $sql .= " ORDER BY u.name ASC, r.reserve_id ASC, r.created_at ASC"; 

    
    $stmt = $conn->prepare($sql);

    
    if ($stmt === false) {
        die('SQL Prepare failed: ' . $conn->error . '. Query: ' . $sql);
    }
    
    
    $bind_params = [];
    $bind_params[] = $bind_types;
    foreach ($bind_values as $key => $value) {
        $bind_params[] = &$bind_values[$key]; 
    }
    
    call_user_func_array([$stmt, 'bind_param'], $bind_params); 

    
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $result;
}

$pending_requests = fetch_reservations_by_status($conn, ['Pending'], $filter_date);
$approved_requests = fetch_reservations_by_status($conn, ['Approved'], $filter_date);
$on_loan_requests = fetch_reservations_by_status($conn, ['Checked Out'], $filter_date);
$completed_requests = fetch_reservations_by_status($conn, ['Returned', 'Rejected', 'Cancelled', 'Voided'], $filter_date);


// --- KEMASKINI BAHAGIAN INI ---
$assetSql = "
    SELECT asset_id, item_id, asset_code, brand, model, serial_number 
    FROM assets 
    WHERE status = 'Available'
";
$assetResult = $conn->query($assetSql);
if (!$assetResult) {
    die("Error fetching available assets: " . $conn->error);
}
$availableAssets = [];
while ($row = $assetResult->fetch_assoc()) { 
    // Kita simpan semua data termasuk serial_number ke dalam array
    $availableAssets[$row['item_id']][] = $row; 
}
$availableAssets_json = json_encode($availableAssets);


function create_request_table($requests) {
    if (empty($requests)) {
        echo '
        <div class="d-flex flex-column align-items-center justify-content-center py-5">
            <div class="empty-state-icon mb-3" style="background: #f1f5f9; width: 80px; height: 80px; border-radius: 20px; display: flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-box-open fa-3x" style="color: #cbd5e1;"></i>
            </div>
            <h6 class="fw-bold text-dark mb-1">No Records Found</h6>
            <p class="text-muted small">No reservations found matching the criteria.</p>
        </div>';
        return;
    }

    // 1. GROUPING SEMUA DATA DULU
    $grouped_by_user = [];
    foreach ($requests as $row) {
        $grouped_by_user[$row['user_name']][] = $row;
    }

    // 2. LOGIC PAGINATION (Kira berdasarkan jumlah User)
    $limit = 10; 
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $start_index = ($page - 1) * $limit;
    
    $total_users = count($grouped_by_user);
    $total_pages = ceil($total_users / $limit);

    // POTONG ARRAY (Ambil 5 user sahaja untuk page ni)
    $current_page_users = array_slice($grouped_by_user, $start_index, $limit, true);

    $main_accordion_id = 'accordion_main_' . uniqid();
    echo '<div class="accordion" id="' . $main_accordion_id . '">';

    // 3. LOOP USER (Guna $current_page_users)
    $user_index = $start_index; 
    foreach ($current_page_users as $user_name => $user_items) {
        
        $grouped_by_reserve = [];
        foreach ($user_items as $item) {
            $grouped_by_reserve[$item['reserve_id']][] = $item;
        }

        $user_safe_id = preg_replace('/[^A-Za-z0-9]/', '', $user_name) . $user_index;
        $user_collapse_id = 'collapse_user_' . $user_safe_id;
        $user_header_id = 'header_user_' . $user_safe_id;
        $inner_accordion_id = 'inner_accordion_' . $user_safe_id;

       // --- LEVEL 1: USER ACCORDION (Design Siti Aminah) ---
// --- LEVEL 1: USER ACCORDION (Ditambah class user-row untuk search) ---
// Kita tambah class 'user-row' dan 'data-user-name' supaya JS boleh tapis level ni.
echo '<div class="accordion-item shadow-sm mb-3 border-0 rounded-4 overflow-hidden user-row" 
           data-user-name="' . htmlspecialchars($user_name) . '" 
           style="border: 1px solid #eef2f7 !important;">';
echo '  <div class="accordion-header" id="' . $user_header_id . '">';
echo '    <button class="accordion-button collapsed bg-white py-3 px-4" type="button" data-bs-toggle="collapse" data-bs-target="#' . $user_collapse_id . '" style="box-shadow: none;">';
echo '      <div class="d-flex align-items-center w-100">';
// Avatar Biru
echo '        <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px; background: #eef4ff; color: #3b82f6;">';
echo '           <i class="fa-solid fa-user"></i>';
echo '        </div>';
// Nama & Phone
echo '        <div class="flex-grow-1">';
echo '          <div class="fw-bold text-dark fs-6">' . htmlspecialchars($user_name) . '</div>';
echo '          <div class="text-muted small"><i class="fa-solid fa-phone me-1" style="font-size: 0.7rem;"></i>' . htmlspecialchars($user_items[0]['user_phone']) . '</div>';
echo '        </div>';
// Badge Booking
echo '        <span class="badge bg-light text-dark border rounded-pill px-3 py-2 fw-medium me-3" style="font-size: 0.8rem;">' . count($grouped_by_reserve) . ' Booking(s) <i class="fa-solid fa-chevron-down ms-1" style="font-size: 0.6rem;"></i></span>';
echo '      </div>';
echo '    </button>';
echo '  </div>';

echo '  <div id="' . $user_collapse_id . '" class="accordion-collapse collapse" data-bs-parent="#' . $main_accordion_id . '">';
echo '    <div class="accordion-body p-4 bg-white border-top">';
echo '      <div class="accordion" id="' . $inner_accordion_id . '">';

        $reserve_index = 0;
        foreach ($grouped_by_reserve as $reserve_id => $reservation_items) {
            $priority = $reservation_items[0]['priority'];
            $priorityClass = ($priority == 1) ? 'priority-high' : (($priority == 2) ? 'priority-mid' : '');
            $reserve_collapse_id = 'collapse_reserve_' . $user_index . '_' . $reserve_index;
            $status_check = strtolower(trim($reservation_items[0]['status']));

           // --- LEVEL 2: RESERVATION ID CARD ---
echo '<div class="mb-3">';

// Tambah data-bs-toggle & data-bs-target supaya boleh klik untuk buka table
echo '  <div class="d-flex align-items-center justify-content-between p-2 px-3 mb-0" 
             style="background: #f8fafc; border-radius: 10px 10px 0 0; border: 1px solid #edf2f7; cursor: pointer;" 
             data-bs-toggle="collapse" 
             data-bs-target="#' . $reserve_collapse_id . '">';

    echo '    <div class="d-flex align-items-center gap-3">';

        // Paparan Nombor Giliran yang lebih "Sharp"
        $display_number = $reserve_index + 1;
        echo '      <div style="font-size: 0.75rem; min-width: 40px;">';
        echo '        <span class="text-muted fw-bold">NO:</span> ';
        echo '        <span class="text-primary fw-bold">' . $display_number . '</span>'; 
        echo '      </div>';

        // Tarikh (Kekalkan design kau tapi kecikkan sikit bagi balance)
        echo '      <div class="d-none d-md-block text-start border-start ps-3">';
        echo '        <div class="text-muted small" style="font-size: 0.65rem; line-height:1;">Applied on</div>';
        echo '        <div class="fw-bold text-dark" style="font-size: 0.75rem;">' . date('d M Y', strtotime($reservation_items[0]['apply_date'])) . '</div>';
        echo '      </div>';
        
        echo '      <i class="fa-solid fa-chevron-down ms-2 text-muted" style="font-size: 0.7rem;"></i>';
    echo '    </div>';

    // Bahagian Kanan (Butang & Badge Item)
    echo '    <div class="d-flex align-items-center gap-2" onclick="event.stopPropagation();">'; 
    // ^ event.stopPropagation supaya bila klik butang, dia tak tertutup/terbuka accordion tu.
    
    if ($status_check === 'approved') {
        echo '<button type="button" class="btn btn-primary btn-sm rounded-pill px-3 checkout-all-btn" 
                      style="font-size: 0.7rem;" 
                      data-reserve-id="' . $reserve_id . '">
                <i class="fa-solid fa-box-open me-1"></i> Check Out All
              </button>';
    }
    
    echo '      <span class="badge bg-white text-dark border rounded-pill fw-normal px-2 py-1" style="font-size: 0.7rem;">';
    echo '        <i class="fa-solid fa-boxes-stacked me-1 text-secondary"></i> ' . count($reservation_items) . ' Item(s)';
    echo '      </span>';
    echo '    </div>';

echo '  </div>'; // Tutup Header Bar

            // --- LEVEL 3: TABLE DETAIL ---
            echo '  <div id="' . $reserve_collapse_id . '" class="accordion-collapse collapse" data-bs-parent="#' . $inner_accordion_id . '">';
            echo '    <div class="accordion-body p-0">';
            echo '      <div class="table-responsive"><table class="table mb-0 align-middle">';
            echo '        <thead><tr class="table-light text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">';
            echo '          <th class="ps-3 py-3" style="width: 20%;">Item / Priority</th>';
            echo '          <th style="width: 25%;">Location / Purpose</th>'; // Header dikemaskini
            echo '          <th class="text-center" style="width: 10%;">Qty</th>';
            echo '          <th style="width: 20%;">Duration</th>';
            echo '          <th class="text-center" style="width: 10%;">Status</th>';
            echo '          <th class="text-center pe-3" style="width: 15%;">Actions</th>';
            echo '        </tr></thead><tbody>';
			
			
foreach ($reservation_items as $row) {
    $status = strtolower(trim($row['status']));
    $id = $row['reservation_item_id'];
    
    // Warna Badge Priority (Ikut gambar: High = Red)
    $p_class = ($row['priority'] == 1) ? 'bg-danger' : (($row['priority'] == 2) ? 'bg-warning text-dark' : 'bg-info text-dark');
    $p_text = ($row['priority'] == 1) ? 'High' : (($row['priority'] == 2) ? 'Moderate' : 'Low');

    // Status Icon Logic
    $icon = 'fa-regular fa-clock'; $color = '#ffc107'; 
    if($status == 'approved'){ $icon = 'fa-solid fa-circle-check'; $color = '#10b981'; }

    echo "<tr id='row-{$id}' class='border-bottom' 
            data-qty='{$row['quantity']}' 
            data-item-id='{$row['item_id']}' 
            data-user-name='" . htmlspecialchars($user_name) . "' 
            data-itemname='" . htmlspecialchars($row['item_name']) . "'>";

    // 1. KOLOM ITEM / PRIORITY (Design bersih dengan badge bulat)
    echo "<td class='ps-4 py-4'>";
    echo "  <div class='fw-bold text-dark fs-6 mb-1'>" . htmlspecialchars($row['item_name']) . "</div>";
    echo "  <span class='badge $p_class rounded-pill px-2' style='font-size:0.65rem; font-weight:600;'>$p_text</span>";
    echo "</td>";

// --- TAMBAH INI: Papar reason jika ada ---
if (!empty($row['rejection_reason'])) {
    echo "<div class='mt-2 p-2 rounded' style='background: #fff5f5; border-left: 3px solid #feb2b2; font-size: 0.7rem;'>";
    echo "  <strong class='text-danger'>Reason:</strong> <span class='text-muted'>" . htmlspecialchars($row['rejection_reason']) . "</span>";
    echo "</div>";
}
echo "</td>";

    // 2. KOLOM LOCATION / PURPOSE (Guna icon kelabu)
    echo "<td>";
// Buang tanda ; selepas ?? ''
echo "  <div class='text-muted small mb-1'><i class='fa-solid fa-location-dot me-1'></i>" . htmlspecialchars($row['location'] ?? '') . "</div>";
    echo "  <div class='text-muted small' style='font-size: 0.75rem;'>" . htmlspecialchars($row['reservation_reason'] ?? 'Purpose not stated') . "</div>";
    echo "</td>";


    // 3. KOLOM QTY (Teks besar & bold)
    echo "<td class='text-center fw-bold fs-5' style='color: #1e293b;'>" . htmlspecialchars($row['quantity']) . "</td>";

    // 4. KOLOM DURATION (Tarikh range)
    $start = date('d M', strtotime($row['reserve_date']));
    $end = date('d M Y', strtotime($row['return_date']));
    echo "<td><div class='small fw-medium text-dark'>$start - $end</div></td>";
// 5. KOLOM STATUS (Icon dengan Tooltip/Hover)
echo "<td class='text-center'>";
    
    // Logic untuk pilih Icon, Warna dan Teks Hover (Title)
    $status_text = ucwords($status); // Tukar 'pending' jadi 'Pending'
    $icon = 'fa-regular fa-clock'; 
    $color = '#ffc107'; // Kuning (Default Pending)

    if($status == 'approved'){ 
        $icon = 'fa-solid fa-circle-check'; 
        $color = '#10b981'; // Hijau
    } elseif($status == 'rejected'){
        $icon = 'fa-solid fa-circle-xmark'; 
        $color = '#ef4444'; // Merah
    } elseif($status == 'on loan' || $status == 'checked out'){
        $icon = 'fa-solid fa-box-open'; 
        $color = '#3b82f6'; // Biru
   } elseif($status == 'returned'){
        $icon = 'fa-solid fa-house-circle-check'; 
        $color = '#6366f1'; // Indigo
    } elseif($status == 'voided'){ // <--- TAMBAH INI
        $icon = 'fa-solid fa-hourglass-end'; 
        $color = '#94a3b8'; // Warna Kelabu (Slate)
        $status_text = 'Voided: Technician did not approve in time';
    }
    // Paparkan Icon. 'title' akan keluar bila cursor duduk atas icon tu.
    echo "<i class='$icon fs-5' 
             style='color: $color; cursor: help;' 
             data-bs-toggle='tooltip' 
             data-bs-placement='top' 
             title='$status_text'></i>";

echo "</td>";
    // 6. KOLOM ACTIONS (Button rounded-pill seperti gambar)
    echo "<td class='text-center pe-4'>";
    if ($status === 'pending') {
        echo "<div class='d-flex gap-2 justify-content-center'>";
        echo "  <button class='btn btn-success btn-sm rounded-pill px-3 d-flex align-items-center gap-1' onclick='event.stopPropagation(); openApproveModal($id)' style='background:#10b981; border:none;'>";
        echo "      <i class='fa-regular fa-circle-check'></i> Approve</button>";
        echo "  <button class='btn btn-danger btn-sm rounded-pill px-3 d-flex align-items-center gap-1' onclick='event.stopPropagation(); openRejectModal($id)' style='background:#ef4444; border:none;'>";
        echo "      <i class='fa-regular fa-circle-xmark'></i> Reject</button>";
        echo "</div>";
    } elseif ($status === 'approved') {
    // Kita tambah <span> pada tulisan 'Check Out' untuk kecilkan font dia
    echo "<button class='btn btn-primary btn-sm rounded-pill px-3 d-flex align-items-center' onclick='event.stopPropagation(); checkOutItem($id)' style='font-size: 12px; gap: 5px;'>";
    echo "<i class='fa-solid fa-truck-ramp-box'></i>";
    echo "<span style='font-size: 11px; font-weight: 600;'>Check Out</span>";
    echo "</button>";
} elseif ($status === 'on loan' || $status === 'checked out'){
    // SINI: Aku dah buang button info biru yang buat dia nampak double tu
    echo "<button class='btn btn-warning btn-sm px-3 rounded-pill text-dark' onclick='event.stopPropagation(); checkInItem($id)' title='Return Asset'><i class='fa-solid fa-box-open me-1'></i>Return</button>";
} else {
    // Kita simpan nama-nama admin dalam attribute 'data-'
    $app_name = htmlspecialchars($row['approved_by_name'] ?? 'Not available');
    $out_name = htmlspecialchars($row['checked_out_by_name'] ?? 'Not available');
    $in_name = htmlspecialchars($row['checked_in_by_name'] ?? 'Not available');

    echo "<button class='btn btn-outline-secondary btn-sm rounded-pill px-3' 
            onclick='openAuditModal(\"$app_name\", \"$out_name\", \"$in_name\")'>
            <i class='fa-solid fa-clock-rotate-left me-1'></i> History
          </button>";
}
echo "</td>";

    echo "</tr>";
}
            echo '</tbody></table></div>';
            echo '    </div>'; 
            echo '  </div>';
            echo '</div>';
            $reserve_index++;
        }
        echo '      </div>';
        echo '    </div>';
        echo '  </div>';
        echo '</div>';
        $user_index++;
    }
    echo '</div>';

    // 4. RENDER BUTTON PAGING (Letak bawah sekali)
    if ($total_pages > 1) {
        echo '<div class="d-flex flex-column align-items-center mt-4">';
        echo '  <nav><ul class="pagination pagination-sm mb-2">';
        
        $prev_dis = ($page <= 1) ? 'disabled' : '';
        echo '<li class="page-item '.$prev_dis.'"><a class="page-link shadow-sm border-0 rounded-pill px-3 me-2" href="?page='.($page-1).'"><i class="fa-solid fa-angle-left"></i></a></li>';

        for ($i = 1; $i <= $total_pages; $i++) {
            $act = ($page == $i) ? 'active' : '';
            echo '<li class="page-item '.$act.'"><a class="page-link shadow-sm border-0 mx-1 rounded-circle" style="width:32px; height:32px; text-align:center;" href="?page='.$i.'">'.$i.'</a></li>';
        }

        $next_dis = ($page >= $total_pages) ? 'disabled' : '';
        echo '<li class="page-item '.$next_dis.'"><a class="page-link shadow-sm border-0 rounded-pill px-3 ms-2" href="?page='.($page+1).'"><i class="fa-solid fa-angle-right"></i></a></li>';
        
        echo '</ul></nav>';
        echo '<small class="text-muted">Showing ' . ($start_index + 1) . ' to ' . min($start_index + $limit, $total_users) . ' of ' . $total_users . ' users</small>';
        echo '</div>';
    }
}
?>
<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician - Manage Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="../css/style.css">
	<style>
.btn-checkout-custom {
    width: 65px !important;  /* Kecikkan sikit dari 85px */
    height: 65px !important;
    border-radius: 50% !important;
    font-size: 10px !important;
    line-height: 1.1;
    z-index: 10;
}

/* Biar dia duduk jauh sikit dari teks NO: 1 */
.d-flex.align-items-center.justify-content-between {
    padding-top: 15px !important;
    padding-bottom: 15px !important;
}
.btn-checkout-custom i {
    font-size: 20px !important; /* Icon besar sikit dari tulisan */
}

.btn-checkout-custom:hover {
    transform: scale(1.05) !important;
    box-shadow: 0 6px 20px rgba(13, 110, 253, 0.4) !important;
}

/* Responsif untuk Mobile */
@media (max-width: 768px) {
    .btn-checkout-custom {
        width: 70px !important;
        height: 70px !important;
    }
    .btn-checkout-custom i {
        font-size: 16px !important;
    }
    .btn-checkout-custom span {
        font-size: 9px !important;
    }
}
	.main-content {
    /* Gunakan min-height supaya background sentiasa sekurang-kurangnya setinggi skrin */
    min-height: 100vh; 
    
    /* Warna latar belakang utama */
    background-color: #f1f5f9; 
    
    /* Pattern texture */
    background-image: url('https://www.transparenttextures.com/patterns/cubes.png');
    
    /* PENTING: Supaya pattern tidak bergerak bila kita scroll (nampak lebih kemas) */
    background-attachment: fixed;
    
    /* Tambah padding supaya content tidak rapat sangat dengan tepi skrin */
    padding: 1rem 0.5rem;
    
    /* Memastikan background meliputi seluruh ruang */
    background-repeat: repeat;
}

	/* Design untuk Nav Link (Tab) */
.nav-pills .nav-link {
    color: #64748b;
    font-weight: 500;
    padding: 10px 20px;
    border: none !important;
    transition: all 0.3s ease;
}

/* Design untuk Nav Link yang Tengah Aktif */
.nav-pills .nav-link.active {
    background-color: white !important;
    color: #1e293b !important;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

/* Badge dalam tab */
.badge {
    font-size: 0.75rem;
    padding: 0.4em 0.7em;
}

/* Input search background */
.bg-light {
    background-color: #F4F7FE !important;
}
.modern-badge.badge-expired {
    background: #f1f5f9; /* Kelabu lembut */
    color: #64748b;
    border: 1px solid #e2e8f0;
}

.bg-expired-soft {
    background: #f8fafc;
    border-left: 5px solid #94a3b8 !important;
}

/* --- MOBILE BOTTOM NAV (THEMED DARK) --- */
@media (max-width: 991px) {
    body {
        padding-bottom: 80px; /* Ruang supaya content tak kena sorok dek bar */
    }

    .mobile-bottom-nav {
        display: flex !important;
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        /* TUKAR: Warna gelap macam sidebar laptop */
        background: #1e293b !important; 
        border-top: 1px solid rgba(255, 255, 255, 0.1); 
        z-index: 10000;
        justify-content: space-around;
        padding: 12px 0;
        box-shadow: 0 -8px 25px rgba(0,0,0,0.2);
    }

    .mobile-bottom-nav a {
        /* Warna icon & teks masa tak aktif */
        color: #94a3b8 !important; 
        text-decoration: none !important;
        text-align: center;
        font-size: 11px;
        font-weight: 600;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        flex: 1;
        transition: 0.3s;
    }

    /* Warna Cyan bila menu aktif/tekan */
    .mobile-bottom-nav a.active {
        color: #06b6d4 !important;
    }

    .mobile-bottom-nav a i {
        font-size: 20px;
    }

    /* Tambah sikit effect bila user touch */
    .mobile-bottom-nav a:active {
        transform: scale(0.9);
        opacity: 0.8;
    }
}

@media (max-width: 768px) {
    /* Sembunyikan Header Table yang serabut tu */
    thead {
        display: none;
    }
    
    /* Paksa setiap row jadi card */
    tr.user-row {
        display: block;
        margin-bottom: 15px;
        border: 1px solid #edf2f7 !important;
        border-radius: 12px;
        padding: 10px;
        background: #ffffff;
    }
    
    td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border: none !important;
        padding: 8px 5px !important;
        width: 100% !important;
        text-align: left !important;
    }

    /* Tambah Label sebelum data (Contoh: Item: Laptop) */
    td::before {
        content: attr(data-label); /* Kau kena tambah data-label kat TD nanti */
        font-weight: 700;
        color: #64748b;
        font-size: 11px;
        text-transform: uppercase;
    }
}

    /* Sembunyikan kalau kat PC */
    @media (min-width: 992px) {
        .mobile-bottom-nav {
            display: none !important;
        }
    }
	
	.toast-container {
    z-index: 1060; /* Pastikan dia duduk atas sekali dari modal/sidebar */
}

.toast {
    border-radius: 12px;
    overflow: hidden;
    animation: slideInRight 0.5s ease-out;
}

@keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.toast-header {
    border-bottom: none;
}

/* Container untuk Search & Filter */
.search-filter-container {
    background: white;
    padding: 15px;
    border-radius: 15px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    margin-bottom: 20px;
}

/* Input Search bagi nampak soft */
#completedSearchInput, #userSearchInput {
    border: 1px solid #e2e8f0 !important;
    background-color: #f8fafc !important;
    padding: 10px 15px;
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div> 
<div class="sidebar" id="admin-sidebar">
    <div> <div class="sidebar-header">
            <div class="logo-icon"><i class="fa-solid fa-wrench"></i></div>
            <div class="logo-text"><strong>UniKL Technician</strong><br><span style="font-size: 0.85rem; color: #64748b;">System Support</span></div>
        </div>
        
        <div class="sidebar-nav"> <a href="dashboard_tech.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
<a href="check_out.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'check_out.php') ? 'active' : '' ?>">
    <i class="fa-solid fa-dolly"></i>
    <span>Manage Requests</span>
    
    <?php 
    // Kita panggil function tu dan simpan dalam variable sekejap
    $jumlah_pending = get_pending_count($conn); 
    
    if ($jumlah_pending > 0): 
    ?>
        <span class="badge"><?= $jumlah_pending ?></span>
    <?php endif; ?>
</a>         <a href="manageItem_tech.php"><i class="fa-solid fa-box-archive"></i> Manage Items</a>
            <a href="report.php"><i class="fa-solid fa-chart-line"></i> Report</a>
        </div>
    </div>
    
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-link"><i class="fa-solid fa-sign-out-alt"></i> Logout</a> 
    </div>
</div>

<div class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <button id="sidebarToggle" class="btn d-none">
                <i class="fas fa-bars"></i>
            </button>
            <h3 class="mb-0">Manage Requests</h3>
        </div>

        <div class="topbar-right">
            <a href="profile_tech.php" class="user-pill text-decoration-none d-flex align-items-center">
                <div class="text-end me-2 d-none d-md-block">
                    <div class="user-name" style="text-transform: capitalize; font-weight: 600; color: #1e293b; line-height: 1.2;">
                        <?= htmlspecialchars($displayName) ?>
                    </div>
                    <small class="text-muted" style="font-size: 0.75rem;">Technician</small>
                </div>
                <div class="profile-avatar">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($displayName) ?>&background=06b6d4&color=fff" class="rounded-circle" width="35" alt="Profile">
                </div>
            </a>
        </div>
    </div>

       
    <div class="container-fluid">
         <div class="card shadow-sm border-0 mb-3" style="padding: 15px 25px;">
            <div class="card-body p-0">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <h6 class="mb-0 text-dark fw-bold">
                            <i class="fa-solid fa-filter me-2 text-primary"></i>Filter by Apply Date
                        </h6>
                    </div>
                    <div class="col-md-8">
                       <form method="GET" action="check_out.php" class="d-flex justify-content-md-end align-items-center gap-2">
    <label for="applyDate" class="small text-muted mb-0 d-none d-lg-block">Select Date:</label>
    <input type="date" id="applyDate" name="filter_date" value="<?= htmlspecialchars($filter_date ?? '') ?>" class="form-control form-control-sm" style="width: 180px; border-radius: 8px;">
    <button type="submit" class="btn btn-primary btn-sm px-3" style="border-radius: 8px;">Filter</button>
    <a href="check_out.php" class="btn btn-outline-secondary btn-sm px-3" style="border-radius: 8px;">Reset</a>
</form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
           <div class="card border-0 shadow-sm p-4" style="border-radius: 16px;">
    <div class="d-flex align-items-center gap-2 mb-4">
        <i class="fa-solid fa-box text-primary"></i>
        <h6 class="fw-bold mb-0">Reservation Actions</h6>
    </div>

    <ul class="nav nav-pills mb-4 p-1" id="myTab" role="tablist" style="background: #F8F9FC; border-radius: 12px; display: inline-flex; border: none; width: 100%;">
        <li class="nav-item flex-fill" role="presentation">
            <button class="nav-link active w-100 d-flex align-items-center justify-content-center gap-2" id="pending-tab" data-bs-toggle="pill" data-bs-target="#pending-tab-pane" type="button" style="border-radius: 10px;">
                <i class="fa-regular fa-clock"></i> New Requests 
                <span class="badge rounded-pill bg-warning text-dark"><?= count($pending_requests) ?></span>
            </button>
        </li>
        <li class="nav-item flex-fill" role="presentation">
            <button class="nav-link w-100 d-flex align-items-center justify-content-center gap-2" id="approved-tab" data-bs-toggle="pill" data-bs-target="#approved-tab-pane" type="button" style="border-radius: 10px;">
                <i class="fa-regular fa-circle-check"></i> To Be Collected 
                <span class="badge rounded-pill bg-success"><?= count($approved_requests) ?></span>
            </button>
        </li>
        <li class="nav-item flex-fill" role="presentation">
            <button class="nav-link w-100 d-flex align-items-center justify-content-center gap-2" id="onloan-tab" data-bs-toggle="pill" data-bs-target="#onloan-tab-pane" type="button" style="border-radius: 10px;">
                <i class="fa-solid fa-boxes-stacked"></i> On Loan 
                <span class="badge rounded-pill bg-primary"><?= count($on_loan_requests) ?></span>
            </button>
        </li>
        <li class="nav-item flex-fill" role="presentation">
            <button class="nav-link w-100 d-flex align-items-center justify-content-center gap-2" id="completed-tab" data-bs-toggle="pill" data-bs-target="#completed-tab-pane" type="button" style="border-radius: 10px;">
                <i class="fa-solid fa-clock-rotate-left"></i> Loan Records
            </button>
        </li>
    </ul>

    <div class="tab-content" id="myTabContent">
        <div class="tab-pane fade show active" id="pending-tab-pane" role="tabpanel"><?php create_request_table($pending_requests); ?></div>
        <div class="tab-pane fade" id="approved-tab-pane" role="tabpanel"><?php create_request_table($approved_requests); ?></div>
        <div class="tab-pane fade" id="onloan-tab-pane" role="tabpanel"><?php create_request_table($on_loan_requests); ?></div>
       <div class="tab-pane fade" id="completed-tab-pane" role="tabpanel">
    <div class="row mb-3 mt-3">
        <div class="col-md-12 d-flex justify-content-end">
            <div class="input-group input-group-sm" style="width: 320px;">
                <span class="input-group-text bg-white border-end-0" style="border-radius: 10px 0 0 10px;">
                    <i class="fa-solid fa-magnifying-glass text-muted"></i>
                </span>
                <input type="text" id="completedSearchInput" class="form-control border-start-0 ps-0" 
                       placeholder="Search name or item in records..." 
                       style="border-radius: 0; box-shadow: none; border-color: #dee2e6;">
                <button class="btn btn-primary btn-sm px-3" type="button" id="btnSearchCompleted" style="border-radius: 0 10px 10px 0;">
                    Search
                </button>
            </div>
        </div>
    </div>

    <?php create_request_table($completed_requests); ?>
</div>
		
    </div>
</div>
        </div>
    </div>
</div>

<div class="modal fade" id="approveDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Approve Reservation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><strong>User:</strong> <span id="userName"></span></p>
                <p><strong>Item:</strong> <span id="itemName"></span></p>
                <p><strong>Requested Qty:</strong> <span id="requestedQtyText"></span></p> <hr>
                <div class="mb-3">
                    <label class="form-label fw-bold">Quantity to Approve:</label>
                    <input type="number" class="form-control" id="approve_actual_qty">
                </div>

                <div id="partialRejectionReasonContainer" style="display: none;" class="mb-3">
                    <label for="partial_reject_reason" class="form-label fw-bold text-danger">Reason for Partial Approval:</label>
                    <textarea class="form-control" id="partial_reject_reason" rows="2" placeholder="Why are you giving less than requested?"></textarea>
                    <small class="text-muted">Min. 5 characters required.</small>
                </div>

                <div id="assetListContainer"></div>
                
                <input type="hidden" id="approve_reservation_item_id">
                <input type="hidden" id="approve_original_qty"> 
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmApproveBtn" disabled>Approve</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger fw-bold"><i class="fa-solid fa-circle-xmark me-2"></i>Reject Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="reject_reason" class="form-label fw-bold">Reason for Rejection:</label>
                    <textarea class="form-control" id="reject_reason" rows="3" placeholder="Explain why the request is rejected..."></textarea>
                </div>
                <input type="hidden" id="reject_reservation_item_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="confirmRejectBtn">Confirm Rejection</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="checkInModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Return Item (Check-In)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="checkInModalBody">
                <p class="text-center">Loading assets...</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmCheckInBtn">Confirm Return</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="auditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-light">
                <h5 class="modal-title"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Reservation History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="timeline-audit">
                    <div class="d-flex mb-3">
                        <div class="me-3 text-center" style="width: 30px;">
                            <i class="fa-solid fa-check-circle text-success fs-4"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">Approved By</h6>
                            <p class="text-muted small mb-0" id="audit_approved_name">Loading...</p>
                        </div>
                    </div>
                    <div class="d-flex mb-3">
                        <div class="me-3 text-center" style="width: 30px;">
                            <i class="fa-solid fa-truck-ramp-box text-primary fs-4"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">Checked Out By</h6>
                            <p class="text-muted small mb-0" id="audit_checked_out_name">Loading...</p>
                        </div>
                    </div>
                    <div class="d-flex">
                        <div class="me-3 text-center" style="width: 30px;">
                            <i class="fa-solid fa-box-open text-warning fs-4"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">Returned / Checked In By</h6>
                            <p class="text-muted small mb-0" id="audit_checked_in_name">Loading...</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl)
    })
	
    // 1. Logik Auto-Search dari Dashboard
    const urlParams = new URLSearchParams(window.location.search);
    const searchId = urlParams.get('search_id');

    if (searchId) {
        // Guna setTimeout sedikit lebih lama (600ms-800ms) untuk pastikan DataTable sudah 'init'
        setTimeout(() => {
            // Cuba cari DataTable yang sedia ada
            let table;
            if ($.fn.dataTable.isDataTable('.table')) {
                table = $('.table').DataTable();
            }

            if (table) {
                // Gunakan fungsi search() bawaan DataTables
                table.search(searchId).draw();
                
                // (Opsional) Highlight kotak search
                const searchInput = document.querySelector('.dataTables_filter input');
                if (searchInput) {
                    searchInput.style.backgroundColor = "#e0f2fe"; 
                    searchInput.style.border = "2px solid #06b6d4";
                    searchInput.focus();
                }
            } else {
                // Fallback: Jika DataTable belum sedia, guna cara manual kau tadi
                const searchInput = document.querySelector('input[type="search"]');
                if (searchInput) {
                    searchInput.value = searchId;
                    searchInput.dispatchEvent(new Event('input', { bubbles: true }));
                    searchInput.focus();
                }
            }
        }, 800); 
    }
    // 2. Aktifkan semua tooltip (Bootstrap)
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

// --- 3. BARU: LOGIK PAGINATION UNTUK NAMA USER ---
    const itemsPerPage = 10; 
    const userRows = document.querySelectorAll('.user-row'); // Cari class user-row dalam PHP tadi
    const totalPages = Math.ceil(userRows.length / itemsPerPage);
    const accordionContainer = document.querySelector('.tab-content'); // Tempat letak butang page

    function showPage(page) {
        userRows.forEach((row, index) => {
            row.style.display = 'none'; // Sorok semua
            if (index >= (page - 1) * itemsPerPage && index < page * itemsPerPage) {
                row.style.display = 'block'; // Tunjuk 5 sahaja
            }
        });
    }

    if (totalPages > 1) {
        let paginationHtml = '<div class="d-flex justify-content-center mt-3"><nav><ul class="pagination">';
        for (let i = 1; i <= totalPages; i++) {
            paginationHtml += `<li class="page-item"><button class="page-link btn-page" data-page="${i}">${i}</button></li>`;
        }
        paginationHtml += '</ul></nav></div>';
        accordionContainer.insertAdjacentHTML('afterend', paginationHtml);

        // Event listener untuk butang page
        document.querySelectorAll('.btn-page').forEach(btn => {
            btn.addEventListener('click', function() {
                showPage(this.getAttribute('data-page'));
                window.scrollTo(0, 0); // Scroll ke atas balik bila tukar page
            });
        });
    }

    showPage(1); // Jalankan page 1 masa mula-mula load

}); // Penutup DOMContentLoaded

// 3. Data dari PHP
const availableAssets = <?php echo $availableAssets_json; ?>;

$(document).ready(function() {

// --- LOGIK KEKALKAN TAB SELEPAS RELOAD ---
    // Simpan tab aktif ke dalam localStorage bila user klik tab
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        localStorage.setItem('activeTab', $(e.target).attr('data-bs-target'));
    });

    // Baca balik tab yang disimpan bila page reload
    var activeTab = localStorage.getItem('activeTab');
    if (activeTab) {
        var tabTrigger = new bootstrap.Tab($('button[data-bs-target="' + activeTab + '"]')[0]);
        tabTrigger.show();
    }
    
$('#userSearchInput').on('keyup', function() {
    var value = $(this).val().toLowerCase();
    var $activeAccordionItems = $('.tab-pane.active .user-row');

    if (value === "") {
        // Kalau kotak search kosong, jalankan balik pagination page 1
        showPage(1);
        $('.pagination').show(); 
    } else {
        // Kalau user tengah menaip, sorok pagination & tapis semua
        $('.pagination').hide(); 
        $activeAccordionItems.each(function() {
            var textToSearch = $(this).find('.accordion-header').text().toLowerCase();
            $(this).toggle(textToSearch.indexOf(value) > -1);
        });
    }
    
    // PENTING: Refresh popover supaya butang 'Details' berfungsi pada hasil search
    refreshBootstrapComponents();
});

    // Reset kotak search bila user tukar tab (New Request -> On Loan etc)
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        $('#userSearchInput').val(''); // Kosongkan input
        $('.accordion-item').show();   // Tunjukkan balik semua item
    });
    // ==========================================

$.ajaxSetup({
    error: function(xhr, status, error) {
        Swal.fire({
            icon: 'error',
            title: 'System Error',
            text: 'Something went wrong! Please contact your admin or check the console.',
            footer: 'Error Details: ' + error
        });
        // Enable-kan balik semua button yang sangkut "Processing..."
        $('.btn').prop('disabled', false); 
    }
});

    function handleBulkAction(action, reserveId, title, text, confirmText, confirmColor) {
        
        Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: confirmColor,
            cancelButtonColor: '#6c757d',
            confirmButtonText: confirmText
        }).then((result) => {
            if (result.isConfirmed) {
                var $button = $(`[data-reserve-id="${reserveId}"]`); 
                var originalText = $button.html(); 
                
                $button.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Processing...');

                $.ajax({
                    url: 'checkout_action.php', 
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: action,
                        reserve_id: reserveId
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Success!', response.message, 'success').then(() => {
                                location.reload(); 
                            });
                        } else {
                            Swal.fire('Error', 'Gagal: ' + (response.message || 'Unknown error occurred.'), 'error');
                            $button.prop('disabled', false).html(originalText); 
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Network Error', 'A network error occurred or server did 						respond correctly.', 'error');
                        $button.prop('disabled', false).html(originalText);
                    }
                });
            }
        });
    }

    
    $(document).on('click', '.checkout-all-btn', function() {
        var reserveId = $(this).data('reserve-id');
        handleBulkAction(
            'checkout_all_items', 
            reserveId,
            'Confirm  Check-Out?',
            "All items approved in Booking ID: " + reserveId + " will be marked as Checked Out (On Loan).",
            'Yes, Check Out All!',
            '#3b82f6' 
        );
    });

// Panggil semula komponen Bootstrap selepas filter
function refreshBootstrapComponents() {
    // 1. Hidupkan balik Accordion
    var accordions = document.querySelectorAll('.accordion-collapse');
    accordions.forEach(acc => new bootstrap.Collapse(acc, { toggle: false }));

    // 2. Hidupkan balik Popover (Details)
    var popovers = document.querySelectorAll('[data-bs-toggle="popover"]');
    popovers.forEach(pop => new bootstrap.Popover(pop));
}

// Letakkan ini di luar atau di dalam $(document).ready()
function openAuditModal(id) {
    // 1. Dapatkan row berdasarkan ID
    const row = document.getElementById('row-' + id);
    
    if (row) {
        // 2. Ambil data admin yang disimpan dalam data-attributes (Pastikan PHP kau output data ni)
        const approvedBy = row.getAttribute('data-approved-by') || 'Not available';
        const checkedOutBy = row.getAttribute('data-checked-out-by') || 'Not available';
        const checkedInBy = row.getAttribute('data-checked-in-by') || 'Not available';
        
        // 3. Masukkan ke dalam modal history
        $('#audit_approved_name').text(approvedBy);
        $('#audit_checked_out_name').text(checkedOutBy);
        $('#audit_checked_in_name').text(checkedInBy);

        // 4. Paparkan modal
        const auditModal = new bootstrap.Modal(document.getElementById('auditModal'));
        auditModal.show();
    } else {
        Swal.fire('Error', 'Audit data not found for this row.', 'error');
    }
}
// Jadikan fungsi ni global supaya boleh dipanggil oleh onclick dalam HTML
window.openAuditModal = openAuditModal;



function checkOutItem(id) {
    const $btn = $(`[onclick="checkOutItem(${id})"]`); 
    const originalText = $btn.html();

    Swal.fire({
        title: 'Confirm Check-Out?', 
        text: "Are you sure you want to mark this item (and all its assigned assets) as Checked Out?", 
        icon: 'question',
        showCancelButton: true, 
        confirmButtonColor: '#3b82f6', 
        cancelButtonColor: '#6c757d', 
        confirmButtonText: 'Yes, check it out!'
    }).then((result) => {
        if (result.isConfirmed) {
            
            
            Swal.fire({ 
                title: 'Preparing Assets...', 
                text: 'Fetching assets list from server.', 
                allowOutsideClick: false, 
                didOpen: () => { Swal.showLoading(); }
            });
            $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Checking Out...');
            
            
            $.ajax({
                url: 'checkout_action.php',
                method: 'GET',
                dataType: 'json',
                data: { action: 'get_assets_for_checkout', reservation_item_id: id },
                success: function(assets) {
                    
                    if (!assets || assets.length === 0) {
                        Swal.fire('Error', 'No assets found in \'Reserved\' status for this item. Check status in DB.', 'error');
                        $btn.prop('disabled', false).html(originalText);
                        return;
                    }

                    const asset_ids = assets.map(a => a.asset_id);
                    
                    
                    $.ajax({
                        url: 'checkout_action.php',
                        method: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'checkout_multi',
                            reservation_item_id: id,
                            asset_ids: JSON.stringify(asset_ids) 
                        },
                        success: function(data) {
                            Swal.close();
                            Swal.fire({ 
                                title: 'Checked Out!', 
                                text: data.message, 
                                icon: 'success', 
                                timer: 2000, 
                                showConfirmButton: false 
                            }).then(() => location.reload());
                        },
                        error: function(xhr) {
                            Swal.close();
                            let realErrorMessage = xhr.responseText;
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                realErrorMessage = xhr.responseJSON.message;
                            }
                            
                            Swal.fire('Error', 'An error occurred during check-out. Details: ' + realErrorMessage, 'error');
                            $btn.prop('disabled', false).html(originalText);
                        }
                    });
                },
                error: function(xhr) {
                    Swal.close();
                    let realErrorMessage = xhr.responseText;
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        realErrorMessage = xhr.responseJSON.message;
                    }
                    Swal.fire('Error', 'Failed to fetch asset list. Details: ' + realErrorMessage, 'error');
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        }
    });
}

window.checkOutItem = checkOutItem;


function openApproveModal(id) {
    const row = document.getElementById('row-' + id);
    if (!row) return;

    const originalQty = parseInt(row.dataset.qty);
    const itemId = row.dataset.itemId;
    const reservationReason = row.dataset.reason; 
    
    const assets = availableAssets[itemId] || [];
    const availableCount = assets.length;

    const $approveBtn = $('#confirmApproveBtn');
    const $assetContainer = $('#assetListContainer');
    const $approveQtyInput = $('#approve_actual_qty');
    const $partialRejectContainer = $('#partialRejectionReasonContainer');
    const $partialRejectReason = $('#partial_reject_reason');
    
    $('#userName').text(row.dataset.userName);
    $('#itemName').text(row.dataset.itemname);
    $('#userPhone').text(row.dataset.phone);
    
    $('#reservationReasonText').text(reservationReason || 'N/A'); 
    $('#approve_reservation_item_id').val(id);
    $('#requestedQtyText').text(originalQty);
    $('#approve_original_qty').val(originalQty);
    
    let qtyToApprove = (availableCount >= originalQty) ? originalQty : availableCount;
    $approveQtyInput.val(qtyToApprove);
    $approveQtyInput.attr('max', availableCount); 

    function updatePartialReasonDisplay(currentQty) {
        if (currentQty < originalQty) {
            $partialRejectContainer.slideDown();
            $partialRejectReason.attr('required', true); 
        } else {
            $partialRejectContainer.slideUp();
            $partialRejectReason.attr('required', false).val(''); 
        }
    }

function validateApproveButton() {
    const currentQtyToApprove = parseInt($approveQtyInput.val());
    const checkedCount = $('.asset-checkbox:checked').length;
    const originalQty = parseInt($('#approve_original_qty').val());
    const reasonText = $partialRejectReason.val().trim();
    
    let isReasonValid = true;
    
    // Syarat 1: Kalau bagi kurang dari yang diminta, WAJIB ada alasan (min 5 huruf)
    if (currentQtyToApprove < originalQty) {
        isReasonValid = reasonText.length >= 5;
    }

    // Syarat 2: Bilangan yang di-tick MESTI SAMA dengan nombor dalam input Quantity
    const isQtyMatch = (currentQtyToApprove > 0 && checkedCount === currentQtyToApprove);

    // Kalau semua syarat lepas, baru hidupkan butang
    if (isQtyMatch && isReasonValid) {
        $approveBtn.prop('disabled', false);
    } else {
        $approveBtn.prop('disabled', true);
    }
}

function buildAssetCheckboxes() {
    const currentQtyToApprove = parseInt($approveQtyInput.val());

    if (isNaN(currentQtyToApprove) || currentQtyToApprove < 0) {
        $assetContainer.html('<div class="alert alert-warning">Please enter a valid quantity.</div>');
    } else if (assets.length === 0) {
        $assetContainer.html('<div class="alert alert-danger">No assets available for this item!</div>');
    } else {
        let html = `
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-sm table-hover align-middle">
                    <thead class="table-light sticky-top">
                        <tr style="font-size: 0.75rem;" class="text-uppercase text-muted">
                            <th class="text-center" style="width: 40px;">Sel</th>
                            <th>Asset Details</th>
                        </tr>
                    </thead>
                    <tbody>`;

        assets.forEach(a => {
            // --- LOGIC POTONG NAMA MODEL (JS VERSION) ---
            let fullModel = a.model || '';
            let shortModel = fullModel;

            // Jika ada (MIC 2), kita cari kedudukan '(' yang pertama
            // Tapi kalau nak ambil bermula dari (MIC 2) dan seterusnya:
            let firstBracket = fullModel.indexOf('('); 
            if (firstBracket !== -1) {
                shortModel = fullModel.substring(firstBracket); 
                // Hasil: "(Digital UHF...)(MIC 2)(AL FARABI)"
                
                // JIKA AWAK NAK BUANG "Digital UHF" tu dan ambil (MIC 2) sahaja:
                // Kita cari perkataan "(MIC"
                let micIndex = fullModel.indexOf('(MIC');
                if (micIndex !== -1) {
                    shortModel = fullModel.substring(micIndex);
                }
            }

            let serialNum = a.serial_number ? a.serial_number : 'No S/N';

            html += `
                <tr style="font-size: 0.85rem;">
                    <td class="text-center">
                        <input class="form-check-input asset-checkbox" type="checkbox" value="${a.asset_id}" id="asset-${a.asset_id}">
                    </td>
                    <td>
                        <label for="asset-${a.asset_id}" class="d-block" style="cursor: pointer;">
                            <span class="fw-bold text-primary">${a.asset_code}</span> 
                            <span class="badge bg-light text-dark border ms-1" style="font-size: 0.7rem;">SN: ${serialNum}</span>
                            <br>
                            <small class="text-muted">${a.brand || ''} <span class="text-dark fw-bold">${shortModel}</span></small>
                        </label>
                    </td>
                </tr>`;
        });

        html += `</tbody></table></div>`;
        $assetContainer.html(html);

        $('.asset-checkbox').on('change', function() {
            validateApproveButton();
        });
    }
    validateApproveButton();
}
    $approveQtyInput.off('change keyup');
    $assetContainer.off('change.assetcheck');
    $partialRejectReason.off('change keyup'); 

    $approveQtyInput.on('change keyup', function() {
        buildAssetCheckboxes();
        updatePartialReasonDisplay(parseInt($(this).val()));
    }); 

    $partialRejectReason.on('change keyup', validateApproveButton); 
    $assetContainer.on('change.assetcheck', '.asset-checkbox', validateApproveButton); 

    buildAssetCheckboxes();
    updatePartialReasonDisplay(qtyToApprove);

    new bootstrap.Modal('#approveDetailsModal').show();
}    
    window.openApproveModal = openApproveModal;


    
    $('#confirmApproveBtn').on('click', function() {
        const reservation_item_id = $('#approve_reservation_item_id').val();
        const selected = $('.asset-checkbox:checked').map(function() { return $(this).val(); }).get();
        
        const actualApprovedQty = parseInt($('#approve_actual_qty').val());
        const originalQty = parseInt($('#approve_original_qty').val());
        const reasonForPartialReject = $('#partial_reject_reason').val().trim();

        
        if (actualApprovedQty > 0 && selected.length !== actualApprovedQty) {
            Swal.fire('Selection Error', `You must select exactly ${actualApprovedQty} asset(s).`, 'warning');
            return;
        }
        
        const btn = $(this).prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Approving...');
        
        $.ajax({
            url: 'checkout_action.php', 
            method: 'POST',
            data: { 
                action: 'approve', 
                reservation_item_id: reservation_item_id, 
                selectedAssets: selected,
                approved_quantity: actualApprovedQty,
                partial_reason: (actualApprovedQty < originalQty) ? reasonForPartialReject : '' 
            },
            dataType: 'json',
            success: (data) => {
                Swal.fire({ title: 'Success!', text: data.message || 'Approved!', icon: 'success', timer: 1500, showConfirmButton: false })
                .then(() => location.reload());
            },
            error: (xhr) => {
                let realErrorMessage = xhr.responseText;
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    realErrorMessage = xhr.responseJSON.message;
                }
                Swal.fire('AJAX Request Failed!', 'Details: ' + realErrorMessage, 'error');
                btn.prop('disabled', false).text('Approve Request');
            }
        });
    });


    
function openRejectModal(id) {
    // 1. Pastikan ID dimasukkan ke hidden input dalam modal
    const inputId = document.getElementById('reject_reservation_item_id');
    if (inputId) {
        inputId.value = id;
    }

    // 2. Kosongkan ruangan alasan
    const reasonInput = document.getElementById('reject_reason');
    if (reasonInput) {
        reasonInput.value = '';
    }

    // 3. Cara paling selamat untuk buka modal di Bootstrap 5
    const modalElement = document.getElementById('rejectModal');
    if (modalElement) {
        const modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);
        modalInstance.show();
    } else {
        console.error("Error: Element #rejectModal tidak dijumpai dalam HTML!");
    }
}
window.openRejectModal = openRejectModal;
    
    $('#confirmRejectBtn').on('click', function() {
        const reservation_item_id = $('#reject_reservation_item_id').val();
        const reason = $('#reject_reason').val().trim();
        if (!reason) {
            Swal.fire('Input Required', 'Please provide a reason for rejection.', 'warning');
            $('#reject_reason').focus();
            return;
        }
        const btn = $(this).prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Rejecting...');
        $.post('checkout_action.php', { action: 'reject', reservation_item_id, reason }, (data) => {
            Swal.fire({ title: 'Rejected!', text: data.message || 'Request rejected.', icon: 'success', timer: 1500, showConfirmButton: false })
            .then(() => location.reload());
        }, 'json').fail((xhr) => {
            let realErrorMessage = xhr.responseText;
            if (xhr.responseJSON && xhr.responseJSON.message) {
                realErrorMessage = xhr.responseJSON.message;
            }
            Swal.fire('Error', 'An error occurred during rejection. Details: ' + realErrorMessage, 'error');
            btn.prop('disabled', false).text('Confirm Rejection');
        });
    });


    
    function checkInItem(id) {
        const row = document.getElementById('row-' + id);
        if (!row) return;

        const $modal = $('#checkInModal');
        const $modalBody = $('#checkInModalBody');
        const $confirmBtn = $('#confirmCheckInBtn');

        $modal.find('.modal-title').text('Check In: ' + row.dataset.itemname);
        $modalBody.html('<div class="text-center p-4"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><br>Loading assets...</div>');
        $confirmBtn.prop('disabled', true);

        const checkInModal = new bootstrap.Modal($modal[0]);
        checkInModal.show();

        
        $.ajax({
            url: 'checkout_action.php',
            method: 'GET',
            data: {
                action: 'get_assets_for_checkin',
                reservation_item_id: id
            },
            dataType: 'json',
            success: function(assets) {
                if (!assets || assets.length === 0) {
                    $modalBody.html('<div class="alert alert-warning mb-0">No assets found in \'Checked Out\' status for this item. It might have already been returned.</div>');
                    return;
                }

                
                let html = `<p>Please set the condition for each returned asset (${assets.length} unit(s)).</p>`;
                html += `<form id="checkInForm">`;
                html += `<input type="hidden" id="checkin_reservation_item_id" value="${id}">`; 

                assets.forEach(asset => {
                    const asset_id = asset.asset_id;
                    const unique_radio_name = `condition_${asset_id}`;
                    
                    html += `
                        <div class="card mb-3 p-3 checkin-asset-card" data-asset-id="${asset_id}">
                            <h6 class="mb-2 fw-bold text-primary">${asset.asset_code}</h6>
                            <div class="mb-2">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="${unique_radio_name}" id="condition_good_${asset_id}" value="Good" required checked>
                                    <label class="form-check-label" for="condition_good_${asset_id}">Good</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="${unique_radio_name}" id="condition_damaged_${asset_id}" value="Maintenance">
                                    <label class="form-check-label text-danger" for="condition_damaged_${asset_id}">Damaged</label>
                                </div>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input asset-status" type="radio" name="${unique_radio_name}" id="status_missing_${asset_id}" value="Not_Returned_Yet" required>
                                <label class="form-check-label text-danger" for="status_missing_${asset_id}"><strong>Not Returned Yet (Left Behind)</strong></label>
                            </div>
                    
                            <div class="remarks-container" style="display: none;">
                                <label for="remarks_${asset_id}" class="form-label small mb-1">Remarks (Required if damaged):</label>
                                <input type="text" class="form-control form-control-sm" id="remarks_${asset_id}" placeholder="e.g., Screen cracked, cable missing...">
                            </div>
                        </div>
                    `;
                });
                
                html += '</form>';
                $modalBody.html(html);
                $confirmBtn.prop('disabled', false); 
                
                $modalBody.find('input[type="radio"]:checked').trigger('change');
            },
            error: function(xhr) {
                let realErrorMessage = xhr.responseText;
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    realErrorMessage = xhr.responseJSON.message;
                }
                $modalBody.html('<div class="alert alert-danger mb-0">Error loading asset list: ' + realErrorMessage + '</div>');
            }
        });
    }
    
    window.checkInItem = checkInItem;

    
    $('#checkInModalBody').on('change', 'input[type="radio"]', function() {
        const $card = $(this).closest('.checkin-asset-card');
        const $remarksContainer = $card.find('.remarks-container');
        const $remarksLabel = $remarksContainer.find('label');
        const $remarksInput = $card.find('input[id^="remarks_"]');
        
        $remarksInput.prop('required', false);
        $remarksInput.val('');
        
        const selectedValue = $(this).val();

        if (selectedValue === 'Maintenance') {
            $remarksLabel.text('Remarks (Required if damaged):'); 
            $remarksInput.prop('required', true);
            $remarksContainer.slideDown();
            
        } else if (selectedValue === 'Not_Returned_Yet') {
            $remarksLabel.text('Remarks (Optional - e.g., Reason left behind):'); 
            $remarksContainer.slideDown(); 
            
        } else { 
            $remarksLabel.text('Remarks (Optional):'); 
            $remarksContainer.slideUp();
        }
    });

    
$('#confirmCheckInBtn').on('click', function() {
    const reservation_item_id = $('#checkin_reservation_item_id').val();
    let asset_conditions = [];
    let isValid = true;

    $('.checkin-asset-card').each(function() {
        const $card = $(this);
        const asset_id = $card.data('asset-id');
        // Ambil value radio (Good / Maintenance / Not_Returned_Yet)
        const condition = $card.find(`input[name="condition_${asset_id}"]:checked`).val();
        // Ambil value remarks
        const remarks = $card.find(`#remarks_${asset_id}`).val().trim();

        if (!condition) {
            isValid = false;
            Swal.fire('Input Required', `Please select a condition for asset code.`, 'warning');
            return false; 
        }

        // MESTI guna 'Maintenance' sebab itu value dalam HTML radio kau
        if (condition === 'Maintenance' && !remarks) {
            isValid = false;
            Swal.fire('Input Required', `Please provide remarks for the damaged asset.`, 'warning');
            $card.find(`#remarks_${asset_id}`).focus();
            return false; 
        }
        
        // Masukkan ke dalam array untuk dihantar ke PHP
        asset_conditions.push({
            asset_id: asset_id,
            condition: condition,
            k: remarks // Ini akan dibaca sebagai $asset['remarks'] di PHP
        });
    });

    if (!isValid) return; 

    const $btn = $(this);
    Swal.fire({
        title: 'Confirm Check-In?',
        text: `Are you sure you want to return ${asset_conditions.length} asset(s)?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, Confirm!'
    }).then((result) => {
        if (result.isConfirmed) {
            $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Processing...');
            
            // HANTAR DATA KE PHP
            $.post('checkout_action.php', {
                action: 'checkin_multi', 
                reservation_item_id: reservation_item_id,
                asset_conditions: JSON.stringify(asset_conditions) 
            }, function(data) {
                if(data.success) {
                    Swal.fire('Success!', data.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                    $btn.prop('disabled', false).text('Confirm Check-In');
                }
            }, 'json');
        }
    });
});    
    
    
    
    
    const $sidebar = $('.sidebar');
    const $overlay = $('#sidebarOverlay');
    const $focusableElements = $sidebar.find('a, button');

    function setSidebarState(open) {
        if (open) {
            $sidebar.addClass('toggled'); 
            $overlay.addClass('active');
            $focusableElements.attr('tabindex', '0'); 
        } else {
            $sidebar.removeClass('toggled');
            $overlay.removeClass('active');
            $focusableElements.attr('tabindex', '-1'); 
        }
    }

    $('#sidebarToggle').on('click', function(e) {
        e.preventDefault();
        setSidebarState(!$sidebar.hasClass('toggled'));
    });

    $('#sidebarOverlay').on('click', function() {
        setSidebarState(false);
    });

    $(window).on('load resize', function() {
        if ($(window).width() >= 992) {
            $sidebar.removeClass('toggled'); 
            $overlay.removeClass('active'); 
            $focusableElements.attr('tabindex', '0'); 
        } else {
            $sidebar.removeClass('toggled');
            $overlay.removeClass('active');
            $focusableElements.attr('tabindex', '-1'); 
        }
    });
    
	
});

</script>

<nav class="mobile-bottom-nav">
    <a href="dashboard_tech.php" class="nav-item">
        <i class="fa-solid fa-table-columns"></i>
        <span>Dashboard</span>
    </a>
    <a href="check_out.php" class="nav-item active">
        <i class="fa-solid fa-dolly"></i>
        <span> Manage Requests</span>
    </a>
    <a href="manageItem_tech.php"><i class="fa-solid fa-box-archive"></i> Manage Items</a>
            <a href="report.php"><i class="fa-solid fa-chart-line"></i> Report</a>
    <a href="profile_tech.php" class="nav-item">
        <i class="fa-solid fa-user"></i>
        <span>Profile</span>
    </a>
</nav></body>

</html>