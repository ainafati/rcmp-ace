<?php
session_start();
include '../config.php'; 

// 1. Sekatan Akses & Pengesahan Sesi
if (!isset($_SESSION['person_id']) || $_SESSION['logged_in_role'] !== 'Technician') {
    session_unset();
    session_destroy();
    $_SESSION['error'] = "Access denied. Please log in as a Technician.";
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

// --- Fungsi Bantuan (Tiada perubahan) ---
function get_reservation_item_count($conn, $status) {
    $sql = "SELECT COUNT(id) AS count FROM reservation_items WHERE status = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        return 0;
    }
    $stmt->bind_param("s", $status);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result ? (int) $result['count'] : 0;
}

function fetch_asset_details($conn, $status_condition_sql) {
    $sql = "
        SELECT
            a.asset_id, a.asset_code, a.status, a.last_return_date,
            i.item_name,
            c.category_name
        FROM assets a
        LEFT JOIN item i ON a.item_id = i.item_id
        LEFT JOIN categories c ON i.category_id = c.category_id
        WHERE {$status_condition_sql}
        ORDER BY c.category_name, i.item_name, a.asset_code
    ";
    $result = $conn->query($sql);
    if ($result) {
        return $result->fetch_all(MYSQLI_ASSOC);
    } else {
        error_log("Error fetching asset details: " . $conn->error);
        return [];
    }
}

// --- Kiraan Ringkasan Data (Telah dibetulkan) ---
$pending_count_for_badge = get_reservation_item_count($conn, 'Pending'); 

$totalAssetsResult = $conn->query("SELECT COUNT(asset_id) AS total FROM assets WHERE status NOT IN ('Broken', 'Decommissioned', 'Missing')");
$totalAssetsRow = $totalAssetsResult->fetch_assoc();
$totalAssetsCount = isset($totalAssetsRow['total']) ? (int)$totalAssetsRow['total'] : 0;

$availableResult = $conn->query("SELECT COUNT(asset_id) AS total FROM assets WHERE status = 'Available'");
$availableRow = $availableResult->fetch_assoc();
$availableCount = isset($availableRow['total']) ? (int)$availableRow['total'] : 0;

$checkedOutResult = $conn->query("SELECT COUNT(asset_id) AS total FROM assets WHERE status = 'Checked Out'");
$checkedOutRow = $checkedOutResult->fetch_assoc();
$checkedOutCount = isset($checkedOutRow['total']) ? (int)$checkedOutRow['total'] : 0;

// *** PEMBETULAN SQL UTAMA: Tambah JOIN ke jadual reservations (r) ***
$overdueSql = "
    SELECT COUNT(DISTINCT ri.id) AS total 
    FROM reservation_items ri 
    JOIN reservations r ON ri.reserve_id = r.reserve_id 
    WHERE ri.status = 'Checked Out' AND r.return_date < CURDATE()
";
$overdueResult = $conn->query($overdueSql);
// -------------------------------------------------------------------

$overdueRow = $overdueResult->fetch_assoc();
$overdueCount = isset($overdueRow['total']) ? (int)$overdueRow['total'] : 0;

$sql_maintenance_count = "SELECT COUNT(*) AS maintenance_count FROM assets WHERE status = 'Maintenance'";
$result_maintenance = $conn->query($sql_maintenance_count);
$maintenance_count = ($result_maintenance && $result_maintenance->num_rows > 0) ? $result_maintenance->fetch_assoc()['maintenance_count'] : 0;

// --- Ambil Butiran Aset (Tiada perubahan) ---
$total_assets_details = fetch_asset_details($conn, "a.status NOT IN ('Broken', 'Decommissioned', 'Missing')");
$available_assets_details = fetch_asset_details($conn, "a.status = 'Available'");

// Kueri aset yang telah dikeluarkan (Checked Out) - FIXED VERSION
$checked_out_sql = "
    SELECT
        a.asset_id, a.asset_code, a.status,
        i.item_name, c.category_name,
        u.name as user_name,
        r.return_date
    FROM assets a
    JOIN item i ON a.item_id = i.item_id
    JOIN categories c ON i.category_id = c.category_id
    /* Gunakan INNER JOIN supaya hanya aset yang ADA rekod pinjaman aktif sahaja keluar */
    JOIN reservation_assets ra ON a.asset_id = ra.asset_id
    JOIN reservation_items ri ON ra.reservation_item_id = ri.id
    JOIN reservations r ON ri.reserve_id = r.reserve_id
    JOIN person u ON r.person_id = u.person_id
    /* Tapis hanya untuk pinjaman yang belum dipulangkan */
    WHERE a.status = 'Checked Out' 
    AND ri.status = 'Checked Out'
    ORDER BY r.return_date ASC, a.asset_code ASC
";

$checked_out_result = $conn->query($checked_out_sql);
$checked_out_details = $checked_out_result ? $checked_out_result->fetch_all(MYSQLI_ASSOC) : [];

// Kueri butiran aset yang telah luput (Overdue) - (Tiada perubahan, kueri ini sudah betul)
$overdue_details_sql = "
    SELECT
        ri.id AS reservation_item_id, u.name AS user_name, u.phoneNum AS user_phone, i.item_name,
        r.return_date, DATEDIFF(CURDATE(), r.return_date) AS days_overdue,
        GROUP_CONCAT(DISTINCT a.asset_code SEPARATOR ', ') AS assigned_assets
    FROM reservation_items ri
    JOIN reservations r ON ri.reserve_id = r.reserve_id
    JOIN person u ON r.person_id = u.person_id
    JOIN item i ON ri.item_id = i.item_id
    LEFT JOIN reservation_assets ra ON ri.id = ra.reservation_item_id
    LEFT JOIN assets a ON ra.asset_id = a.asset_id
    WHERE ri.status = 'Checked Out' AND r.return_date < CURDATE()
    GROUP BY ri.id ORDER BY r.return_date ASC ";
