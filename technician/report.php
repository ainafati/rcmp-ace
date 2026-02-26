<?php
session_start();
include '../config.php'; 

function get_pending_count($conn) {
    $sql = "SELECT COUNT(id) AS total FROM reservation_items WHERE LOWER(TRIM(status)) = 'pending'";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        return (int)$row['total'];
    }
    return 0;
}

if (!isset($_SESSION['person_id'])) {
    header("Location: ../login.php");
    exit();
}
$person_id = (int)$_SESSION['person_id'];

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. Tarik Data Technician
$stmt = $conn->prepare("SELECT name, email FROM person WHERE person_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $person_id);
    $stmt->execute();
    $tech = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$tech) {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// 3. Logik Nama Pendek
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

function get_reservation_item_count($conn, $status) {
    $sql = "SELECT COUNT(id) AS count FROM reservation_items WHERE status = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    $stmt->bind_param("s", $status);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result ? (int) $result['count'] : 0;
}

$pending_count_for_badge = get_reservation_item_count($conn, 'Pending');

$categories_result = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
$categories = $categories_result ? $categories_result->fetch_all(MYSQLI_ASSOC) : [];

// FILTER INPUT
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01');
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-t');
$category_filter_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
$asset_filter_code = isset($_POST['asset_code']) ? trim($_POST['asset_code']) : ''; 
$status_filter = isset($_POST['status_filter']) ? $_POST['status_filter'] : 'Returned';

$current_month = date('m', strtotime($start_date));
$current_year = date('Y', strtotime($start_date));

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// SQL BASE
$sql_base_select = "SELECT 
             u.name AS user_name, i.item_name, a.asset_code, c.category_name, 
             r.reserve_date, r.return_date, ri.return_condition,
             approver.name AS approved_by_name, 
             checkout.name AS checked_out_by_name, 
             checkin.name AS checked_in_by_name";
             
$sql_base_from = " FROM reservation_items ri
             JOIN reservations r ON ri.reserve_id = r.reserve_id
             JOIN person u ON r.person_id = u.person_id             
             JOIN item i ON ri.item_id = i.item_id
             JOIN categories c ON i.category_id = c.category_id
             LEFT JOIN person approver ON ri.approved_by = approver.person_id 
             LEFT JOIN person checkout ON ri.checked_out_by = checkout.person_id 
             LEFT JOIN person checkin ON ri.checked_in_by = checkin.person_id 
             LEFT JOIN reservation_assets ra ON ri.id = ra.reservation_item_id
             LEFT JOIN assets a ON ra.asset_id = a.asset_id";

// Tentukan lajur tarikh mana nak guna
$date_column = ($status_filter === 'Returned') ? "r.return_date" : "r.reserve_date";

// BINA WHERE CLAUSE
$sql_where_clauses = [
    "ri.status = ?", 
    "$date_column BETWEEN ? AND ?" 
];
$param_types = "sss"; 
$param_values = [$status_filter, $start_date, $end_date];

if ($category_filter_id > 0) {
    $sql_where_clauses[] = "i.category_id = ?";
    $param_types .= "i";
    $param_values[] = $category_filter_id;
}

if (!empty($asset_filter_code)) {
    $sql_where_clauses[] = "a.asset_code = ?";
    $param_types .= "s";
    $param_values[] = $asset_filter_code;
}

// *** CRITICAL FIX: Bina string $sql_where ***
$sql_where = " WHERE " . implode(" AND ", $sql_where_clauses);

// 1. COUNT UNTUK PAGINATION
$sql_count = "SELECT COUNT(ri.id) AS total" . $sql_base_from . $sql_where;
$stmt_count = $conn->prepare($sql_count);
if ($stmt_count === false) { die("SQL Error (Count): " . $conn->error); }

$stmt_count->bind_param($param_types, ...$param_values);
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);
$stmt_count->close();

// 2. FETCH DATA SEBENAR
$sql = $sql_base_select . $sql_base_from . $sql_where . " ORDER BY $date_column DESC, a.asset_code ASC LIMIT ?, ?";
$final_param_types = $param_types . "ii";
$final_param_values = array_merge($param_values, [$start, $limit]);

$stmt = $conn->prepare($sql);
if ($stmt === false) { die("SQL Error (Fetch): " . $conn->error); }

$stmt->bind_param($final_param_types, ...$final_param_values);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

