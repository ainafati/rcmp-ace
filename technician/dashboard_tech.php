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
        
        // Acara Tempahan
        $events[] = [
            'title' => "{$h['item_name']} ({$h['quantity']}) - {$h['username']}",
            'start' => date('Y-m-d', strtotime($h['reserve_date'])),
            'end' => date('Y-m-d', strtotime($h['return_date'] . ' +1 day')), 
            'color' => '#10b981', 
            'description' => 'Reservation'
        ];

        // Tempoh Buffer
        if ($h['status'] === 'Checked Out' && !empty($h['return_date'])) {
            $bufferStartDate = date('Y-m-d', strtotime($h['return_date'] . ' +1 day'));
            $bufferEndDate = date('Y-m-d', strtotime($h['return_date'] . ' +2 days')); 

            $events[] = [
                'title' => "Buffer: {$h['item_name']}",
                'start' => $bufferStartDate,
                'end' => $bufferEndDate,
                'color' => '#f59e0b', 
                'textColor' => '#854d0e',
                'description' => 'Buffer Period - Pending Check-in'
            ];
        }
    }
} else {
    error_log("Error fetching reservation history for calendar: " . $conn->error);
}

// --- Pemberitahuan (Tiada perubahan) ---
$tech_id = (int) $_SESSION['person_id']; 
$tech_role_id = 2; // Anda menetapkan ID peranan Juruteknik kepada 2

$sql_notif = "SELECT n.*
              FROM notifications n
              WHERE n.person_id = ? 
              AND n.recipient_role_id = ? 
              AND n.is_read = 0 
              ORDER BY n.created_at DESC 
              LIMIT 10";
              
$stmt_notif = $conn->prepare($sql_notif);

if (!$stmt_notif) {
    error_log("Notification Prepare failed: (" . $conn->errno . ") " . $conn->error);
    $new_notifications = [];
} else {
    $stmt_notif->bind_param("ii", $tech_id, $tech_role_id);
    $stmt_notif->execute();
    $result_notif = $stmt_notif->get_result();
    $new_notifications = $result_notif ? $result_notif->fetch_all(MYSQLI_ASSOC) : [];
    $stmt_notif->close();
}

$new_notif_count = count($new_notifications);

// --- Pengekodan JSON (Tiada perubahan) ---
$total_assets_details_json = json_encode($total_assets_details);
$available_assets_details_json = json_encode($available_assets_details);
$checked_out_details_json = json_encode($checked_out_details);
$overdue_details_json = json_encode($overdue_details);
$maintenance_assets_details_json = json_encode($maintenance_assets_details);
$events_json = json_encode($events);
$new_notifications_json = json_encode($new_notifications);

// --- SQL UNTUK PENDING ACTIONS DASHBOARD ---