$overdue_details_result = $conn->query($overdue_details_sql);
$overdue_details = $overdue_details_result ? $overdue_details_result->fetch_all(MYSQLI_ASSOC) : [];

$maintenance_assets_details = fetch_asset_details($conn, "a.status = 'Maintenance'");

// --- Data Carta (Tiada perubahan) ---
$chart_sql = "
    SELECT 
        c.category_name, 
        COUNT(ri.id) as loan_count 
    FROM reservation_items ri 
    JOIN item i ON ri.item_id = i.item_id 
    JOIN categories c ON i.category_id = c.category_id 
    WHERE ri.status = 'Checked Out' 
    GROUP BY c.category_id 
    ORDER BY loan_count DESC
";
$chart_result = $conn->query($chart_sql);
$chart_data = $chart_result ? $chart_result->fetch_all(MYSQLI_ASSOC) : [];

$chartLabels = [];
$chartValues = [];

foreach ($chart_data as $row) {
    $chartLabels[] = $row['category_name'];
    $chartValues[] = (int) $row['loan_count']; 
}
$totalLoans = array_sum($chartValues);

// --- Data Kalendar (Tiada perubahan) ---
$events = []; 

$historySql = "SELECT ri.quantity, r.reserve_date, r.return_date, ri.status,
                       u.name AS username, i.item_name
                FROM reservation_items ri
                JOIN reservations r ON ri.reserve_id = r.reserve_id
                JOIN person u ON r.person_id = u.person_id
                JOIN item i ON ri.item_id = i.item_id
                WHERE ri.status IN ('Approved', 'Checked Out')
                ORDER BY r.reserve_date ASC";
$historyResult = $conn->query($historySql);

if ($historyResult) {
    while ($h = $historyResult->fetch_assoc()) {
        
        $returnDate = date('Y-m-d', strtotime($h['return_date']));

        // --- 1. Acara Tempahan (HIJAU) ---
        $events[] = [
            'title' => "{$h['item_name']} ({$h['quantity']}) - {$h['username']}",
            'start' => date('Y-m-d', strtotime($h['reserve_date'])),
            // End +1 supaya petak hari terakhir (14hb/17hb) penuh dengan warna hijau
            'end' => date('Y-m-d', strtotime($returnDate . ' +1 day')), 
            'backgroundColor' => '#10b981', 
            'borderColor' => '#10b981',
            'description' => 'Reservation',
            'allDay' => true
        ];

        // --- 2. Tempoh Buffer (OREN) ---
        if ($h['status'] === 'Checked Out' && !empty($h['return_date'])) {
            // PAKSA BUFFER BERMULA HARI SETERUSNYA (15hb atau 18hb)
            $bufferStart = date('Y-m-d', strtotime($returnDate . ' +1 day'));
            // END +2 SUPAYA DIA MENGISI PENUH HARI TERSEBUT
            $bufferEnd = date('Y-m-d', strtotime($returnDate . ' +2 days')); 

            $events[] = [
                'title' => "Buffer: {$h['item_name']}",
                'start' => $bufferStart,
                'end' => $bufferEnd,
                'backgroundColor' => '#f59e0b', 
                'borderColor' => '#f59e0b',
                'textColor' => '#ffffff',
                'description' => 'Buffer - Pending Check-in',
                'allDay' => true // Wajib untuk elakkan jadi titik
            ];
        }
    }
}
// --- Pemberitahuan (Notification History & New) ---
// --- Pemberitahuan (Notification History & New) ---
$tech_id = (int) $_SESSION['person_id']; 
$tech_role_id = 2; 

// Selesaikan isu GROUP BY
$conn->query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

// Ambil 10 notifikasi unik (tak berulang)
$sql_all_notif = "SELECT * FROM notifications 
                  WHERE person_id = ? OR recipient_role_id = ? 
                  GROUP BY message, related_id 
                  ORDER BY created_at DESC 
                  LIMIT 10";

$stmt_all = $conn->prepare($sql_all_notif);
if ($stmt_all) {
    $stmt_all->bind_param("ii", $tech_id, $tech_role_id);
    $stmt_all->execute();
    $all_notifications = $stmt_all->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_all->close();
} else {
    $all_notifications = [];
}

// Kira jumlah UNREAD yang sebenar untuk badge merah
$sql_unread = "SELECT COUNT(*) as unread FROM notifications 
               WHERE (person_id = ? OR recipient_role_id = ?) AND is_read = 0";
$stmt_u = $conn->prepare($sql_unread);
$stmt_u->bind_param("ii", $tech_id, $tech_role_id);
$stmt_u->execute();
$new_notif_count = $stmt_u->get_result()->fetch_assoc()['unread'] ?? 0;
$stmt_u->close();


// Gantikan line 271 yang error tu dengan ini:
$new_notif_count = isset($all_notifications) ? count(array_filter($all_notifications, function($n) { 
    return $n['is_read'] == 0; 
})) : 0;


// --- Pengekodan JSON ---
$total_assets_details_json = json_encode($total_assets_details);
$available_assets_details_json = json_encode($available_assets_details);
$checked_out_details_json = json_encode($checked_out_details);
$overdue_details_json = json_encode($overdue_details);
$maintenance_assets_details_json = json_encode($maintenance_assets_details);
$events_json = json_encode($events);

// TUKAR LINE INI: Guna $all_notifications supaya variable ni wujud dlm JSON
$new_notifications_json = json_encode($all_notifications ?? []);


// 1. Ambil 3 Permohonan Pinjaman Baru (Ganti yang tadi)
$sql_new_req = "
    SELECT i.item_name, u.name as user_name, r.created_at as time_ago 
    FROM reservation_items ri
    JOIN reservations r ON ri.reserve_id = r.reserve_id
    JOIN person u ON r.person_id = u.person_id
    JOIN item i ON ri.item_id = i.item_id
    WHERE ri.status = 'Pending'
    ORDER BY r.created_at DESC LIMIT 3";
