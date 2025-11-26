<?php
session_start();


include '../config.php';


if (!isset($_SESSION['person_id'])) {
    header("Location: ../login.php");
    exit();
}

$person_id = (int) $_SESSION['person_id'];


$user = null; 
$stmt_user = $conn->prepare("SELECT name, email, phoneNum FROM person WHERE person_id = ?");

if ($stmt_user) {
    $stmt_user->bind_param("i", $person_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    $user = $result_user->fetch_assoc(); 
    $stmt_user->close();
} else {
    
    error_log("Failed to prepare user statement: " . $conn->error);
}

if (!$user) { 
    session_destroy();
    header("Location: ../login.php"); 
    exit();
}





$rowsPerPage = 10;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;
$offset = ($currentPage - 1) * $rowsPerPage;
$totalRows = 0;
$totalPages = 0;


$sql_count = "SELECT COUNT(ri.id)
              FROM reservations r
              JOIN reservation_items ri ON r.reserve_id = ri.reserve_id
              WHERE r.person_id = ?";
if ($stmt_count = $conn->prepare($sql_count)) {
    $stmt_count->bind_param("i", $person_id);
    $stmt_count->execute();
    $stmt_count->bind_result($totalRows);
    $stmt_count->fetch();
    $stmt_count->close();
    $totalPages = ceil($totalRows / $rowsPerPage);
    
    
    if ($currentPage > $totalPages && $totalRows > 0) {
        $currentPage = $totalPages;
        $offset = ($currentPage - 1) * $rowsPerPage; 
    }
} else {
    error_log("Failed to prepare count statement: " . $conn->error);
}


$history = [];
$sql = "SELECT ri.id AS reservation_item_id, i.item_name, ri.reserve_date, ri.return_date, ri.reason, ri.status, ri.quantity
        FROM reservations r
        JOIN reservation_items ri ON r.reserve_id = ri.reserve_id
        JOIN item i ON ri.item_id = i.item_id
        WHERE r.person_id = ?
        ORDER BY ri.id ASC 
        LIMIT ? OFFSET ?";

if ($stmt = $conn->prepare($sql)) {
    
    $stmt->bind_param("iii", $person_id, $rowsPerPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();
} else {
    error_log("Failed to prepare history statement: " . $conn->error);
}


$upcoming_bookings_all = [];
$sql_upcoming = "SELECT i.item_name, ri.reserve_date, ri.return_date, ri.status
                 FROM reservations r
                 JOIN reservation_items ri ON r.reserve_id = ri.reserve_id
                 JOIN item i ON ri.item_id = i.item_id
                 WHERE r.person_id = ? AND ri.status IN ('Approved', 'Pending', 'Checked Out') 
                 ORDER BY ri.id ASC";

if ($stmt_upcoming = $conn->prepare($sql_upcoming)) {
    $stmt_upcoming->bind_param("i", $person_id);
    $stmt_upcoming->execute();
    $result_upcoming = $stmt_upcoming->get_result();
    while ($row_up = $result_upcoming->fetch_assoc()) {
        $upcoming_bookings_all[] = $row_up;
    }
    $stmt_upcoming->close();
} else {
    error_log("Failed to prepare upcoming bookings statement: " . $conn->error);
}



?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrowing History — UniKL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    /* --- CSS STYLES UNIFIED --- */
    :root {
        --primary-color: #06b6d4; /* Cyan 600 (Biru Teal Gelap) */
        --primary-light: #f0f9ff; /* Biru Sangat Muda untuk Latar Belakang Aktif */
        --primary-hover: #0891b2; /* Cyan 700 */
        --bg-light-gray: #f4f7f9; /* Latar belakang luar */
        --card-bg: #ffffff; /* Latar belakang Sidebar/Topbar/Card */
        --text-dark: #1e293b; 
        --text-muted: #64748b; 
        --shadow-light: 0 4px 12px rgba(0, 0, 0, 0.05); 
        
        /* Warna Badge Status */
        --status-approved-bg: #d1fae5;
        --status-approved-text: #059669;
        --status-checkedout-bg: #dbeafe;
        --status-checkedout-text: #1d4ed8;
        --status-pending-bg: #fef3c7;
        --status-pending-text: #b45309;
        --status-rejected-bg: #fee2e2;
        --status-rejected-text: #991b1b;
        --status-returned-bg: #e5e7eb;
        --status-returned-text: #374151;
    }
    
    body {  
        font-family: 'Inter', sans-serif;   
        background-color: var(--bg-light-gray);   
        color: var(--text-dark);    
        min-height: 100vh;  
    }
    
    /* --- Sidebar Styles --- */
    .sidebar {  
        width: 280px; position: fixed; top: 0; bottom: 0; left: 0;    
        background: var(--card-bg); padding: 20px;  
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05); z-index: 1000;  
        display: flex; flex-direction: column; justify-content: space-between; 
        transition: transform 0.3s ease-in-out; 
    }
    .sidebar-header { display: flex; align-items: center; gap: 10px; margin-bottom: 35px; }
    .logo-icon { width: 45px; height: 45px; background-color: var(--primary-color); color: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
    .logo-text strong { display: block; font-size: 18px; color: var(--text-dark); font-weight: 700; }
    .logo-text span { font-size: 12px; color: var(--text-muted); font-weight: 500; }
    .sidebar a {   
        display: flex; align-items: center; gap: 15px;  
        color: var(--text-muted); text-decoration: none;    
        padding: 14px 18px; margin-bottom: 6px; 
        border-radius: 10px; font-weight: 500; font-size: 15px; 
        transition: all 0.2s;   
    }
        
    .sidebar a.active { 
        background: var(--primary-light);    
        color: var(--primary-color);    
        font-weight: 700;    
        box-shadow: 0 2px 8px rgba(6, 182, 212, 0.1);
    }
    .sidebar a:hover:not(.active) {
        background: #eef1f4;
        color: var(--text-dark);
    }
    .sidebar a.logout-link { color: #ef4444; font-weight: 600; margin-top: 20px; }
    .sidebar a i { width: 20px; text-align: center; }

    /* --- Content & Topbar Styles --- */
    .main-content { margin-left: 280px; transition: margin-left 0.3s ease-in-out; }
    .topbar {    
        background: var(--card-bg); 
        padding: 18px 30px; 
        display: flex;   
        justify-content: space-between; 
        align-items: center; 
        border-bottom: 1px solid #eef1f4;    
        z-index: 999;    
        position: sticky;    
        top: 0; 
    }
    .topbar h3 { font-weight: 700; margin: 0; color: var(--text-dark); font-size: 24px; }
    .topbar .user-profile { 
        display: flex;   
        align-items: center;     
        gap: 15px; 
        position: relative; 
    }
    .topbar .user-name { font-weight: 600; font-size: 15px; color: var(--text-dark); }
    .container-fluid { padding: 30px; }
        
    .card { 
        border-radius: 12px;
        box-shadow: var(--shadow-light);     
        background: var(--card-bg); 
        margin-bottom: 25px;     
        border: none;    
        transition: all 0.2s;
        padding: 25px; 
    }
    
    /* --- TABLE STYLES --- */
    .table-responsive {
        border-radius: 8px;
        overflow-x: auto;
        border: 1px solid #eef1f4;
        margin-top: 10px;
    }
    .table thead th {
        background-color: #f8fafc;
        color: var(--text-dark);
        font-weight: 600;
        font-size: 13px;
        padding: 12px 15px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        vertical-align: middle;
        border-bottom: 1px solid #eef1f4;
    }
    .table tbody td {
        padding: 12px 15px;
        font-size: 14px;
        vertical-align: middle;
    }
    .table tbody tr:last-child td {
        border-bottom: none;
    }
    .table-hover tbody tr:hover {
        background-color: #fbfdff;
    }
    .item-name-col { width: 25%; }
    .date-col, .status-col { width: 15%; }

    /* Custom Badge Styles */
    .loan-status-badge {
        font-weight: 600;
        font-size: 11px;
        padding: 4px 10px;
        border-radius: 9999px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }
    
    /* Guna CSS Vars untuk Badge */
    .loan-status-badge[data-status="approved"] { background-color: var(--status-approved-bg); color: var(--status-approved-text); }
    .loan-status-badge[data-status="checked out"] { background-color: var(--status-checkedout-bg); color: var(--status-checkedout-text); }
    .loan-status-badge[data-status="pending"] { background-color: var(--status-pending-bg); color: var(--status-pending-text); }
    .loan-status-badge[data-status="rejected"] { background-color: var(--status-rejected-bg); color: var(--status-rejected-text); }
    .loan-status-badge[data-status="returned"] { background-color: var(--status-returned-bg); color: var(--status-returned-text); }

    /* Kawalan Penapis dan Pagination */
    .filter-controls-container {
        display: flex;
        gap: 15px; 
        margin-bottom: 20px;
        align-items: center;
        flex-wrap: wrap; /* Penting untuk mobile */
    }
    #searchInput {
        flex-grow: 1; 
        min-width: 150px;
    }
    .form-select-sm, .form-control-sm {
        height: 38px !important;
        font-size: 14px;
        padding: 0.25rem 0.5rem;
    }
    .pagination-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 20px;
        font-size: 14px;
        color: var(--text-muted);
    }
    .pagination-controls .pagination {
        --bs-pagination-color: var(--primary-color);
        --bs-pagination-focus-box-shadow: 0 0 0 0.25rem rgba(6, 182, 212, 0.25);
        --bs-pagination-hover-color: var(--primary-hover);
        --bs-pagination-active-bg: var(--primary-color);
        --bs-pagination-active-border-color: var(--primary-color);
    }

    /* --- Upcoming Bookings (Reka Bentuk Baharu) --- */

    .card h4.title-booking {
        font-weight: 700;
        font-size: 1.2rem;
        color: var(--text-dark);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .upcoming-list {
        display: flex;
        flex-direction: column;
        gap: 15px; /* Jarak antara item */
    }

    .upcoming-item {
        border: 1px solid #eef1f4;
        border-radius: 10px; /* Jejari yang kemas */
        padding: 10px 15px;
        display: flex;
        flex-direction: column;
        gap: 5px;
        background-color: var(--card-bg);
        transition: all 0.2s;
        overflow: hidden; /* Penting untuk bar status */
    }

    .upcoming-item:hover {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
    }

    /* Bar status warna di atas */
    .item-status-bar {
        height: 4px; 
        width: 100%;
        margin: -10px -15px 10px -15px; /* Penuhkan lebar card */
    }

    /* Teks Nama Item */
    .item-name {
        font-weight: 600;
        color: var(--text-dark);
        font-size: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 3px;
    }
    .item-name i {
        color: var(--text-muted); /* Warna ikon */
        font-size: 14px;
    }

    /* Teks Tarikh dan Status */
    .item-dates-status {
        font-size: 13px;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        justify-content: space-between; 
    }

    /* Warna-warna Status Bar */
    .status-bar-checkedout { background-color: #16a34a; /* Hijau Gelap */ }
    .status-bar-approved { background-color: #06b6d4; /* Primary / Cyan */ } 
    .status-bar-pending { background-color: #f59e0b; /* Kuning/Amber */ }

    /* Warna Teks Status */
    .text-success-dark { color: #16a34a !important; font-weight: 600; }
    .text-info-dark { color: #0891b2 !important; font-weight: 600; }
    .text-warning-dark { color: #b45309 !important; font-weight: 600; }

    /* --- Mobile Optimizations (Kekalkan) --- */
    @media (max-width: 992px) {
        .sidebar { transform: translateX(-280px); left: 0; width: 280px; }
        .sidebar.active { transform: translateX(0); }
        .sidebar.active ~ #overlay { display: block; }
        .main-content { margin-left: 0; width: 100%; }
        .menu-toggle-btn { display: inline-block; order: -1; font-size: 24px; background: none; border: none; color: var(--text-dark); padding: 0; }
        .topbar { padding: 15px 20px; display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 15px; }
        .topbar h3 { font-size: 20px; text-align: center; }
        .topbar .user-name { display: none; }
        .container-fluid { padding: 15px; }
        .card { border-radius: 10px; }
    }
</style>
</head>
<body>

<div class="offcanvas-backdrop fade" id="sidebar-backdrop" style="display: none; z-index: 1040;"></div>

<div class="sidebar" id="offcanvasSidebar">
    <div>
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fa-solid fa-cube"></i></div>
            <div class="logo-text"><strong>UniKL User</strong><span>Equipment System</span></div>
        </div>
        <a href="dashboard_user.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        <a href="item_user.php"><i class="fa-solid fa-box"></i> Item Availability</a>
        <a href="history.php" class="active"><i class="fa-solid fa-clock-rotate-left"></i> History</a>
    </div>
    <a href="logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>

<div class="main-content">
    <div class="topbar">
        <button class="btn btn-sm btn-outline-primary d-lg-none" type="button" id="sidebarToggle">
            <i class="fa-solid fa-bars"></i>
        </button>

        <h3>Borrowing History</h3>
        <div class="user-profile">
            <span class="user-name"><?= htmlspecialchars(isset($user['name']) ? $user['name'] : 'User') ?></span>
            <a href="profile.php" title="Go to My Profile" style="color: inherit; text-decoration: none;">
                <i class="fa-solid fa-circle-user fa-2x text-secondary"></i>
            </a>
        </div>
    </div>
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-8 col-12"> 
                <div class="card">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="fa-solid fa-list-ul me-2 text-primary"></i> My Loan History</h5>
                        <div class="btn-group" role="group">
                            <button type="button" id="tableViewBtn" class="btn btn-primary btn-sm active"><i class="fa-solid fa-table-list me-2"></i>Table</button>
                            <button type="button" id="calendarViewBtn" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-calendar-days me-2"></i>Calendar</button>
                        </div>
                    </div>

                    <div id="tableView">                    
                        <div class="filter-controls-container">
                           <select id="statusFilter" class="form-select form-select-sm" style="max-width: 200px;">
                                <option value="">All Statuses</option>
                                <option value="approved">Approved</option>
                                <option value="checked out">Checked Out</option>
                                <option value="pending">Pending</option>
                                <option value="rejected">Rejected</option>
                                <option value="returned">Returned</option>
                           </select>
                           <input id="searchInput" type="text" class="form-control form-control-sm" placeholder="Search item or reason...">
                        </div>
                        
                        <div class="table-responsive">
                            <table id="historyTable" class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th class="item-name-col">Item Name</th>
                                        <th class="date-col">Borrow Date</th>
                                        <th class="date-col">Return Date</th>
                                        <th class="status-col">Status</th>
                                        <th>Qty</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($history)): ?>
                                        <?php foreach ($history as $loan):
                                            $status = strtolower($loan['status']);
                                            $badgeClass = ''; 
                                            $statusIcon = 'fa-solid fa-circle-info';
                                            
                                            
                                            if ($status == 'approved') {
                                                $badgeClass = 'text-bg-success';
                                                $statusIcon = 'fa-solid fa-circle-check';
                                            } elseif ($status == 'checked out') {
                                                $badgeClass = 'text-bg-primary'; 
                                                $statusIcon = 'fa-solid fa-hand-holding-box';
                                            } elseif ($status == 'pending') {
                                                $badgeClass = 'text-bg-warning';
                                                $statusIcon = 'fa-solid fa-hourglass-half';
                                            } elseif ($status == 'rejected') {
                                                $badgeClass = 'text-bg-danger';
                                                $statusIcon = 'fa-solid fa-circle-xmark';
                                            } elseif ($status == 'returned') {
                                                $badgeClass = 'text-bg-secondary';
                                                $statusIcon = 'fa-solid fa-handshake';
                                            }
                                        ?>
                                        <tr>
                                            <td class="item-name-col"><strong><?= htmlspecialchars($loan['item_name']) ?></strong></td>
                                            <td class="date-col"><?= date("d M Y", strtotime($loan['reserve_date'])) ?></td>
                                            <td class="date-col"><?= date("d M Y", strtotime($loan['return_date'])) ?></td>
                                            <td>
                                                <span class="badge rounded-pill loan-status-badge" data-status="<?= $status ?>">
                                                    <i class="<?= $statusIcon ?>"></i> <?= ucfirst(htmlspecialchars($loan['status'])) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($loan['quantity']) ?></td>
                                            <td><small class="text-muted"><?= htmlspecialchars($loan['reason']) ?></small></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6" class="text-center text-muted py-5"><i class="fa-solid fa-box-open fa-2x mb-2"></i><br>No history records found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <nav aria-label="History Pagination" class="pagination-controls">
                            <div>
                                <?php if ($totalRows > 0): ?>
                                Showing <?= min(($currentPage - 1) * $rowsPerPage + 1, $totalRows) ?>
                                to <?= min($currentPage * $rowsPerPage, $totalRows) ?>
                                of <?= $totalRows ?> entries
                                <?php else: ?>
                                No entries found
                                <?php endif; ?>
                            </div>
                            <ul class="pagination pagination-sm mb-0">
                                <?php if ($currentPage > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $currentPage - 1 ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">&laquo;</span>
                                    </li>
                                <?php endif; ?>

                                <?php
                                 
                                 $maxPagesToShow = 5; 
                                 $startPage = max(1, $currentPage - floor($maxPagesToShow / 2));
                                 $endPage = min($totalPages, $startPage + $maxPagesToShow - 1);
                                 if ($endPage == $totalPages) {
                                     $startPage = max(1, $endPage - $maxPagesToShow + 1);
                                 }

                                 if ($startPage > 1) {
                                     echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                                     if ($startPage > 2) {
                                         echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                     }
                                 }

                                 for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?= ($i == $currentPage) ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                 <?php endfor;

                                 if ($endPage < $totalPages) {
                                     if ($endPage < $totalPages - 1) {
                                         echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                     }
                                     echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '">' . $totalPages . '</a></li>';
                                 }
                                 ?>

                                <?php if ($currentPage < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $currentPage + 1 ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">&raquo;</span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>

                    <div id="calendarView" style="display: none;">
                        </div>
                </div>
            </div>

            <div class="col-lg-4 col-12">
                <div class="card">
                    <h4 class="title-booking"><i class="fa-solid fa-calendar-check me-2 text-primary"></i> Upcoming Bookings</h4>
                    
                    <div class="upcoming-list">
                        <?php if (!empty($upcoming_bookings_all)): ?>
                            <?php foreach ($upcoming_bookings_all as $booking):
                                $status_upcoming = strtolower($booking['status']);
                                $bar_class = ''; 
                                $status_text_class = ''; 
                                
                                if ($status_upcoming === 'checked out') {
                                    $bar_class = 'status-bar-checkedout';
                                    $status_text_class = 'text-success-dark'; 
                                } elseif ($status_upcoming === 'approved') {
                                    $bar_class = 'status-bar-approved';
                                    $status_text_class = 'text-info-dark'; 
                                } elseif ($status_upcoming === 'pending') {
                                    $bar_class = 'status-bar-pending';
                                    $status_text_class = 'text-warning-dark'; 
                                }
                            ?>
                            <div class="upcoming-item">
                                <div class="item-status-bar <?= $bar_class ?>"></div> 
                                
                                <div class="item-name">
                                    <i class="fa-solid fa-box"></i>
                                    <?= htmlspecialchars($booking['item_name']) ?>
                                </div>
                                
                                <div class="item-dates-status">
                                    <span class="dates-range text-muted">
                                        <i class="fa-solid fa-calendar-alt me-1"></i>
                                        <?= date("d M", strtotime($booking['reserve_date'])) ?> &rarr; <?= date("d M Y", strtotime($booking['return_date'])) ?>
                                    </span>
                                    <span class="status-text <?= $status_text_class ?>">
                                        <?= ucfirst(htmlspecialchars($booking['status'])) ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fa-solid fa-face-smile fa-2x mb-2"></i><br>
                                Tiada tempahan yang sedang aktif atau menunggu kelulusan.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 

if (isset($conn)) {
    $conn->close();
}
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.13/index.global.min.js'></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    const sidebar = document.getElementById('offcanvasSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const backdrop = document.getElementById('sidebar-backdrop');
    const body = document.body;

    if (toggleBtn) {
        
        toggleBtn.addEventListener('click', () => {
            if (window.innerWidth <= 992) {
                const isHidden = sidebar.style.transform === 'translateX(-280px)' || sidebar.style.transform === '';
                
                if (isHidden) {
                    sidebar.style.transform = 'translateX(0px)';
                    backdrop.style.display = 'block';
                    body.classList.add('offcanvas-open');
                } else {
                    sidebar.style.transform = 'translateX(-280px)';
                    backdrop.style.display = 'none';
                    body.classList.remove('offcanvas-open');
                }
            }
        });

        backdrop.addEventListener('click', () => {
            if (window.innerWidth <= 992) {
                sidebar.style.transform = 'translateX(-280px)';
                backdrop.style.display = 'none';
                body.classList.remove('offcanvas-open');
            }
        });
    }

    
    const tableViewBtn = document.getElementById('tableViewBtn');
    const calendarViewBtn = document.getElementById('calendarViewBtn');
    const tableView = document.getElementById('tableView');
    const calendarView = document.getElementById('calendarView');
    let calendar = null;

    function initializeCalendar() {
        if (calendar) {
            calendar.render(); 
            return;
        } 
        
        calendar = new FullCalendar.Calendar(calendarView, {
            initialView: 'dayGridMonth',
            headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' },
            
            events: 'get_bookings.php?person_id=<?= $person_id ?>', 
            height: 'auto',
            eventClick: function(info) {
                alert('Item: ' + info.event.title + 
                      '\nStatus: ' + info.event.extendedProps.status + 
                      '\nStart: ' + info.event.startStr.substring(0, 10) +
                      '\nEnd: ' + info.event.endStr.substring(0, 10));
            }
        });
        calendar.render();
    }

    if (tableViewBtn && calendarViewBtn && tableView && calendarView) {
        tableViewBtn.addEventListener('click', () => {
            tableView.style.display = 'block';
            calendarView.style.display = 'none';
            tableViewBtn.classList.add('active', 'btn-primary');
            tableViewBtn.classList.remove('btn-outline-primary');
            calendarViewBtn.classList.remove('active', 'btn-primary');
            calendarViewBtn.classList.add('btn-outline-primary');
        });

        calendarViewBtn.addEventListener('click', () => {
            tableView.style.display = 'none';
            calendarView.style.display = 'block';
            calendarViewBtn.classList.add('active', 'btn-primary');
            calendarViewBtn.classList.remove('btn-outline-primary');
            tableViewBtn.classList.remove('active', 'btn-primary');
            tableViewBtn.classList.add('btn-outline-primary');
            initializeCalendar(); 
        });
    }

    
    const historyTable = document.getElementById('historyTable');
    if (historyTable) {
        const tableBody = historyTable.querySelector('tbody');
        
        const initialRows = Array.from(tableBody.querySelectorAll('tr')).filter(row => !row.querySelector('td[colspan="6"]')); 
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        
        
        const originalNoDataRow = tableBody.querySelector('td[colspan="6"]') ? tableBody.innerHTML : null;
        
        function applyClientSideFilters() {
            const query = searchInput.value.trim().toLowerCase();
            const status = statusFilter.value;
            let visibleCount = 0;
            
            
            const existingNoMatchRow = tableBody.querySelector('.no-filter-match');
            if (existingNoMatchRow) existingNoMatchRow.remove();
            
            
            if (initialRows.length === 0 && originalNoDataRow) {
                return; 
            }
            
            let allHidden = true;

            initialRows.forEach(row => {
                
                const item = row.cells[0].innerText.toLowerCase();
                
                const reasonElement = row.cells[5].querySelector('small');
                const reason = reasonElement ? reasonElement.innerText.toLowerCase() : '';
                
                const rowStatusBadge = row.cells[3].querySelector('.loan-status-badge');
                
                const rowStatus = rowStatusBadge ? rowStatusBadge.dataset.status : ''; 

                const matchesSearch = item.includes(query) || reason.includes(query);
                const matchesStatus = (status === '') || (rowStatus === status);

                if (matchesSearch && matchesStatus) {
                    row.style.display = ''; 
                    visibleCount++;
                    allHidden = false;
                } else {
                    row.style.display = 'none'; 
                }
            });

            
            if (visibleCount === 0) {
                 
                if (initialRows.length > 0) {
                    const tr = document.createElement('tr');
                    tr.className = 'no-filter-match';
                    tr.innerHTML = `<td colspan="6" class="text-center text-muted py-5"><i class="fa-solid fa-search fa-2x mb-2"></i><br>No matching records found for the current filters on this page.</td>`;
                    tableBody.appendChild(tr);
                }
            }
        }

        if(searchInput) searchInput.addEventListener('keyup', applyClientSideFilters);
        if(statusFilter) statusFilter.addEventListener('change', applyClientSideFilters);

    }
});
</script>

</body>
</html>