$pagination_params = http_build_query([
    'start_date' => $start_date,
    'end_date' => $end_date,
    'category_id' => $category_filter_id,
    'asset_code' => $asset_filter_code,
    'status_filter' => $status_filter
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Returned Items Report — UniKL Technician</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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

:root {
    --bg-main: #f4f7fe; /* Warna background dashboard soft blue */
    --card-shadow: 0 10px 30px rgba(132, 147, 168, 0.15);
    --primary-text: #1b2559;
}

body {
    background-color: var(--bg-main);
    font-family: 'DM Sans', 'Inter', sans-serif; /* Guna font lebih rounded */
    color: var(--primary-text);
}

/* --- Container Utama (Glass Card) --- */
.card {
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(10px); /* Efek glassmorphism */
    border: none;
    border-radius: 25px; /* Lebih bulat macam dalam gambar b17eff.png */
    box-shadow: var(--card-shadow);
    padding: 30px !important;
    margin-bottom: 25px;
}

/* --- Input & Filter Section --- */
.form-select, .form-control {
    background: #ffffff !important;
    border: 1px solid #e0e5f2 !important;
    border-radius: 16px; /* Pill shape */
    padding: 12px 16px;
    font-weight: 500;
    color: var(--primary-text);
}

/* --- Table Styling (Floating Rows) --- */
.table {
    border-collapse: separate;
    border-spacing: 0 12px; /* Jarakkan row supaya nampak terapung */
}

.table thead th {
    border: none;
    color: #a3aed0; /* Warna kelabu muted untuk header */
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 15px;
}

.table tbody tr {
    background-color: #ffffff;
    border-radius: 20px;
    transition: transform 0.2s ease;
    box-shadow: 0 4px 10px rgba(0,0,0,0.02);
}

.table tbody tr:hover {
    transform: scale(1.01); /* Efek pop-up bila hover */
    background-color: #ffffff !important;
}

.table td {
    padding: 20px 15px !important;
    border: none !important;
}

/* Round kan bucu row */
.table td:first-child { border-radius: 20px 0 0 20px; }
.table td:last-child { border-radius: 0 20px 20px 0; }

/* --- Badge Status (Modern Pill) --- */
.badge {
    padding: 8px 16px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 0.75rem;
}

.badge.bg-success { background-color: #05cd991a !important; color: #05cd99 !important; }
.badge.bg-danger { background-color: #ee5d501a !important; color: #ee5d50 !important; }

/* --- Button Export (Macam image_e626b9.png) --- */
.btn-export {
    border-radius: 14px;
    padding: 8px 20px;
    font-weight: 600;
    border: 1px solid #e0e5f2;
    background: #ffffff;
    transition: 0.3s;
}

.btn-export-pdf { color: #ee5d50; border-color: #fdd8d5; }
.btn-export-excel { color: #05cd99; border-color: #bdf3e4; }
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

</style>

</head>
<body>


<div class="sidebar" id="admin-sidebar">
    <div>
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fa-solid fa-wrench"></i></div>
            <div class="logo-text">
                <strong>UniKL Technician</strong><br>
                <span>System Support</span>
            </div>
        </div>

        <div class="sidebar-nav">
            <a href="dashboard_tech.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'dashboard_tech.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-table-columns"></i> Dashboard
            </a>

<a href="check_out.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'check_out.php') ? 'active' : '' ?>">
    <i class="fa-solid fa-dolly"></i> 
    <span class="me-auto">Manage Requests</span> <?php if (isset($pending_count_for_badge) && $pending_count_for_badge > 0): ?>
        <span class="badge rounded-pill bg-danger" style="font-size: 0.7rem; margin-left: 5px;">
            <?= $pending_count_for_badge ?>
        </span>
    <?php endif; ?>
</a>
            <a href="manageItem_tech.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'manageItem_tech.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-box-archive"></i> Manage Items
            </a>

            <a href="report.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'report.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-line"></i> Report
            </a>
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
            <h3 class="mb-0">Returned Item Report</h3>
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
        <div class="card p-4 mb-4">
            <h5 class="mb-3"><i class="fa-solid fa-filter me-2"></i>Filter Report Data</h5>

            <form method="POST" action="report.php" id="reportForm">
                <input type="hidden" id="start_date_hidden" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                <input type="hidden" id="end_date_hidden" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                
                <div class="row g-3 mb-3">
                    <div class="col-md-3 col-6">
                        <label for="month_select" class="form-label fw-bold">Select Month</label>
                        <select id="month_select" class="form-select">
                            <?php for ($m = 1; $m <= 12; $m++) {
                                $month_name = date('F', mktime(0, 0, 0, $m, 1));
                                $selected = ($m == $current_month) ? 'selected' : '';
                                
                                echo "<option value='" . str_pad($m, 2, '0', STR_PAD_LEFT) . "' $selected>$month_name</option>";
                            } ?>
                        </select>
                    </div>
                    <div class="col-md-3 col-6">
                        <label for="year_select" class="form-label fw-bold">Select Year</label>
                        <select id="year_select" class="form-select">
                            <?php $start_year = date('Y') - 5; $end_year = date('Y');
                            for ($y = $end_year; $y >= $start_year; $y--) {
                                $selected = ($y == $current_year) ? 'selected' : '';
                                echo "<option value='$y' $selected>$y</option>";
                            } ?>
                        </select>
                    </div>

                    <div class="col-md-3 col-6">
                        <label for="category_filter" class="form-label fw-bold">Filter by Category</label>
                        <select id="category_filter" name="category_id" class="form-select">
                            <option value="0">All Categories</option>
                            <?php if (!empty($categories)): foreach($categories as $cat): ?>
                                <option value="<?= $cat['category_id'] ?>" <?= ($cat['category_id'] == $category_filter_id) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 col-6">
                        <label for="asset_code_filter" class="form-label fw-bold">Filter by Asset Code</label>
                        <input type="text" id="asset_code_filter" name="asset_code" class="form-control" 
                            value="<?= htmlspecialchars($asset_filter_code) ?>" placeholder="e.g., LCT-001">
                    </div>

<div class="col-md-3 col-6">
    <label for="status_filter" class="form-label fw-bold">Report Type / Status</label>
    <select id="status_filter" name="status_filter" class="form-select">
        <option value="Returned" <?= ($status_filter == 'Returned') ? 'selected' : '' ?>>1. Returned Items</option>
        <option value="Cancelled" <?= ($status_filter == 'Cancelled') ? 'selected' : '' ?>>2. User Cancelled</option>
        <option value="Voided" <?= ($status_filter == 'Voided') ? 'selected' : '' ?>>3. Tech Rejected (Voided)</option>
        <option value="Expired" <?= ($status_filter == 'Expired') ? 'selected' : '' ?>>4. Unclaimed (Expired)</option>
    </select>
</div>                </div>
                <hr>

                <div class="row g-3 align-items-end">
                    <div class="col-md-4 col-12">
                        <label for="start_date_display" class="form-label fw-bold">Start Date</label>
                        <input type="text" id="start_date_display" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                    <div class="col-md-4 col-12">
                        <label for="end_date_display" class="form-label fw-bold">End Date</label>
                        <input type="text" id="end_date_display" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                    <div class="col-md-4 col-12">
                        <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-arrows-rotate me-2"></i>Apply Filters</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold" style="color: var(--primary-text);">Returned Items 
        <span class="text-muted fw-normal" style="font-size: 0.9rem;">(<?= $total_records ?> found)</span>
    </h5>
    <div>
    <a href="generate_pdf.php?<?= $pagination_params ?>" target="_blank" class="btn btn-export btn-export-pdf me-2">
        <i class="fa-solid fa-file-pdf me-2"></i>PDF
    </a>
    <a href="export_excel_tech.php?<?= $pagination_params ?>" target="_blank" class="btn btn-export btn-export-excel">
        <i class="fa-solid fa-file-excel me-2"></i>Excel
    </a>
</div>
</div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                   
                   <thead>
    <tr>
        <th>User</th>
        <th>Item Details & Status</th> 
        <th class="d-none d-md-table-cell">Category</th>
        <th class="d-none d-lg-table-cell">Reserve Date</th>
        <th><?= ($status_filter === 'Returned') ? 'Return Date' : 'Status Date' ?></th>
        <th class="d-none d-lg-table-cell">Approved By</th>
        <?php if ($status_filter === 'Returned'): ?>
            <th class="d-none d-lg-table-cell">Check Out By</th>
            <th class="d-none d-lg-table-cell">Check In By</th>
        <?php endif; ?>
    </tr>
</thead>
<tbody>
    <?php if (empty($records)): ?>
        <tr><td colspan="8" class="text-center text-muted py-5">No records found for <?= htmlspecialchars($status_filter) ?>.</td></tr>
    <?php else: foreach ($records as $record): ?>
        <tr>
            <td><?= htmlspecialchars($record['user_name']) ?></td>
            <td>
                <strong><?= htmlspecialchars($record['item_name']) ?></strong>
                <small class="text-muted d-block">Asset: <?= htmlspecialchars($record['asset_code'] ?: 'N/A') ?></small>
                
                <?php if ($status_filter === 'Returned'): ?>
                    <span class="badge bg-success">Returned</span>
                <?php elseif ($status_filter === 'Cancelled'): ?>
                    <span class="badge bg-secondary">Cancelled</span>
                <?php elseif ($status_filter === 'Voided'): ?>
                    <span class="badge bg-danger">Rejected</span>
                <?php elseif ($status_filter === 'Expired'): ?>
                    <span class="badge bg-warning text-dark">Expired</span>
                <?php endif; ?>
            </td>
            
            <td class="d-none d-md-table-cell"><?= htmlspecialchars($record['category_name']) ?></td>
            <td class="d-none d-lg-table-cell"><?= date("d M Y", strtotime($record['reserve_date'])) ?></td>
            <td>
                <?php 
                    $displayDate = ($status_filter === 'Returned') ? $record['return_date'] : $record['reserve_date'];
                    echo date("d M Y", strtotime($displayDate)); 
                ?>
            </td>
            <td class="d-none d-lg-table-cell"><?= htmlspecialchars($record['approved_by_name'] ?: 'N/A') ?></td>
            
            <?php if ($status_filter === 'Returned'): ?>
                <td class="d-none d-lg-table-cell"><?= htmlspecialchars($record['checked_out_by_name'] ?: 'N/A') ?></td>
                <td class="d-none d-lg-table-cell"><?= htmlspecialchars($record['checked_in_by_name'] ?: 'N/A') ?></td>
            <?php endif; ?>
        </tr>
    <?php endforeach; endif; ?>
</tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&<?= $pagination_params ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>

                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);

                        if ($start_page > 1) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }

                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&<?= $pagination_params ?>"><?= $i ?></a>
                        </li>
                        <?php 
                        endfor; 
                        
                        if ($end_page < $total_pages) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
                        ?>

                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&<?= $pagination_params ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    
    const startDateDisplay = document.getElementById('start_date_display');
    const endDateDisplay = document.getElementById('end_date_display');
    const startDateHidden = document.getElementById('start_date_hidden');
    const endDateHidden = document.getElementById('end_date_hidden');

    
    flatpickr(startDateDisplay, { 
        dateFormat: "Y-m-d",
        onChange: function(selectedDates, dateStr) {
            startDateHidden.value = dateStr; 
        }
    });
    
    flatpickr(endDateDisplay, { 
        dateFormat: "Y-m-d",
        onChange: function(selectedDates, dateStr) {
            endDateHidden.value = dateStr; 
        }
    });

    const monthSelect = document.getElementById('month_select');
    const yearSelect = document.getElementById('year_select');
    const reportForm = document.getElementById('reportForm');
    const categoryFilter = document.getElementById('category_filter');

    
    const sidebar = document.getElementById('offcanvasSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const backdrop = document.getElementById('sidebar-backdrop');
    const body = document.body;

    function toggleSidebar() {
        body.classList.toggle('offcanvas-open');
        if (body.classList.contains('offcanvas-open')) {
            backdrop.style.display = 'block';
        } else {
            setTimeout(() => {
                backdrop.style.display = 'none';
            }, 300);
        }
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', toggleSidebar);
    }
    
    if (backdrop) {
        backdrop.addEventListener('click', toggleSidebar);
    }
    
    
    function updateDateInputs() {
        if (!yearSelect || !monthSelect || !startDateHidden || !endDateHidden) return; 

        const year = yearSelect.value;
        const month = monthSelect.value;
        
        const lastDay = new Date(year, month, 0).getDate(); 
        
        const startDate = `${year}-${month}-01`;
        const endDate = `${year}-${month}-${lastDay}`;

        
        startDateDisplay._flatpickr.setDate(startDate, true); 
        endDateDisplay._flatpickr.setDate(endDate, true); 
        
        
        startDateHidden.value = startDate;
        endDateHidden.value = endDate;
    }

    function handleFilterChange(event) {
        
        
        if (event.target === monthSelect || event.target === yearSelect) {
            updateDateInputs();
        }

        
        if (event.target === monthSelect || event.target === yearSelect || event.target === categoryFilter) {
            reportForm.submit();
        } 
    }
    
    
    if (monthSelect) monthSelect.addEventListener('change', handleFilterChange);
    if (yearSelect) yearSelect.addEventListener('change', handleFilterChange);
    if (categoryFilter) categoryFilter.addEventListener('change', handleFilterChange);
    
</script>

<nav class="mobile-bottom-nav">
    <a href="dashboard_user.php" class="nav-item">
        <i class="fa-solid fa-table-columns"></i>
        <span>Dashboard</span>
    </a>
    <a href="check_out.php" class="nav-item">
        <i class="fa-solid fa-dolly"></i>
        <span> Manage Requests</span>
    </a>
    <a href="manageItem_tech.php"><i class="fa-solid fa-box-archive"></i> Manage Items</a>
            <a href="report.php" class="nav-item active"><i class="fa-solid fa-chart-line"></i> Report</a>
    <a href="profile_tech.php" class="nav-item">
        <i class="fa-solid fa-user"></i>
        <span>Profile</span>
    </a>
</nav></body>
</html>