$res_new_req = $conn->query($sql_new_req);
$new_requests_data = $res_new_req ? $res_new_req->fetch_all(MYSQLI_ASSOC) : [];


// 2. Ambil 3 Pulangan Aset Yang Dijadualkan Hari Ini
$today = date('Y-m-d');
$sql_returns = "
    SELECT a.asset_code, u.name as user_name, r.return_date as return_time
    FROM reservation_assets ra
    JOIN assets a ON ra.asset_id = a.asset_id
    JOIN reservation_items ri ON ra.reservation_item_id = ri.id
    JOIN reservations r ON ri.reserve_id = r.reserve_id
    JOIN person u ON r.person_id = u.person_id
    WHERE r.return_date = '$today' AND ri.status = 'Checked Out'
    LIMIT 3";
$res_returns = $conn->query($sql_returns);
$returns_today_data = $res_returns ? $res_returns->fetch_all(MYSQLI_ASSOC) : [];

// 3. Ambil 3 Item Paling Lama Overdue (Gunakan data yang sedia ada)
// Memandangkan kau dah ada $overdue_details kat atas, kita cuma perlu tapis sikit:
$top_overdue_data = array_slice($overdue_details, 0, 3);

$conn->close(); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0"> 
<title>Technician Dashboard | UniKL ACE</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<link rel="stylesheet" href="../css/style.css">
<style>

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
    padding: 2rem;
    
    /* Memastikan background meliputi seluruh ruang */
    background-repeat: repeat;
}

/* --- FIX FULLCALENDAR MOBILE RESPONSIVE --- */

/* 1. Pastikan container tak melimpah keluar */
#calendar, .fc {
    max-width: 100% !important;
    overflow-x: hidden;
}

/* 2. Susun balik Header (Bulan, Haribulan & Butang) supaya tak bertindih */
@media (max-width: 768px) {
    .fc .fc-toolbar {
        display: flex;
        flex-direction: column; /* Susun menegak dlm mobile */
        gap: 10px;
        align-items: center;
    }

    .fc-toolbar-title {
        font-size: 1.1rem !important; /* Kecilkan tajuk bulan */
        text-align: center;
    }

    /* Kecilkan butang prev, next, today */
    .fc .fc-button {
        padding: 5px 8px !important;
        font-size: 0.8rem !important;
    }

    /* 3. Kecilkan font hari (Sun, Mon, Tue...) supaya muat satu baris */
    .fc-col-header-cell-cushion {
        font-size: 0.7rem !important;
        padding: 2px !important;
    }

    /* 4. Kecilkan nombor tarikh dlm kotak */
    .fc-daygrid-day-number {
        font-size: 0.75rem !important;
        padding: 4px !important;
    }

    /* 5. Set height kotak tarikh supaya tak terlalu panjang ke bawah */
    .fc .fc-daygrid-body {
        width: 100% !important;
    }
    
    .fc .fc-daygrid-day {
        height: 60px !important; /* Paksa height kotak jadi kecil */
    }

    /* 6. Hilangkan atau kecilkan dot/event dlm mobile supaya tak serabut */
    .fc-event-main {
        font-size: 0.6rem !important;
        white-space: nowrap;
        overflow: hidden;
    }
}

/* Extra: Bagikan card calendar tu nampak lebih "clean" */
.fc-view-harness {
    background: #ffffff;
    border-radius: 12px;
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

    /* Hilangkan focus ring & hover grey yang buruk */
    .notif-item-wrapper .dropdown-item:hover {
        filter: brightness(0.98);
        background-color: inherit;
    }

    /* Scrollbar yang nipis (Modern look) */
    #notificationList::-webkit-scrollbar {
        width: 5px;
    }
    #notificationList::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 10px;
    }

    /* Hover effect untuk butang Mark As Read */
    .btn-mark-read:hover {
        background-color: #bae6fd !important;
        color: #0369a1 !important;
    }
</style>

</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div> 
<div class="sidebar" id="admin-sidebar">
    <div>
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fa-solid fa-wrench"></i></div>
            <div class="logo-text"><strong>UniKL Technician</strong><br><span style="font-size: 0.85rem; color: #64748b;">System Support</span></div>
        </div>
        
        <div class="sidebar-nav">
            <a href="dashboard_tech.php" class="active"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
            <a href="check_out.php">
                <i class="fa-solid fa-dolly"></i> Manage Requests
                <?php if ($pending_count_for_badge > 0): ?>
                    <span class="badge rounded-pill"><?= $pending_count_for_badge ?></span>
                <?php endif; ?>
            </a>
            <a href="manageItem_tech.php"><i class="fa-solid fa-box-archive"></i> Manage Items</a>
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
        <h3>Dashboard</h3> 
    </div>

<div class="topbar-right">
    <div class="dropdown me-3">
        <div class="notification-wrapper" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer; position: relative;">
            <i class="fa-solid fa-bell"></i>
            <?php if (($new_notif_count ?? 0) > 0): ?>
                <span class="notification-dot"></span>
            <?php endif; ?>
        </div>
        
        <ul class="dropdown-menu dropdown-menu-end shadow border-0" id="notificationList">
    <div class="p-3 d-flex justify-content-between align-items-center border-bottom bg-light">
        <h6 class="mb-0 fw-bold text-dark">Notifications</h6>
        <button id="markAllReadBtn" class="btn btn-sm">Mark As Read</button>
    </div>

    <div id="notifItemsContainer" style="max-height: 350px; overflow-y: auto;">
        <?php if (!empty($all_notifications)): ?>
            <?php foreach($all_notifications as $notif): ?>
                <?php endforeach; ?>
        <?php else: ?>
            <li class="p-4 text-center text-muted small">No notifications</li>
        <?php endif; ?>
    </div>

    <li class="mark-all-container">
        <a href="notifications_history.php" class="dropdown-item text-center small py-3 fw-bold text-primary" style="border-top: 1px solid #eee; background-color: #f8f9fa;">
            View All Notifications <i class="fa-solid fa-arrow-right ms-1"></i>
        </a>
    </li>