// 1. Ambil 3 Permohonan Pinjaman Baru (Pending)
$sql_new_req = "
    SELECT i.item_name, u.name as user_name, r.created_at 
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/main.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    /* KEKALKAN SEMUA STYLES LAMA */
    :root {
        /* Warna Utama (Cyan/Teal) */
        --primary-color: #06b6d4; /* Cyan 600 (Biru Teal Gelap) */
        --primary-light: #f0f9ff; /* Biru Sangat Muda untuk Latar Belakang Aktif */
        --primary-hover: #0891b2; /* Cyan 700 */
        --danger-color: #ef4444; /* Merah untuk Logout */
        
        /* Warna Sedia Ada (Kekal Sama) */
        --bg-light-gray: #f8fafc;
        --text-dark: #1e293b; 
        --text-muted: #64748b;
    }
    
    /* BASE & TYPOGRAPHY */
    body { font-family: 'Inter', sans-serif; background-color: var(--bg-light-gray); color: #334155; min-height: 100vh; }
    h3, h5, .modal-title { font-weight: 600; color: var(--text-dark); }
    .card { border-radius: 12px; border: 1px solid #e5e7eb; }
    
    /* UTILITY COLORS (Diselaraskan) */
    .bg-primary-blue { background-color: var(--primary-color); } /* Diganti dengan Cyan */
    .text-primary-blue { color: var(--primary-color); } /* Diganti dengan Cyan */
    .text-green { color: #10b981; }
    .text-orange { color: #f97316; }
    .text-red { color: #ef4444; }
    .text-amber { color: #f59e0b; }

    /* DEFINISI SIDEBAR (MOBILE & DESKTOP) */
    .sidebar { 
        width: 250px; 
        position: fixed; 
        top: 0; 
        bottom: 0; 
        left: 0; 
        background: #ffffff; 
        padding: 20px 0; 
        border-right: 1px solid #e5e7eb; 
        display: flex; 
        flex-direction: column; 
        z-index: 1050; /* Naikkan Z-index sedikit untuk overlay/toggle */
        transition: transform 0.3s ease; /* Tambah Transisi */
    }
    .sidebar-header { padding: 0 20px; display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
    
    /* LOGO ICON: KOTAK CYAN/TEAL */
    .logo-icon { 
        width: 40px; height: 40px; 
        background-color: var(--primary-color); /* Menggunakan Cyan */
        color: white; 
        border-radius: 10px; 
        display: flex; align-items: center; justify-content: center; 
        font-size: 20px; 
    }

    /* Logo Text Styles */
    .logo-text strong { font-size: 1.1rem; font-weight: 700; color: var(--text-dark); }
    .logo-text span { font-size: 0.8rem; color: #94a3b8; font-weight: 500; }
    
    /* Sidebar Links Container */
    .sidebar-nav { margin-top: 30px; padding: 0 15px; flex-grow: 1; } 
    .sidebar-footer { padding: 0 15px; margin-top: auto; }

    .sidebar a { 
        display: flex; align-items: center; gap: 12px; 
        color: var(--text-muted); text-decoration: none; padding: 12px 15px; 
        margin-bottom: 8px; border-radius: 8px; 
        font-weight: 500; 
        transition: all 0.2s ease-in-out; 
        position: relative; 
    }

    /* ACTIVE & HOVER STYLE (Menggunakan Cyan) */
    .sidebar a.active, .sidebar a:hover:not(.logout-link) { 
        background: var(--primary-color); /* Cyan Penuh */
        color: #fff; 
    }

    /* BADGE STYLES */
    .sidebar a .badge { margin-left: auto; background-color: var(--danger-color); color: white; }
    .sidebar a.active .badge { background-color: #ffffff; color: var(--danger-color); }


    /* LOGOUT BUTTON: MERAH TEKS SAHAJA */
    .sidebar-footer a.logout-link {
        background: transparent !important; 
        color: var(--danger-color) !important; /* Warna Merah */
        border-radius: 8px;
        font-weight: 500 !important; 
        padding: 12px 15px;
        margin-top: 25px; 
        justify-content: flex-start; 
    }
    .sidebar-footer a.logout-link:hover {
        background: #fee2e2 !important; /* Latar belakang hover Merah Pudar */
        color: var(--danger-color) !important;
    }

    /* MAIN LAYOUT & TOPBAR (Kekal Sama, guna primary-color baru) */
    .main-content { margin-left: 250px; min-height: 100vh; /* Pastikan ia meliputi seluruh ketinggian */ }
    .topbar { background: #ffffff; padding: 15px 30px; display: flex; justify-content: flex-end; align-items: center; border-bottom: 1px solid #e5e7eb; z-index: 1020; }
    .container-fluid { padding: 30px; }
    .topbar h3 { margin-right: auto; font-weight: 700; color: var(--text-dark); }
    
    /* ... (CSS Summary Card, FullCalendar, Chart, Badge kekal sama) ... */
    
    .card-summary {
        background-color: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        border: 1px solid #e5e7eb;
        cursor: pointer;
        padding: 20px;
        text-align: left;
        height: 100%;
        transition: transform 0.2s, box-shadow 0.2s;
        position: relative;
    }
    .card-summary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    }
    .card-summary .icon-wrapper {
        position: absolute; top: 20px; right: 20px;
        width: 35px; height: 35px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem;
    }

    .card-summary .count { font-size: 2.5rem; font-weight: 700; line-height: 1.1; margin-bottom: 5px; }
    .card-summary .label { font-size: 0.85rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; }
    
    /* Specific Card Styling (Menggunakan primary-color baru) */
    .card-total-assets .icon-wrapper { background-color: var(--primary-light); color: var(--primary-color); }
    .card-available .icon-wrapper { background-color: #d1fae5; color: #10b981; }
    .card-checked-out .icon-wrapper { background-color: #fff7ed; color: #f97316; }
    .card-overdue .icon-wrapper { background-color: #fee2e2; color: #ef4444; }
    .card-maintenance .icon-wrapper { background-color: #fefce8; color: #f59e0b; } 
    
    /* FullCalendar Customization */
    #calendar-container { padding: 20px; }

    
    /* Chart Bar Styling */
    .loan-chart-item { margin-bottom: 15px; font-size: 0.9rem; display: flex; align-items: center; justify-content: space-between; }
    .loan-chart-item .label { width: 30%; font-weight: 500; }
    .loan-chart-item .bar-wrapper { flex-grow: 1; margin: 0 10px; position: relative; }
    .loan-chart-bar { background-color: #e5e7eb; height: 10px; border-radius: 5px; overflow: hidden; }
    .loan-chart-bar-fill { height: 100%; border-radius: 5px; transition: width 0.5s ease; }
    .loan-chart-item .value { font-weight: 600; color: var(--text-dark); width: 10%; text-align: right; }
    .loan-chart-footer { border-top: 1px solid #e5e7eb; padding-top: 15px; margin-top: 15px; display: flex; justify-content: space-between; font-weight: 600; font-size: 1rem; }
    .loan-chart-total { color: var(--text-dark); font-size: 1.2rem; }
    
    /* Chart colors (Menggunakan primary-color baru) */
    .bg-blue-fill { background-color: var(--primary-color); }
    .bg-green-fill { background-color: #10b981; }
    .bg-orange-fill { background-color: #f97316; }
    .bg-red-fill { background-color: #ef4444; }
    
    /* FullCalendar event colors */
    .fc-event-main-frame { color: white !important; }

    /* Custom badge styles for modal tables */
    .badge-status-available { background-color: #d1fae5; color: #065f46; }
    .badge-status-checked-out { background-color: #fff7ed; color: #b45309; }
    .badge-status-maintenance { background-color: #fefce8; color: #a16207; }
    .badge-status-danger { background-color: #fee2e2; color: #991b1b; }
    .badge-status-default { background-color: #e5e7eb; color: #4b5563; }
    
.fc-toolbar-chunk .fc-button {
    text-transform: capitalize !important;
}

.dropdown-menu-end {
    left: auto !important; 
    right: 0 !important;
}

#notificationList {
    width: 320px; 
    max-height: 400px; 
    overflow-y: auto; 
    overflow-x: hidden; 
}

/* Force wrap for the notification message */
.dropdown-item p {
    white-space: normal;
}

/* START: New Styles for Compact Layout */
.h-100 {
    height: 100% !important;
}
#taskSummary h6 {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-dark);
}
#taskSummary ul li {
    padding-top: 5px;
    padding-bottom: 5px;
    font-size: 0.85rem; /* Make the list items slightly smaller */
}
/* END: New Styles for Compact Layout */


@media (max-width: 991.98px) {
    
	#calendar-container {
        padding: 10px; /* Kurangkan padding kalendar */
        width: 100%;
        overflow-x: auto; /* Benarkan scroll jika FullCalendar itu sendiri terlalu lebar */
    }
	
    /* Sidebar mesti tersembunyi secara default, dan muncul apabila kelas .toggled dikeluarkan */
    .sidebar {
        /* Pindahkan sidebar ke luar skrin secara default pada skrin kecil */
        transform: translateX(-250px); 
        transition: transform 0.3s ease;
        z-index: 1050; /* Pastikan ia berada di atas segala-galanya apabila dibuka */
    }

    /* Keadaan apabila toggle diklik (sidebar tersembunyi) */
    .sidebar.toggled {
        /* Kembali ke kedudukan asal (tersembunyi) */
        transform: translateX(-250px); 
    }
    
    /* Keadaan apabila toggle diklik (sidebar dipaparkan) */
    .sidebar:not(.toggled) {
        transform: translateX(0); /* Alihkan ke skrin */
    }

    /* 2. Main Content mesti mengambil lebar penuh pada Mobile */
    .main-content {
        /* Buang margin kiri yang digunakan pada desktop */
        margin-left: 0; 
        width: 100%; /* Lebar penuh */
    }

    /* 3. Sidebar Overlay (Jika anda mempunyai elemen overlay) */
    #sidebarOverlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1040; /* Di bawah sidebar, di atas main-content */
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
    }
    
    #sidebarOverlay.active {
        opacity: 1;
        pointer-events: auto;
    }
    
    /* 4. Topbar Button & Heading Adjustment */
    .topbar {
        padding-left: 15px; /* Kurangkan padding pada mobile */
        padding-right: 15px;
        z-index: 1030; /* Pastikan topbar berada di atas main content */
    }
    
    .topbar h3 {
        font-size: 1.2rem; /* Kecilkan tajuk */
    }

    /* 5. Kurangkan padding container-fluid */
    .container-fluid {
        padding: 15px;
    }
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
    <button id="sidebarToggle" class="btn btn-link d-lg-none" type="button">
        <i class="fas fa-bars fa-lg"></i>
    </button>

    <h3>Dashboard</h3> 

    <div class="topbar-right">
        </div>

        <div class="technician-profile d-flex align-items-center">
<span class="user-name me-2" style="text-transform: capitalize; font-weight: 600;">
    <?= htmlspecialchars($displayName) ?>
</span>            
			
			<div class="dropdown me-3" style="position: relative;">
                <button class="btn btn-link text-secondary p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-bell fa-xl"></i>
                    <?php if (($new_notif_count ?? 0) > 0): ?>
                        <span class="position-absolute translate-middle badge rounded-circle bg-danger border border-light p-1" style="top: 2px; right: -5px; font-size: 0.6em; z-index: 1001;" id="notif-count-badge">
                                    <?= $new_notif_count ?>
                        </span>
                    <?php endif; ?>
                </button>
							<ul class="dropdown-menu dropdown-menu-end" 
                            style="width: 320px; max-height: 400px; overflow-y: auto; overflow-x: hidden;" 
                            id="notificationList">
							<h6 class="dropdown-header">Notifications (<span id="notif-count-header"><?= $new_notif_count ?? 0 ?></span> New)</h6>
                    <?php if (($new_notif_count ?? 0) > 0): ?>
                        <?php foreach(($new_notifications ?? []) as $notif): 
                            $icon = ($notif['type'] == 'reject' || $notif['type'] == 'reservation_rejected') ? 'fa-times-circle text-danger' : 'fa-check-circle text-success';
                        ?>
                            <li class="notif-item" data-id="<?= $notif['id'] ?>">
                                <a class="dropdown-item" href="#">
                                    <div class="d-flex align-items-start">
                                        <i class="fa-solid <?= $icon ?> me-2 mt-1 fa-lg"></i>
                                        <div>
                                            <p class="mb-0 small fw-bold text-wrap"><?= htmlspecialchars($notif['message']) ?></p>
                                            <small class="text-muted"><?= date('H:i, d M', strtotime($notif['created_at'])) ?></small>
                                        </div>
                                    </div>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                        <?php endforeach; ?>
                        <li><a class="dropdown-item text-center small text-primary" href="#" id="markAllRead">Mark all as read</a></li>
                    <?php else: ?>
                        <li><span class="dropdown-item text-center text-muted" id="no-notif-message">No new notifications.</span></li>
                    <?php endif; ?>
                </ul>
            </div>
            <a href="profile_tech.php" title="My Profile" class="text-secondary" style="text-decoration: none;">
                <i class="fa-solid fa-circle-user fa-2x text-primary-blue"></i>
            </a>
        </div>
    </div>
    <div class="container-fluid">
        <div class="row g-3">
            
            <div class="col-xl-2 col-lg-4 col-md-4 col-sm-6">
                <div class="card card-summary card-total-assets" id="totalAssetsCard" data-bs-toggle="modal" data-bs-target="#totalAssetsModal">
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
        <div class="row mt-4 g-4">
            
            <div class="col-12">
                <div class="card p-4">
                    <h5 class="mb-3"><i class="fa-solid fa-calendar-days text-primary-blue me-2"></i> Reservation Calendar</h5>
                    <div id="calendar-container">
                        <div id="calendar"></div>
                    </div>
                    <div class="calendar-legend mt-3">
                        <div class="d-flex">
                            <div class="me-3"><span class="badge" style="background-color: #10b981;">&nbsp;</span> Reservations</div>
                            <div><span class="badge" style="background-color: #f59e0b;">&nbsp;</span> Buffer Timeline</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 col-12">
                <div class="card p-4 h-100" id="pendingActionsCard">
                    <h5 class="mb-4"><i class="fa-solid fa-list-check text-red me-2"></i> Pending Actions</h5>
                    
                    <div id="taskSummary">
                        
                        <?php
                        
                        if (!empty($new_requests_data)): ?>
                            <h6><i class="fa-solid fa-bell-concierge text-primary-blue me-1"></i> New Loan Requests (<?= count($new_requests_data) ?>)</h6>
                            <ul class="list-unstyled small mb-3 border-bottom pb-2">
                            <?php foreach (array_slice($new_requests_data, 0, 3) as $req): ?>
                                <li class="d-flex justify-content-between">
                                    <span><?= htmlspecialchars($req['item_name']) ?> by <?= htmlspecialchars($req['user_name']) ?></span>
                                    <span class="text-muted"><?= htmlspecialchars($req['time_ago']) ?></span>
                                </li>
                            <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <h6><i class="fa-solid fa-bell-concierge text-primary-blue me-1"></i> New Loan Requests (0)</h6>
                            <p class="small text-muted mb-4 border-bottom pb-2">No new requests pending approval.</p>
                        <?php endif; ?>

                        <?php if (!empty($returns_today_data)): ?>
                            <h6><i class="fa-solid fa-rotate-left text-green me-1"></i> Scheduled Returns Today (<?= count($returns_today_data) ?>)</h6>
                            <ul class="list-unstyled small mb-3 border-bottom pb-2">
                            <?php foreach (array_slice($returns_today_data, 0, 3) as $ret): ?>
                                <li class="d-flex justify-content-between">
                                    <span><?= htmlspecialchars($ret['asset_code']) ?> (<?= htmlspecialchars($ret['user_name']) ?>)</span>
                                    <span class="text-green fw-bold"><?= htmlspecialchars($ret['return_time']) ?></span>
                                </li>
                            <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <h6><i class="fa-solid fa-rotate-left text-green me-1"></i> Scheduled Returns Today (0)</h6>
                            <p class="small text-muted mb-4 border-bottom pb-2">No returns scheduled for today.</p>
                        <?php endif; ?>

                        <?php if (!empty($top_overdue_data)): ?>
                            <h6><i class="fa-solid fa-clock-rotate-left text-red me-1"></i> Top Overdue Items</h6>
                            <ul class="list-unstyled small mb-1">
                            <?php foreach (array_slice($top_overdue_data, 0, 3) as $overdue): ?>
                                <li class="d-flex justify-content-between">
                                    <span><?= htmlspecialchars($overdue['item_name']) ?></span>
                                    <span class="badge rounded-pill bg-danger"><?= htmlspecialchars($overdue['days_overdue']) ?> days</span>
                                </li>
                            <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <h6><i class="fa-solid fa-clock-rotate-left text-red me-1"></i> Top Overdue Items (0)</h6>
                            <p class="small text-muted mb-1">No critically overdue items.</p>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 col-12">
                <div class="card p-4 h-100">
                    <h5 class="mb-4"><i class="fa-solid fa-chart-pie text-primary-blue me-2"></i> Loan Distribution</h5>
                    
                    <div id="loanChartContainer">
                        <?php 
                        // KEKALKAN KOD PHP BAR CHART ANDA DI SINI
                        $colors_fill = ['bg-blue-fill', 'bg-green-fill', 'bg-orange-fill', 'bg-red-fill'];
                        $chart_index = 0;
                        if ($totalLoans == 0): ?>
                            <div class="text-center p-4 text-muted">No items have been checked out yet.</div>
                        <?php else:
                            foreach ($chartLabels as $index => $label):
                                $value = $chartValues[$index];
                                $percentage = round(($value / $totalLoans) * 100);
                                $colorClass = $colors_fill[$chart_index % count($colors_fill)];
                                $chart_index++;
                        ?>
                        <div class="loan-chart-item">
                            <div class="label"><?= htmlspecialchars($label) ?></div>
                            <div class="bar-wrapper">
                                <div class="loan-chart-bar">
                                    <div class="loan-chart-bar-fill <?= $colorClass ?>" style="width: <?= $percentage ?>%;"></div>
                                </div>
                            </div>
                            <div class="value"><?= $value ?></div>
                        </div>
                        <?php endforeach; 
                        endif; ?>
                    </div>
                    
                    <div class="loan-chart-footer">
                        <span>Total Loans</span>
                        <span class="loan-chart-total"><?= $totalLoans ?></span>
                    </div>
                </div>
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
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> 
<script>

    // KEKALKAN SEMUA DATA PHP JSON DARI PART 3
    const totalAssetsDetails = <?php echo $total_assets_details_json; ?>;
    const availableAssetsDetails = <?php echo $available_assets_details_json; ?>;
    const checkedOutAssetsDetails = <?php echo $checked_out_details_json; ?>;
    const overdueDetails = <?php echo $overdue_details_json; ?>;
    const maintenanceAssetsDetails = <?php echo $maintenance_assets_details_json; ?>;
    const eventsData = <?php echo $events_json; ?>;

    const newNotifications = <?php echo $new_notifications_json; ?>;
    let currentNewCount = <?php echo $new_notif_count; ?>;
    
    // --- START: PENAMBAHAN UNTUK CHART.JS (HARUS DEFINED DALAM BLOK PHP) ---
    const chartLabels = <?php echo json_encode($chartLabels ?? []); ?>;
    const chartValues = <?php echo json_encode($chartValues ?? []); ?>;
    // --- END: PENAMBAHAN UNTUK CHART.JS ---


    document.addEventListener('DOMContentLoaded', function() {

        
        if (typeof jQuery === 'undefined') {
            console.error("jQuery is not loaded. Notification and DataTables functions will fail.");
            return;
        }

        
        // --- NOTIFICATION HANDLERS ---
        const $notifBadge = $('#notif-count-badge');
        const $notifHeaderCount = $('#notif-count-header');
        const $notifList = $('#notificationList');
        
        updateNotificationCount(currentNewCount, false); 

        
        function renderNotifications(notifications) {
            $notifList.empty();
            if (notifications.length === 0) {
                $notifList.append('<li><span class="dropdown-item text-center text-muted" id="no-notif-message">No new notifications.</span></li>');
                return;
            }
            
            
            $('#no-notif-message').parent().remove();

            notifications.forEach((notif, index) => {
                
                const createdDate = new Date(notif.created_at);
                const timeStr = createdDate.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });

                const item = `<li class="notif-item dropdown-item dropdown-item-unread" data-id="${notif.id}">
                    <div class="d-flex align-items-center">
                        <i class="fa-solid fa-bell me-3 text-primary"></i>
                        <div class="flex-grow-1">
                            <h6 class="mb-0 fw-bold">${notif.type.replace('_', ' ')}</h6>
                            <p class="text-truncate mb-0">${notif.message}</p>
                            <small class="text-muted">${timeStr}</small>
                        </div>
                    </div>
                </li>`;
                $notifList.append(item);
                
                
                if (index < notifications.length - 1) {
                    $notifList.append('<li class="dropdown-divider"></li>');
                }
            });
            
            
             $notifList.append('<li><hr class="dropdown-divider"></li>');
             $notifList.append('<li><a class="dropdown-item text-center text-success" href="#" id="markAllRead"><i class="fa-solid fa-check-double me-1"></i> Mark all as read</a></li>');
        }

        
        function updateNotificationCount(newCount, redraw = true) {
            currentNewCount = newCount;
            
            
            if (newCount > 0) {
                $notifBadge.text(newCount).removeClass('d-none').addClass('position-absolute');
            } else {
                $notifBadge.addClass('d-none').removeClass('position-absolute');
            }

            
            $notifHeaderCount.text(newCount);
            
            
            if (newCount === 0 && redraw) {
                
                $notifList.empty();
                $notifList.append('<li><span class="dropdown-item text-center text-muted" id="no-notif-message">No new notifications.</span></li>');
            }
        }

        
        function markNotificationAsRead(id, successCallback) {
            const url = 'update_notification_status.php'; 
            
            $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                data: { id: id },
                success: function(response) {
                    if (response.status === 'success') {
                        
                        if (successCallback) {
                            successCallback();
                        }
                    } else {
                        console.error("Error updating status:", response.message);
                        Swal.fire('Error', 'Failed to update notification status on server.', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    Swal.fire('Error', 'Server communication error. Please try again.', 'error');
                }
            });
        }
        
        
        renderNotifications(newNotifications);


        
        
        
        $notifList.on('click', 'li.notif-item', function(e) {
            e.preventDefault();
            const notifId = $(this).data('id');
            const $item = $(this);

            if ($item.hasClass('dropdown-item-unread')) {
                markNotificationAsRead(notifId, function() {
                    $item.removeClass('dropdown-item-unread').addClass('dropdown-item-read');
                    
                    
                    $item.next('.dropdown-divider').remove(); 
                    $item.remove();
                    updateNotificationCount(currentNewCount - 1); 
                });
            }
        });

        
        $notifList.on('click', '#markAllRead', function(e) {
            e.preventDefault();
            
            if (currentNewCount === 0) {
                Swal.fire('No New Notifications', 'There are no new notifications to mark as read.', 'info');
                return;
            }

            Swal.fire({
                title: 'Confirm',
                text: "Mark all " + currentNewCount + " notifications as read?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Mark All'
            }).then((result) => {
                if (result.isConfirmed) {
                    markNotificationAsRead('all', function() {
                        updateNotificationCount(0, true); 
                        Swal.fire('Done!', 'All notifications marked as read.', 'success');
                    });
                }
            });
        });
        
        
        // --- SIDEBAR TOGGLE (KEMASKINI DENGAN LOGIK MOBILE) ---
        const sidebar = document.getElementById('admin-sidebar');
        const toggleBtn = document.getElementById('sidebarToggle');
        const overlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            sidebar.classList.toggle('toggled');
            overlay.classList.toggle('active');
        }
        if (toggleBtn) { toggleBtn.addEventListener('click', toggleSidebar); }
        if (overlay) { overlay.addEventListener('click', toggleSidebar); }

function checkScreenSize() {
     const sidebar = document.getElementById('admin-sidebar'); // Pastikan elemen didefinisikan atau diakses di sini
     const overlay = document.getElementById('sidebarOverlay'); // Pastikan elemen didefinisikan atau diakses di sini
     
     if (window.innerWidth > 991.98) {
         // Desktop: Pastikan sidebar kelihatan (default)
         sidebar.classList.remove('toggled');
         overlay.classList.remove('active');
     } else {
         // Mobile: Pastikan sidebar tersembunyi
         // KELAS 'toggled' MESTI MENGANDUNGI CSS UNTUK MENYEMBUNYIKAN SIDEBAR
         sidebar.classList.add('toggled'); 
         overlay.classList.remove('active');
     }
}

window.addEventListener('resize', checkScreenSize);
checkScreenSize(); // Ini MESTI dijalankan semasa muatan

        // --- FULLCALENDAR INITIALIZATION ---
        const calendarEl = document.getElementById('calendar');
        if (calendarEl) {
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                events: eventsData,
                height: 'auto',
                firstDay: 1,

                eventDidMount: function(info) {
                    if (info.event.extendedProps.description === 'Reservation') {
                        info.el.style.backgroundColor = '#10b981';
                        info.el.style.borderColor = '#059669';
                        info.el.style.color = 'white';
                    } else if (info.event.extendedProps.description === 'Buffer Timeline - Pending Check-in') {
                        info.el.style.backgroundColor = '#f59e0b';
                        info.el.style.borderColor = '#d97706';
                        info.el.style.color = 'white';
                    }
                },
                eventContent: function(arg) {

                    return { html: '<div class="fc-event-main-frame">' + arg.event.title + '</div>' };
                }

            });
            calendar.render();
        } else { console.error("Calendar element #calendar not found."); }


        
        // --- DATATABLES & MODAL SETUP FUNCTIONS (Sertakan responsive: true) ---
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
                let statusBadgeClass = 'badge-status-default';

                if (statusValue === 'Available') statusBadgeClass = 'badge-status-available';
                else if (statusValue === 'Checked Out') statusBadgeClass = 'badge-status-checked-out';
                else if (statusValue === 'Maintenance') statusBadgeClass = 'badge-status-maintenance';
                else if (statusValue === 'Broken' || statusValue === 'Decommissioned' || statusValue === 'Missing') statusBadgeClass = 'badge-status-danger';

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
                
                $modal.on('hidden.bs.modal', function () {
                    const existingTable = listContainer.querySelector('.asset-detail-table');
                    if (existingTable && $.fn.DataTable.isDataTable(existingTable)) { $(existingTable).DataTable().destroy(); }
                    listContainer.innerHTML = '';
                    });

                $(card).on('click', function() {
                    const tableData = createAssetTableHTML(dataList, includeUser);
                    listContainer.innerHTML = tableData.html;
                    $modal.modal('show');

                    setTimeout(() => {
                        const newTable = $(`#${tableData.id}`);
                        if (newTable.length) {
                            newTable.DataTable({
                                "pageLength": 10, 
                                "order": [], 
                                "destroy": true,
                                "responsive": true, // <-- PENAMBAHAN UNTUK MOBILE
                                "language": {
                                    "search": "Search:", "lengthMenu": "Show _MENU_ assets",
                                    "info": "Showing _START_ to _END_ of _TOTAL_ assets", "infoEmpty": "No assets found",
                                    "infoFiltered": "(filtered from _MAX_ total assets)", "zeroRecords": "No matching assets found",
                                    "paginate": { "first": "First", "last": "Last", "next": "Next", "previous": "Previous" }
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
</body>
</html>