</ul>
    </div>

    <a href="profile_tech.php" class="user-pill text-decoration-none">
        <div class="text-end me-2 d-none d-md-block">
            <div class="user-name" style="text-transform: capitalize; font-weight: 600; color: #1e293b; line-height: 1;">
                <?= htmlspecialchars($displayName) ?>
            </div>
            <small class="text-muted" style="font-size: 0.75rem;">Technician</small>
        </div>
        <div class="profile-avatar">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($displayName) ?>&background=06b6d4&color=fff" class="rounded-circle" width="35">
        </div>
    </a>
</div>
</div>

    <div class="container-fluid">
        <div class="row g-3 justify-content-center">
            
            <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6">
                <div class="card card-summary card-total-assets" id="totalAssetsCard" style="cursor:pointer;">
    <div class="icon-wrapper"><i class="fa-solid fa-desktop"></i></div>
    <div class="count text-primary-blue"><?= $totalAssetsCount ?></div> 
    <div class="label">Total Assets</div>
</div>
            </div>

            <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6">
                <div class="card card-summary card-available" id="availableAssetsCard" data-bs-toggle="modal" data-bs-target="#availableAssetsModal">
                    <div class="icon-wrapper"><i class="fa-solid fa-box-open"></i></div>
                    <div class="count text-green"><?= $availableCount ?></div> 
                    <div class="label">Available</div>
                </div>
            </div>

            <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6">
                <div class="card card-summary card-checked-out" id="checkedOutAssetsCard" data-bs-toggle="modal" data-bs-target="#checkedOutAssetsModal">
                    <div class="icon-wrapper"><i class="fa-solid fa-handshake"></i></div>
                    <div class="count text-orange"><?= $checkedOutCount ?></div> 
                    <div class="label">Checked Out</div>
                </div>
            </div>

            <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6">
                <div class="card card-summary card-overdue" id="overdueCard" data-bs-toggle="modal" data-bs-target="#overdueModal">
                    <div class="icon-wrapper"><i class="fa-solid fa-clock"></i></div>
                    <div class="count text-red"><?= $overdueCount ?></div> 
                    <div class="label">Overdue User</div>
                </div>
            </div>

            <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6">
                   <div class="card card-summary card-maintenance" id="maintenanceAssetsCard" data-bs-toggle="modal" data-bs-target="#maintenanceAssetsModal">
                    <div class="icon-wrapper"><i class="fas fa-wrench"></i></div>
                    <div class="count text-amber"><?= $maintenance_count ?></div> 
                    <div class="label">Maintenance</div>
                </div>
            </div>
            
        </div> 
<div class="row mt-4">
    <div class="col-12 mb-4"> <div class="card shadow-sm border-0 h-100">
	<div class="card-body">
                <h5 class="mb-3"><i class="fa-solid fa-calendar-days text-primary-blue me-2"></i> Reservation Calendar</h5>
                <div id="calendar"></div>
                <div class="d-flex justify-content-center gap-4 mt-3 py-2 bg-light rounded-pill shadow-sm mx-auto" style="max-width: 400px;">
    <div class="small"><span class="badge rounded-circle p-1 me-1" style="background:#10b981;">&nbsp;</span> Reservation</div>
    <div class="small"><span class="badge rounded-circle p-1 me-1" style="background:#f59e0b;">&nbsp;</span> Buffer Period</div>
</div>
            </div>
			
        </div>
    </div>
</div>

<div class="row mt-2 g-4">
    <div class="col-lg-6 col-12">
        <div class="card p-4 h-100 shadow-sm border-0">
            <h5 class="mb-3"><i class="fa-solid fa-clipboard-list text-primary-blue me-2"></i> Pending Actions</h5>
            
            <div class="action-item mb-3">
                <?php if (!empty($new_requests_data)): ?>
                    <h6><i class="fa-solid fa-bell-concierge text-primary-blue me-1"></i> New Requests (<?= count($new_requests_data) ?>)</h6>
                    <ul class="list-unstyled small border-bottom pb-2">
                        <?php foreach (array_slice($new_requests_data, 0, 3) as $req): ?>
                            <li class="d-flex justify-content-between py-1">
                                <span><?= htmlspecialchars($req['item_name']) ?> by <strong><?= htmlspecialchars($req['user_name']) ?></strong></span>
                                <span class="text-muted"><?= htmlspecialchars($req['time_ago']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <h6><i class="fa-solid fa-bell-concierge text-primary-blue me-1"></i> New Requests (0)</h6>
                    <p class="small text-muted border-bottom pb-2">No new requests pending.</p>
                <?php endif; ?>
            </div>

            <div class="action-item mb-3">
                <?php if (!empty($returns_today_data)): ?>
                    <h6><i class="fa-solid fa-rotate-left text-success me-1"></i> Scheduled Returns (<?= count($returns_today_data) ?>)</h6>
                    <ul class="list-unstyled small border-bottom pb-2">
                        <?php foreach (array_slice($returns_today_data, 0, 3) as $ret): ?>
                            <li class="d-flex justify-content-between py-1">
                                <span><?= htmlspecialchars($ret['asset_code']) ?> (<?= htmlspecialchars($ret['user_name']) ?>)</span>
                                <span class="text-success fw-bold"><?= htmlspecialchars($ret['return_time']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <h6><i class="fa-solid fa-rotate-left text-success me-1"></i> Scheduled Returns (0)</h6>
                    <p class="small text-muted border-bottom pb-2">No returns today.</p>
                <?php endif; ?>
            </div>

            <div class="action-item">
                <?php if (!empty($top_overdue_data)): ?>
                    <h6><i class="fa-solid fa-clock-rotate-left text-danger me-1"></i> Top Overdue Items</h6>
                    <ul class="list-unstyled small mb-1">
                        <?php foreach (array_slice($top_overdue_data, 0, 3) as $overdue): ?>
                            <li class="d-flex justify-content-between py-1">
                                <span><?= htmlspecialchars($overdue['item_name']) ?></span>
                                <span class="badge rounded-pill bg-danger"><?= htmlspecialchars($overdue['days_overdue']) ?> days</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <h6><i class="fa-solid fa-clock-rotate-left text-danger me-1"></i> Overdue (0)</h6>
                    <p class="small text-muted">No critically overdue items.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6 col-12">
        <div class="card p-4 h-100 shadow-sm border-0">
            <h5 class="mb-4"><i class="fa-solid fa-chart-pie text-primary-blue me-2"></i> Loan Distribution</h5>
            <div id="loanChartContainer">
                <?php 
                $colors_fill = ['bg-blue-fill', 'bg-green-fill', 'bg-orange-fill', 'bg-red-fill'];
                $chart_index = 0;
                if ($totalLoans == 0): ?>
                    <div class="text-center p-4 text-muted">No items checked out.</div>
                <?php else:
                    foreach ($chartLabels as $index => $label):
                        $value = $chartValues[$index];
                        $percentage = round(($value / $totalLoans) * 100);
                        $colorClass = $colors_fill[$chart_index % count($colors_fill)];
                        $chart_index++;
                ?>
                <div class="loan-chart-item mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small fw-bold"><?= htmlspecialchars($label) ?></span>
                        <span class="small fw-bold"><?= $value ?></span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar <?= $colorClass ?>" style="width: <?= $percentage ?>%;"></div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
            <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                <span class="fw-bold">Total Loans</span>
                <h4 class="mb-0 fw-bold"><?= $totalLoans ?></h4>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden;">
            <div class="modal-header" style="background: #f8fafc; border-bottom: 1px solid #eee;">
                <h5 class="modal-title" id="modalDateLabel">Date Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalEventList" style="max-height: 400px; overflow-y: auto; padding: 20px;">
                </div>
            <div class="modal-footer" style="border-top: none; background: #f8fafc;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 10px;">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="totalAssetsModal" tabindex="-1" aria-labelledby="totalAssetsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"> <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="totalAssetsModalLabel"><i class="fa-solid fa-laptop text-primary-blue me-2"></i>Total Assets (Operational)</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <p class="info-secondary">List of all assets excluding broken, decommissioned, or missing items.</p>
            <div id="totalAssetsList" class="table-responsive"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
    </div>
    </div>
</div>

<div class="modal fade" id="availableAssetsModal" tabindex="-1" aria-labelledby="availableAssetsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"> <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="availableAssetsModalLabel"><i class="fa-solid fa-box-open text-green"></i>Available Assets</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <p class="info-secondary">List of assets currently available for reservation.</p>
            <div id="availableAssetsList" class="table-responsive"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
    </div>
    </div>
</div>

<div class="modal fade" id="checkedOutAssetsModal" tabindex="-1" aria-labelledby="checkedOutAssetsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"> <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="checkedOutAssetsModalLabel"><i class="fa-solid fa-handshake text-orange"></i> Checked Out Assets</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <p class="info-secondary">List of assets currently on loan.</p>
            <div id="checkedOutAssetsList" class="table-responsive"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
    </div>
    </div>
</div>

<div class="modal fade" id="overdueModal" tabindex="-1" aria-labelledby="overdueModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"> <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="overdueModalLabel"><i class="fa-solid fa-clock text-red"></i> Overdue User</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <p class="info-secondary">List of items currently checked out and past their return date.</p>
            <div id="overdueList" class="table-responsive"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
    </div>
    </div>
</div>

<div class="modal fade" id="maintenanceAssetsModal" tabindex="-1" aria-labelledby="maintenanceAssetsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"> 
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="maintenanceAssetsModalLabel"><i class="fa-solid fa-wrench text-amber"></i>Assets Under Maintenance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="info-secondary">List of assets currently undergoing maintenance or repair.</p>
                <div id="maintenanceAssetsList" class="table-responsive"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
const totalAssetsDetails = <?php echo $total_assets_details_json; ?>;
    const availableAssetsDetails = <?php echo $available_assets_details_json; ?>;
   // Tambah || [] supaya kalau PHP kosong, dia jadi array kosong, bukan undefined
const checkedOutAssetsDetails = <?php echo $checked_out_details_json ?: '[]'; ?>;
    const overdueDetails = <?php echo $overdue_details_json; ?>;
    const maintenanceAssetsDetails = <?php echo $maintenance_assets_details_json; ?>;
    const eventsData = <?php echo $events_json; ?>;

    const newNotifications = <?php echo $new_notifications_json; ?>;
    let currentNewCount = <?php echo $new_notif_count; ?>;
    
    // --- START: PENAMBAHAN UNTUK CHART.JS (HARUS DEFINED DALAM BLOK PHP) ---
    const chartLabels = <?php echo json_encode($chartLabels ?? []); ?>;
    const chartValues = <?php echo json_encode($chartValues ?? []); ?>;
    // --- END: PENAMBAHAN UNTUK CHART.JS ---


        // --- NOTIFICATION FUNCTIONS (KEMAS KINI) ---
function renderNotifications(notifications) {
    $notifList.empty();

    // Gunakan <button type="button"> supaya dia tak bertindak macam link
    const header = `
    <div class="p-3 d-flex justify-content-between align-items-center border-bottom bg-light" style="border-radius: 15px 15px 0 0;">
        <h6 class="mb-0 fw-bold text-dark">Notifications</h6>
        <button type="button" id="markAllReadBtn" class="btn btn-sm fw-bold" style="font-size: 0.7rem; background-color: #e0f2fe; color: #0369a1; border-radius: 8px; padding: 4px 10px; border: none;">
            Mark As Read
        </button>
    </div>
    <div id="notifItemsContainer"></div>`; // Tambah container khas untuk item
    
    $notifList.append(header);
    const $itemsContainer = $('#notifItemsContainer');

    if (!notifications || notifications.length === 0) {
        $itemsContainer.append('<li class="p-4 text-center text-muted small">No new notifications.</li>');
    } else {
        notifications.forEach((notif, index) => {
            const createdDate = new Date(notif.created_at);
            const timeStr = createdDate.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
            const isUnread = notif.is_read == 0;
            const bgStyle = isUnread ? 'background-color: #f0f9ff;' : '';
            const textClass = isUnread ? 'fw-bold text-dark' : 'text-muted';

            const item = `
                <div class="notif-item-wrapper">
                    <a class="dropdown-item p-3" href="view_reservation.php?id=${notif.related_id}" style="${bgStyle}">
                        <div class="d-flex align-items-start">
                            <i class="fa-solid fa-bell me-3 mt-1 text-primary"></i>
                            <div class="flex-grow-1">
                                <p class="mb-0 small ${textClass}" style="line-height: 1.2; white-space: normal;">
                                    ${notif.message}
                                </p>
                                <small class="text-muted" style="font-size: 0.65rem;">${timeStr}</small>
                            </div>
                        </div>
                    </a>
                    <hr class="dropdown-divider my-0 opacity-50">
                </div>`;
            $itemsContainer.append(item);
        });
    }
}
    document.addEventListener('DOMContentLoaded', function() {
        
		
        // Cek jQuery
        if (typeof jQuery === 'undefined') {
            console.error("jQuery is not loaded!");
            return;
        }

        const $notifBadge = $('#notif-count-badge');
        const $notifHeaderCount = $('#notif-count-header');
        const $notifList = $('#notificationList');
		
$(document).on('click', '#markAllReadBtn', function() {
    const $btn = $(this);
    
    $.ajax({
        url: 'mark_all_read.php',
        type: 'POST',
        dataType: 'json', // Beritahu jQuery kita jangka JSON
        success: function(data) {
            if (data.status === 'success') {
                // Hanya padam di skrin jika database berjaya diupdate
                $('#notifItemsContainer .notif-item-wrapper').slideUp(300, function() {
                    $(this).remove();
                    if ($('#notifItemsContainer').children().length === 0) {
                        $('#notifItemsContainer').html('<li class="p-4 text-center text-muted small">No new notifications.</li>');
                    }
                });
                
                $('.notification-dot, #notif-count-badge').fadeOut();
                $btn.text('All Cleared').prop('disabled', true).css('opacity', '0.5');
                
                // Reset counter global kalau ada
                currentNewCount = 0; 
            } else {
                alert("Ralat: " + data.message);
            }
        },
        error: function(xhr, status, error) {
            console.error("AJAX Error Details:", xhr.responseText);
            alert("Gagal menghubungi server. Sila semak console log.");
        }
    });
});
// --- SIDEBAR LOGIC UNTUK MOBILE ---
        const sidebar = document.getElementById('admin-sidebar');
        const toggleBtn = document.getElementById('sidebarToggle');
        const overlay = document.getElementById('sidebarOverlay');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('show'); // Guna .show
                if (overlay) overlay.classList.toggle('active');
            });
        }

        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('show');
                overlay.classList.remove('active');
            });
        }
		
        // --- FULLCALENDAR LOGIC ---
        const calendarEl = document.getElementById('calendar');
        if (calendarEl) {
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
 right: 'dayGridMonth,listWeek'
 },
 
 // TAMBAH INI: Tukar tulisan button jadi emoji
    buttonText: {
        today: 'Today',     // Kalau nak tukar tulisan 'Today'
        month: '📅 ',     // Letak emoji depan atau pakai emoji saja
        list: '📋'        // Ini untuk button listWeek kau
    },
                displayEventTime: false,
                eventDisplay: 'block',
                height: 'auto',
                events: eventsData,
                eventDidMount: function(info) {
                    info.el.style.backgroundColor = info.event.backgroundColor;
                    info.el.style.borderColor = info.event.backgroundColor;
                    info.el.style.borderRadius = '6px';
                    info.el.style.color = 'white';
                    info.el.style.padding = '2px 5px';
                    
                    if (info.event.extendedProps.description && info.event.extendedProps.description.includes('Buffer')) {
                        info.el.style.backgroundImage = 'linear-gradient(45deg, rgba(255,255,255,.15) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.15) 50%, rgba(255,255,255,.15) 75%, transparent 75%, transparent)';
                        info.el.style.backgroundSize = '10px 10px';
                    }
                },
dateClick: function(info) {
                    const selectedDate = info.dateStr;
                    const modalBody = document.getElementById('modalEventList');
                    const modalTitle = document.getElementById('modalDateLabel');
                    const displayDate = new Date(selectedDate).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
                    modalTitle.innerHTML = `<i class="fa-regular fa-calendar-days me-2"></i> Details for ${displayDate}`;
                    const filtered = eventsData.filter(ev => selectedDate >= ev.start && selectedDate < ev.end);
                    if (filtered.length === 0) {
                        modalBody.innerHTML = '<div class="text-center py-4 text-muted">No activities for this date.</div>';
                    } else {
                        modalBody.innerHTML = filtered.map(ev => {
                            const isBuffer = ev.description && ev.description.includes('Buffer');
                            const themeColor = isBuffer ? '#f59e0b' : '#10b981';
                            const badgeClass = isBuffer ? 'bg-warning-light text-warning' : 'bg-success-light text-success';
                            return `<div class="detail-card mb-3" style="border-left: 5px solid ${themeColor}; background: #fff; padding: 15px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="fw-bold mb-0">${ev.title}</h6>
                                    <span class="badge ${badgeClass}">${isBuffer ? 'Buffer' : 'Reservation'}</span>
                                </div>
                                <div class="small text-muted">
                                    <i class="fa-regular fa-clock me-1"></i> Start: ${ev.start}<br>
                                    <i class="fa-solid fa-hourglass-end me-1"></i> End: ${ev.end}
                                </div>
                            </div>`;
                        }).join('');
                    }
                    new bootstrap.Modal(document.getElementById('eventModal')).show();
                }
            });
            calendar.render();
        }
		
       function createAssetTableHTML(assetList, includeUserAndReturnDate = false) {
            if (!assetList || assetList.length === 0) {
                return '<div class="text-center p-4 text-muted"><i class="fa-solid fa-check-circle fa-2x mb-2" style="color: #10b981;"></i><br>No matching assets found.</div>';
            }

            const tableId = `assetTable_${Date.now()}_${Math.random().toString(36).substring(2, 7)}`;
            let tableHTML = `<table class="table table-sm table-striped table-hover asset-detail-table" id="${tableId}">`;
            tableHTML += `<thead><tr>
                                <th>Asset Code</th>
                                <th>Item Name</th>
                                <th>Category</th>
                                ${includeUserAndReturnDate ? '<th>Checked Out To</th><th>Return Due</th>' : '<th>Status</th>'}
                              </tr></thead><tbody>`;

            assetList.forEach(asset => {
                const itemName = asset.item_name || '<em class="text-muted">N/A</em>';
                const categoryName = asset.category_name || '<em class="text-muted">N/A</em>';
                const statusValue = asset.status || 'Unknown';
// Ganti bahagian if-else lama kau dengan yang ini:
let statusBadgeClass = 'badge-status-default'; // Default jadi kelabu

if (statusValue === 'Available') {
    statusBadgeClass = 'badge-status-available';
} else if (statusValue === 'Checked Out') {
    statusBadgeClass = 'badge-status-checked-out';
} else if (statusValue === 'Maintenance') {
    statusBadgeClass = 'badge-status-maintenance';
} else if (statusValue === 'Reserved') {
    statusBadgeClass = 'badge-status-default '; // Kita guna oren lembut untuk Reserved
} else if (statusValue === 'Damaged' || statusValue === 'Broken' || statusValue === 'Missing') {
    statusBadgeClass = 'badge-status-danger'; // Kita guna merah untuk Damaged
}

                const statusBadge = `<span class="badge rounded-pill ${statusBadgeClass}">${statusValue}</span>`;

tableHTML += `<tr>
                <td>
                    <a href="check_out.php?search_id=${encodeURIComponent(asset.asset_code)}" 
                       class="btn btn-sm btn-outline-primary fw-bold" 
                       style="text-decoration: none;">
                       ${asset.asset_code || 'N/A'}
                    </a>
                </td>
                <td>${itemName}</td>
                <td>${categoryName}</td>`;                if (includeUserAndReturnDate) {
                    const userName = asset.user_name || '<em class="text-muted">N/A</em>';
                    
                    const returnDate = asset.return_date ? new Date(asset.return_date + 'T00:00:00') : null;
                    const returnDateFormatted = returnDate && !isNaN(returnDate) ? returnDate.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '<em class="text-muted">N/A</em>';

                    tableHTML += `<td>${userName}</td><td>${returnDateFormatted}</td>`;
                } else {
                    tableHTML += `<td>${statusBadge}</td>`;
                }

                tableHTML += `</tr>`;
            });
            tableHTML += '</tbody></table>';
            return { html: tableHTML, id: tableId };
        }


        
        
        
       function setupModalTrigger(cardId, modalElementId, listContainerId, dataList, includeUser = false) {
    const card = document.getElementById(cardId);
    const modalElement = document.getElementById(modalElementId);
    const listContainer = document.getElementById(listContainerId);

    if (card && modalElement && listContainer) {
        const $modal = $(modalElement);
        
        // Bersihkan modal bila tutup
        $modal.on('hidden.bs.modal', function () {
            const existingTable = listContainer.querySelector('.asset-detail-table');
            if (existingTable && $.fn.DataTable.isDataTable(existingTable)) { 
                $(existingTable).DataTable().destroy(); 
            }
            listContainer.innerHTML = '';
        });

       $(card).on('click', function() {
    // 1. Generate data (dia return object {html, id})
    const tableData = createAssetTableHTML(dataList, includeUser);
    
    // 2. MASUKKAN HTML SAHAJA. 
    // Tadi kau letak 'tableData' saja, sebab tu dia keluar [object Object] atau undefined
    listContainer.innerHTML = tableData.html; 

    $modal.modal('show');

    // 3. Initialize DataTable pakai ID yang kita generate tadi
    setTimeout(() => {
        const newTable = $(`#${tableData.id}`);
        if (newTable.length) {
            newTable.DataTable({
                "pageLength": 10,
                "order": [],
                "destroy": true,
                "responsive": true,
                "language": {
                    "search": "Search:",
                    "zeroRecords": "No matching assets found"
                }
            });
        }
    }, 200);
});
    }
}

        
        
        
        setupModalTrigger('totalAssetsCard', 'totalAssetsModal', 'totalAssetsList', totalAssetsDetails);
        setupModalTrigger('availableAssetsCard', 'availableAssetsModal', 'availableAssetsList', availableAssetsDetails);
        setupModalTrigger('checkedOutAssetsCard', 'checkedOutAssetsModal', 'checkedOutAssetsList', checkedOutAssetsDetails, true);
        setupModalTrigger('maintenanceAssetsCard', 'maintenanceAssetsModal', 'maintenanceAssetsList', maintenanceAssetsDetails);


        
        
        
        const overdueCard = document.getElementById('overdueCard');
        const overdueModalElement = document.getElementById('overdueModal');
        const overdueListContainer = document.getElementById('overdueList');

        if (overdueCard && overdueModalElement && overdueListContainer) {
            const $overdueModal = $(overdueModalElement);

             $overdueModal.on('hidden.bs.modal', function () {
                 const existingTable = overdueListContainer.querySelector('.asset-detail-table');
                 if (existingTable && $.fn.DataTable.isDataTable(existingTable)) { $(existingTable).DataTable().destroy(); }
                 overdueListContainer.innerHTML = '';
                 });

            $(overdueCard).on('click', function() {
                overdueListContainer.innerHTML = '';

                if (overdueDetails.length === 0) {
                    overdueListContainer.innerHTML = '<div class="text-center p-4 text-muted"><i class="fa-solid fa-check-circle fa-2x mb-2" style="color: #10b981;"></i><br>No items are currently overdue.</div>';
                } else {
                     const tableId = `overdueTable_${Date.now()}`;
                     let tableHTML = `<table class="table table-sm table-striped table-hover asset-detail-table" id="${tableId}">`;
                     tableHTML += `<thead><tr>
                                     <th>User</th>
                                     <th>Item</th>
                                     <th>Asset Code(s)</th>
                                     <th>Return Date</th>
                                     <th class="text-danger">Days Overdue</th>
                                     <th>Contact</th>
                                    </tr></thead><tbody>`;
                     overdueDetails.forEach(item => {
                          const returnDate = new Date(item.return_date + 'T00:00:00');
                          const returnDateFormatted = returnDate.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
                          const phoneLink = item.user_phone ? `<a href="tel:${item.user_phone}">${item.user_phone}</a>` : 'N/A';
                          const assignedAssets = item.assigned_assets ? `<span class="badge rounded-pill badge-status-default">${item.assigned_assets}</span>` : '<em class="text-muted">None Assigned</em>';

                          tableHTML += `<tr>
                                     <td><strong>${item.user_name || 'N/A'}</strong></td>
                                     <td>${item.item_name || 'N/A'}</td>
                                     <td>${assignedAssets}</td>
                                     <td>${returnDateFormatted}</td>
                                     <td><span class="badge rounded-pill badge-status-danger">${item.days_overdue}</span></td>
                                     <td>${phoneLink}</td>
                                    </tr>`;
                     });
                     tableHTML += '</tbody></table>';
                     overdueListContainer.innerHTML = tableHTML;

                     $overdueModal.modal('show');

                     setTimeout(() => {
                          const newTable = $(`#${tableId}`);
                          if (newTable.length) {
                              newTable.DataTable({
                                  "pageLength": 10, 
                                  "order": [[4, "desc"]], 
                                  "destroy": true,
                                  "responsive": true, // <-- PENAMBAHAN UNTUK MOBILE
                                  "language": {
                                      "search": "Search:", "lengthMenu": "Show _MENU_ overdue items",
                                      "info": "Showing _START_ to _END_ of _TOTAL_ overdue items", "infoEmpty": "No overdue items found",
                                      "infoFiltered": "(filtered from _MAX_ total items)", "zeroRecords": "No matching overdue items found",
                                      "paginate": { "first": "First", "last": "Last", "next": "Next", "previous": "Previous" }
                                  }
                              });
                          }
                     }, 200);
                     }
             });
        }
        
        // --- START: PENAMBAHAN FUNGSI VISUAL BARU (Professional Gauge & Chart.js) ---

        // 1. PROFESSIONAL GAUGE INITIALIZATION (CSS-Based)
        $('.professional-gauge').each(function() {
            const percent = $(this).data('percent');
            const color = $(this).data('color');
            const circumference = 360; // Total lingakaran

            // Hitung rotasi visual fill (dari 180deg ke 360deg)
            const rotation = (percent / 100) * 180 + 180; 
            
            // Terapkan CSS variables dan transformasi
            $(this).css({
                '--gauge-percent': percent,
                '--gauge-color': color
            });
            
            // KITA HANYA BOLEH SET COLOR. ROTATION KEKAL AUTONOMOUS DALAM CSS.
            $(this).get(0).style.setProperty('--gauge-color', color); 
        });

        
        // 2. CHART.JS DONUT CHART
        const donutChartElement = document.getElementById('loanDonutChart');
        if (donutChartElement) {
            
            const donutColors = ['#06b6d4', '#10b981', '#f97316', '#ef4444'];
            const totalLoans = chartValues.reduce((a, b) => a + b, 0);

            if (totalLoans > 0) {
                 new Chart(donutChartElement, {
                     type: 'doughnut',
                     data: {
                         labels: chartLabels,
                         datasets: [{
                             data: chartValues,
                             backgroundColor: donutColors,
                             hoverOffset: 4,
                             borderWidth: 1,
                             borderColor: '#ffffff'
                         }]
                     },
                     options: {
                         responsive: true,
                         maintainAspectRatio: false, 
                         plugins: {
                             legend: {
                                 display: false 
                             },
                             title: {
                                 display: false
                             },
                             tooltip: {
                                 callbacks: {
                                     label: function(context) {
                                         let label = context.label || '';
                                         if (label) { label += ': '; }
                                         if (context.parsed !== null) {
                                             label += context.parsed + ' items';
                                             if (totalLoans > 0) {
                                                 const percentage = ((context.parsed / totalLoans) * 100).toFixed(1) + '%';
                                                 label += ` (${percentage})`;
                                             }
                                         }
                                         return label;
                                     }
                                 }
                             }
                         }
                     }
                 });
            } else {
                 // Gantikan canvas dengan mesej jika tiada data
                 $(donutChartElement).parent().html('<div class="text-center p-4 text-muted">No loan data available for charting.</div>');
            }
        }
        // --- END: PENAMBAHAN FUNGSI VISUAL BARU ---

    });
</script>

<nav class="mobile-bottom-nav">
    <a href="dashboard_tech.php" class="nav-item active">
        <i class="fa-solid fa-table-columns"></i>
        <span>Dashboard</span>
    </a>
    <a href="check_out.php" class="nav-item">
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


