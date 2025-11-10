<?php
session_start();

include '../config.php';
function get_reservation_item_count($conn, $status) {
    
    $sql = "SELECT COUNT(id) AS count 
            FROM reservation_items 
            WHERE status = ?";
    
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


$pending_count_for_badge = get_reservation_item_count($conn, 'Pending'); 


if (!isset($_SESSION['tech_id'])) {
    header("Location: ../login.php");
    exit();
}

$tech_id = (int) $_SESSION['tech_id'];


$stmt = $conn->prepare("SELECT name, email FROM technician WHERE tech_id = ?");
$stmt->bind_param("i", $tech_id);
$stmt->execute();
$result = $stmt->get_result();
$tech = $result->fetch_assoc();
$stmt->close();

if (!$tech) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}


$totalAssetsResult = $conn->query("SELECT COUNT(asset_id) AS total FROM assets WHERE status NOT IN ('Broken', 'Decommissioned', 'Missing')");
$totalAssetsRow = $totalAssetsResult->fetch_assoc();
$totalAssetsCount = isset($totalAssetsRow['total']) ? (int)$totalAssetsRow['total'] : 0;

$availableResult = $conn->query("SELECT COUNT(asset_id) AS total FROM assets WHERE status = 'Available'");
$availableRow = $availableResult->fetch_assoc();
$availableCount = isset($availableRow['total']) ? (int)$availableRow['total'] : 0;

$checkedOutResult = $conn->query("SELECT COUNT(asset_id) AS total FROM assets WHERE status = 'Checked Out'");
$checkedOutRow = $checkedOutResult->fetch_assoc();
$checkedOutCount = isset($checkedOutRow['total']) ? (int)$checkedOutRow['total'] : 0;

$overdueResult = $conn->query("SELECT COUNT(DISTINCT ri.id) AS total FROM reservation_items ri WHERE ri.status = 'Checked Out' AND ri.return_date < CURDATE()");
$overdueRow = $overdueResult->fetch_assoc();
$overdueCount = isset($overdueRow['total']) ? (int)$overdueRow['total'] : 0;




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


$total_assets_details = fetch_asset_details($conn, "a.status NOT IN ('Broken', 'Decommissioned', 'Missing')");
$total_assets_details_json = json_encode($total_assets_details);


$available_assets_details = fetch_asset_details($conn, "a.status = 'Available'");
$available_assets_details_json = json_encode($available_assets_details);


$checked_out_sql = "
    SELECT
        a.asset_id, a.asset_code, a.status,
        i.item_name,
        c.category_name,
        u.name as user_name,
        ri.return_date
    FROM assets a
    LEFT JOIN item i ON a.item_id = i.item_id
    LEFT JOIN categories c ON i.category_id = c.category_id
    LEFT JOIN reservation_assets ra ON a.asset_id = ra.asset_id
    LEFT JOIN reservation_items ri ON ra.reservation_item_id = ri.id AND ri.status = 'Checked Out' -- Ensure link is to an active checkout
    LEFT JOIN reservations r ON ri.reserve_id = r.reserve_id
    LEFT JOIN user u ON r.user_id = u.user_id
    WHERE a.status = 'Checked Out'
    ORDER BY u.name, c.category_name, i.item_name, a.asset_code
";
$checked_out_result = $conn->query($checked_out_sql);
$checked_out_details = [];
if ($checked_out_result) {
    $checked_out_details = $checked_out_result->fetch_all(MYSQLI_ASSOC);
} else {
     error_log("Error fetching checked out details: " . $conn->error);
}
$checked_out_details_json = json_encode($checked_out_details);



$overdue_details_sql = "
    SELECT
        ri.id AS reservation_item_id, u.name AS user_name, u.phoneNum AS user_phone, i.item_name,
        ri.return_date, DATEDIFF(CURDATE(), ri.return_date) AS days_overdue,
        GROUP_CONCAT(DISTINCT a.asset_code SEPARATOR ', ') AS assigned_assets
    FROM reservation_items ri
    JOIN reservations r ON ri.reserve_id = r.reserve_id
    JOIN user u ON r.user_id = u.user_id
    JOIN item i ON ri.item_id = i.item_id
    LEFT JOIN reservation_assets ra ON ri.id = ra.reservation_item_id
    LEFT JOIN assets a ON ra.asset_id = a.asset_id
    WHERE ri.status = 'Checked Out' AND ri.return_date < CURDATE()
    GROUP BY ri.id ORDER BY ri.return_date ASC ";
$overdue_details_result = $conn->query($overdue_details_sql);
$overdue_details = [];
if($overdue_details_result) {
    $overdue_details = $overdue_details_result->fetch_all(MYSQLI_ASSOC);
} else {
    error_log("Error fetching overdue details: " . $conn->error);
}
$overdue_details_json = json_encode($overdue_details);


$chart_sql = "SELECT c.category_name, COUNT(ri.id) as loan_count FROM reservation_items ri JOIN item i ON ri.item_id = i.item_id JOIN categories c ON i.category_id = c.category_id WHERE ri.status = 'Checked Out' GROUP BY c.category_id ORDER BY loan_count DESC";
$chart_result = $conn->query($chart_sql);
$chart_data = $chart_result ? $chart_result->fetch_all(MYSQLI_ASSOC) : [];
$chartLabels = [];
$chartValues = [];
foreach ($chart_data as $row) {
    $chartLabels[] = $row['category_name'];
    $chartValues[] = (int)$row['loan_count'];
}


$events = []; 


$historySql = "SELECT ri.quantity, ri.reserve_date, ri.return_date, ri.status,
                       u.name AS username, i.item_name
               FROM reservation_items ri
               JOIN reservations r ON ri.reserve_id = r.reserve_id
               JOIN user u ON r.user_id = u.user_id
               JOIN item i ON ri.item_id = i.item_id
               WHERE ri.status IN ('Approved', 'Checked Out')
               ORDER BY ri.reserve_date ASC";
$historyResult = $conn->query($historySql);

if ($historyResult) {
    while ($h = $historyResult->fetch_assoc()) {
        
        $events[] = [
            'title' => "{$h['item_name']} ({$h['quantity']}) - {$h['username']}",
            'start' => date('Y-m-d', strtotime($h['reserve_date'])),
            'end' => date('Y-m-d', strtotime($h['return_date'] . ' +1 day')), 
            'color' => '#3b82f6', 
            'description' => 'Reservation'
        ];

        
        if ($h['status'] === 'Checked Out' && !empty($h['return_date'])) {
            
            $bufferStartDate = date('Y-m-d', strtotime($h['return_date'] . ' +1 day'));
            
            $bufferEndDate = date('Y-m-d', strtotime($h['return_date'] . ' +2 days')); 

            $events[] = [
                'title' => "Buffer: {$h['item_name']}",
                'start' => $bufferStartDate,
                'end'  => $bufferEndDate,
                'color' => '#fdba74', 
                'textColor' => '#854d0e',
                'description' => 'Buffer Period - Pending Check-in'
            ];
        }
    }
} else {
    error_log("Error fetching reservation history for calendar: " . $conn->error);
}


$conn->close();


$events_json = json_encode($events);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Dashboard</title>
    <link href="https:
    <link rel="stylesheet" href="https:
    <link href="https:
    <link rel="stylesheet" href="https:
    <script src="https:
    <link rel="preconnect" href="https:
    <link rel="preconnect" href="https:
    <link href="https:
    <style>
        /* 1. FONT & BODY BACKGROUND */
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background-color: #f8fafc; color: #334155; min-height: 100vh; }

        /* 2. SIDEBAR (Desktop Fixed) */
        .sidebar { 
            width: 250px; position: fixed; top: 0; bottom: 0; left: 0; 
            background: #ffffff; padding: 20px; border-right: 1px solid #e5e7eb; 
            z-index: 1000; display: flex; flex-direction: column; justify-content: space-between; 
        }
        .sidebar-header { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
        .logo-icon { width: 40px; height: 40px; background-color: #3b82f6; color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .logo-text strong { display: block; font-size: 16px; color: #1e293b; }
        .logo-text span { font-size: 12px; color: #94a3b8; }
        .sidebar a { display: flex; align-items: center; gap: 12px; color: #64748b; text-decoration: none; padding: 12px 15px; margin-bottom: 8px; border-radius: 8px; font-weight: 500; font-size: 15px; transition: all 0.2s ease-in-out; }
        .sidebar a.active, .sidebar a:hover { background: #3b82f6; color: #fff; }
        .sidebar a.logout-link { color: #ef4444; font-weight: 600; margin-top: auto; }
        .sidebar a.logout-link:hover { color: #fff; background: #ef4444; }
/* 5. SIDEBAR BADGE STYLE (Penambahan) */
.sidebar a .badge {
    margin-left: auto; /* Tolak badge ke kanan */
    font-size: 0.75rem;
    padding: 0.4em 0.6em;
    font-weight: 700;
    border-radius: 10px;
    background-color: #ef4444; /* Merah untuk menarik perhatian */
    color: white;
}

/* Pastikan badge tidak hilang apabila item menu di-hover atau aktif */
.sidebar a.active .badge, .sidebar a:hover .badge {
    background-color: #ffffff;
    color: #ef4444; /* Warna terbalik agar kontras */
}
        /* 3. MAIN LAYOUT & TOPBAR */
        .main-content { margin-left: 250px; }
        .topbar { background: #ffffff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; }
        .topbar h3 { font-weight: 600; margin: 0; color: #1e293b; font-size: 22px; }
        .topbar .technician-profile { display: flex; align-items: center; gap: 12px; }
.topbar .technician-profile i { 
    color: #64748b; /* Kelabu */
}
        .topbar .technician-name { font-weight: 600; font-size: 15px; color: #334155; }
        .container-fluid { padding: 30px; }

        /* 4. CARDS & GENERAL STYLES */
        .card { border-radius: 16px; padding: 25px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); background: #fff; margin-bottom: 25px; border: 1px solid #e2e8f0; }
        .card h5, .modal-title { font-weight: 600; color: #1e293b; }
        .card-summary { text-align: center; position: relative; padding-top: 20px; padding-bottom: 20px; cursor: pointer; } /* Padded summary card */
        .card-summary i { font-size: 2.5rem; margin-bottom: 10px; display: block; }
        .card-summary h3 { font-size: 2.25rem; font-weight: 700; margin: 0; }
        .card-summary p { font-size: 0.9rem; margin: 0; color: #64748b; text-transform: uppercase; font-weight: 500; }
        .text-primary { color: #3b82f6 !important; }
        .text-success { color: #22c55e !important; }
        .text-warning { color: #f59e0b !important; }
        .text-danger { color: #ef4444 !important; }

/* Targetkan bekas kalendar (yang mengandungi kalendar FullCalendar atau yang serupa) */
.calendar-card {
    /* Tetapkan ketinggian minimum yang mencukupi untuk kalendar */
    min-height: 550px; 
}

/* Pastikan semua elemen kalendar mengisi lebar penuh bekas */
#calendar,
.reservation-calendar,
.fc-view-container { 
    width: 100%;
}

/* Penyesuaian font/saiz untuk Kalendar */
.reservation-calendar table {
    font-size: 14px; /* Kecilkan sedikit agar muat dengan baik */
    border: 1px solid var(--border-color); /* Tambah border yang lebih jelas */
    border-radius: 8px; 
}

/* Penyesuaian header Kalendar */
.fc-toolbar {
    padding-bottom: 10px;
    margin-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
}

/* Menyelaraskan kotak slot tempahan (seperti "Type-c charger") */
.fc-event {
    background-color: var(--secondary-color); /* Warna yang lebih baik untuk event */
    border: 1px solid var(--primary-color);
    padding: 2px 4px;
    border-radius: 4px;
    font-size: 12px;
}
        /* 5. MOBILE OPTIMIZATIONS */
        @media (max-width: 991.98px) {
            /* Sidebar becomes off-canvas and hidden by default */
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
                z-index: 1050; 
                position: fixed; 
            }
            .offcanvas-open .sidebar {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0; /* Full width for content */
            }
            .topbar {
                padding: 15px;
            }
            /* Show button to toggle sidebar on mobile */
            .topbar .btn-sm {
                display: inline-block !important; /* Make sure the toggle button is visible */
            }
            /* Adjust padding on small screens */
            .container-fluid {
                padding: 15px;
            }
            .card {
                padding: 15px; /* Less padding on mobile cards */
            }
            .card-summary {
                padding-top: 15px;
                padding-bottom: 15px;
            }
            .card-summary h3 {
                font-size: 1.75rem; /* Smaller count text */
            }
        }
        
        /* Backdrop for Off-Canvas effect */
        .offcanvas-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1040;
            width: 100vw;
            height: 100vh;
            background-color: #000;
            opacity: 0.5;
            transition: opacity 0.3s ease-in-out;
            display: none; /* Hidden by default */
        }
    </style>
</head>
<body>

<div class="offcanvas-backdrop fade" id="sidebar-backdrop"></div>

<div class="sidebar" id="offcanvasSidebar">
    <div>
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fa-solid fa-wrench"></i></div>
            <div class="logo-text"><strong>UniKL Technician</strong><span>System Support</span></div>
        </div>
        <a href="dashboard_tech.php" class="active"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        <a href="check_out.php">
            <i class="fa-solid fa-dolly"></i> Manage Requests
            <?php if ($pending_count_for_badge > 0): ?>
                <span class="badge rounded-pill bg-danger"><?= $pending_count_for_badge ?></span>
            <?php endif; ?>
        </a>
        <a href="manageItem_tech.php"><i class="fa-solid fa-box-archive"></i> Manage Items</a>
        <a href="report.php"><i class="fa-solid fa-chart-line"></i> Report</a>
    </div>
    <a href="logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>

<div class="main-content">
    <div class="topbar">
        <button class="btn btn-sm btn-outline-primary d-lg-none me-3" type="button" id="sidebarToggle" aria-controls="offcanvasSidebar">
            <i class="fa-solid fa-bars"></i>
        </button>

        <h3>Dashboard</h3>
        <div class="technician-profile">
            <span class="technician-name d-none d-sm-block"><?= htmlspecialchars($tech['name']) ?></span>
            <a href="profile_tech.php" title="Go to My Profile" style="color: inherit; text-decoration: none;">
                <i class="fa-solid fa-user-circle fa-2x text-primary"></i>
            </a>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 mb-3">
                <div class="card card-summary text-primary" id="totalAssetsCard" title="Click to view details">
                    <i class="fa-solid fa-laptop"></i> <h3><?= $totalAssetsCount ?></h3> <p>Total Assets</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 mb-3">
                <div class="card card-summary text-success" id="availableAssetsCard" title="Click to view details">
                    <i class="fa-solid fa-box-open"></i> <h3><?= $availableCount ?></h3> <p>Available</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 mb-3">
                <div class="card card-summary text-warning" id="checkedOutAssetsCard" title="Click to view details">
                    <i class="fa-solid fa-handshake"></i> <h3><?= $checkedOutCount ?></h3> <p>Checked Out</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 mb-3">
                <div class="card card-summary text-danger" id="overdueCard" title="Click to view details">
                    <i class="fa-solid fa-clock"></i> <h3><?= $overdueCount ?></h3> <p>Overdue</p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-7 col-12">
                <div class="card">
                    <h5><i class="fa-solid fa-calendar-days me-2 text-primary"></i> Reservation Calendar</h5>
                    <div id="calendar"></div>
                </div>
            </div>
            
            <div class="col-lg-5 col-12">
                <div class="card">
                    <h5><i class="fa-solid fa-user-gear me-2 text-primary"></i> Technician Info</h5>
                    <p><strong>Name:</strong> <?= htmlspecialchars($tech['name']) ?></p>
                    <p class="mb-0"><strong>Email:</strong> <?= htmlspecialchars($tech['email']) ?></p>
                </div>
                <div class="card">
                    <h5><i class="fa-solid fa-chart-pie me-2 text-primary"></i> Loan Distribution by Category</h5>
                    <canvas id="loanChart"></canvas>
                </div>
            </div>
        </div>

    </div>
</div> 

<div class="modal fade" id="totalAssetsModal" tabindex="-1" aria-labelledby="totalAssetsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"> <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="totalAssetsModalLabel"><i class="fa-solid fa-laptop text-primary me-2"></i>Total Assets (Operational)</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <p>List of all assets excluding broken, decommissioned, or missing items.</p>
            <div id="totalAssetsList" class="table-responsive">
                <div class="text-center p-3 text-muted">Loading...</div>
            </div>
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
            <h5 class="modal-title" id="availableAssetsModalLabel"><i class="fa-solid fa-box-open text-success me-2"></i>Available Assets</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <p>List of assets currently available for reservation.</p>
            <div id="availableAssetsList" class="table-responsive">
                <div class="text-center p-3 text-muted">Loading...</div>
            </div>
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
            <h5 class="modal-title" id="checkedOutAssetsModalLabel"><i class="fa-solid fa-handshake text-warning me-2"></i>Checked Out Assets</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <p>List of assets currently on loan.</p>
            <div id="checkedOutAssetsList" class="table-responsive">
                <div class="text-center p-3 text-muted">Loading...</div>
            </div>
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
            <h5 class="modal-title" id="overdueModalLabel"><i class="fa-solid fa-clock text-danger me-2"></i>Overdue Items</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <p>List of items currently checked out and past their return date.</p>
            <div id="overdueList" class="table-responsive">
                <div class="text-center p-3 text-muted">Loading...</div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
    </div>
    </div>
</div>


<script src="https:
<script src="https:
<script src="https:
<script src="https:
<script src="https:


<script>

const ctx = document.getElementById('loanChart');
if (ctx) {
    new Chart(ctx, {
        type: 'doughnut', data: { labels: <?= json_encode($chartLabels) ?>, datasets: [{ data: <?= json_encode($chartValues) ?>, backgroundColor: ['#3b82f6','#22c55e','#f59e0b','#ef4444', '#64748b', '#6d28d9'], borderWidth: 0 }] }, options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } }, cutout: '70%' } });
}


const totalAssetsDetails = <?= $total_assets_details_json ?>;
const availableAssetsDetails = <?= $available_assets_details_json ?>;
const checkedOutAssetsDetails = <?= $checked_out_details_json ?>;
const overdueDetails = <?= $overdue_details_json ?>;
const eventsData = <?php echo $events_json; ?>; 


document.addEventListener('DOMContentLoaded', function() {
    
    
    const sidebar = document.getElementById('offcanvasSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const backdrop = document.getElementById('sidebar-backdrop');
    const body = document.body;

    
    function toggleSidebar() {
        
        const isOpen = sidebar.style.transform === 'translateX(0px)';
        
        if (isOpen) {
            
            sidebar.style.transform = 'translateX(-100%)';
            backdrop.style.display = 'none';
            body.classList.remove('offcanvas-open');
        } else {
            
            sidebar.style.transform = 'translateX(0px)';
            backdrop.style.display = 'block';
            body.classList.add('offcanvas-open');
        }
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', toggleSidebar);
    }
    
    if (backdrop) {
        backdrop.addEventListener('click', toggleSidebar);
    }

    
    const calendarEl = document.getElementById('calendar');
    if (calendarEl) {
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,listWeek'
            },
            events: eventsData,
            height: 'auto',
            firstDay: 1, 
            eventDidMount: function(info) {
             if (info.event.extendedProps.description === 'Buffer Period - Pending Check-in') {
                info.el.classList.add('buffer-event');
              }
            }
        });
        calendar.render();
    } else { console.error("Calendar element #calendar not found."); }

    

    
    function createAssetTableHTML(assetList, includeUserAndReturnDate = false) {
        if (!assetList || assetList.length === 0) {
            return '<div class="text-center p-4 text-muted">No assets found in this category.</div>';
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
             let statusBadgeClass = 'secondary'; 
             if (statusValue === 'Available') statusBadgeClass = 'success';
             else if (statusValue === 'Checked Out') statusBadgeClass = 'warning';
             else if (statusValue === 'Reserved') statusBadgeClass = 'info';
             else if (statusValue === 'Broken' || statusValue === 'Decommissioned' || statusValue === 'Missing') statusBadgeClass = 'danger';
             const statusBadge = `<span class="badge rounded-pill text-bg-${statusBadgeClass}">${statusValue}</span>`;

             tableHTML += `<tr>
                                 <td><strong>${asset.asset_code || 'N/A'}</strong></td>
                                 <td>${itemName}</td>
                                 <td>${categoryName}</td>`;
             if (includeUserAndReturnDate) {
                 const userName = asset.user_name || '<em class="text-muted">N/A</em>';
                 const returnDate = asset.return_date ? new Date(asset.return_date + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '<em class="text-muted">N/A</em>';
                 tableHTML += `<td>${userName}</td><td>${returnDate}</td>`;
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
            const modalInstance = new bootstrap.Modal(modalElement);

             
             modalElement.addEventListener('hidden.bs.modal', function () {
                 const existingTable = listContainer.querySelector('.asset-detail-table');
                 if (existingTable && $.fn.DataTable.isDataTable(existingTable)) {
                     $(existingTable).DataTable().destroy();
                 }
                 listContainer.innerHTML = '<div class="text-center p-3 text-muted">Loading...</div>'; 
             });


            card.addEventListener('click', function() {
                const tableData = createAssetTableHTML(dataList, includeUser);
                listContainer.innerHTML = tableData.html;

                modalInstance.show();

                 
                 setTimeout(() => {
                     const newTable = $(`#${tableData.id}`);
                     if (newTable.length) {
                         newTable.DataTable({
                             "pageLength": 10,
                             "order": [],
                             "language": {
                                 "search": "Search:",
                                 "lengthMenu": "Show _MENU_ assets",
                                 "info": "Showing _START_ to _END_ of _TOTAL_ assets",
                                 "infoEmpty": "No assets found",
                                 "infoFiltered": "(filtered from _MAX_ total assets)",
                                 "zeroRecords": "No matching assets found",
                                 "paginate": { "first": "First", "last": "Last", "next": "Next", "previous": "Previous" }
                             },
                             "destroy": true 
                         });
                     }
                 }, 200); 

            });
        } else {
            console.error(`Missing elements for modal trigger: ${cardId}, ${modalElementId}, ${listContainerId}`);
        }
    }

    
    setupModalTrigger('totalAssetsCard', 'totalAssetsModal', 'totalAssetsList', totalAssetsDetails);
    setupModalTrigger('availableAssetsCard', 'availableAssetsModal', 'availableAssetsList', availableAssetsDetails);
    setupModalTrigger('checkedOutAssetsCard', 'checkedOutAssetsModal', 'checkedOutAssetsList', checkedOutAssetsDetails, true); 

    
    const overdueCard = document.getElementById('overdueCard');
    const overdueModalElement = document.getElementById('overdueModal');
    const overdueListContainer = document.getElementById('overdueList');

    if (overdueCard && overdueModalElement && overdueListContainer) {
        const overdueModal = new bootstrap.Modal(overdueModalElement);

         
         overdueModalElement.addEventListener('hidden.bs.modal', function () {
             const existingTable = overdueListContainer.querySelector('.asset-detail-table');
             if (existingTable && $.fn.DataTable.isDataTable(existingTable)) {
                 $(existingTable).DataTable().destroy();
             }
             overdueListContainer.innerHTML = '<div class="text-center p-3 text-muted">Loading...</div>'; 
         });

        overdueCard.addEventListener('click', function() {
            overdueListContainer.innerHTML = ''; 

            if (overdueDetails.length === 0) {
                overdueListContainer.innerHTML = '<div class="text-center p-4 text-muted"><i class="fa-solid fa-check-circle fa-2x mb-2 text-success"></i><br>No items are currently overdue.</div>';
            } else {
                 const tableId = `overdueTable_${Date.now()}`;
                 let tableHTML = `<table class="table table-sm table-striped table-hover asset-detail-table" id="${tableId}">`;
                 tableHTML += `<thead><tr>
                                     <th>User</th>
                                     <th>Item</th>
                                     <th>Asset Code(s)</th>
                                     <th>Return Date</th>
                                     <th class='text-danger'>Days Overdue</th>
                                     <th>Contact</th>
                                   </tr></thead><tbody>`;
                 overdueDetails.forEach(item => {
                     const returnDate = new Date(item.return_date + 'T00:00:00');
                     const returnDateFormatted = returnDate.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
                     const phoneLink = item.user_phone ? `<a href="tel:${item.user_phone}">${item.user_phone}</a>` : 'N/A';
                     const assignedAssets = item.assigned_assets ? `<span class="badge rounded-pill text-bg-secondary">${item.assigned_assets}</span>` : '<em class="text-muted">None Assigned</em>';

                     tableHTML += `<tr>
                                         <td><strong>${item.user_name || 'N/A'}</strong></td>
                                         <td>${item.item_name || 'N/A'}</td>
                                         <td>${assignedAssets}</td>
                                         <td>${returnDateFormatted}</td>
                                         <td><span class="badge rounded-pill text-bg-danger">${item.days_overdue}</span></td>
                                         <td>${phoneLink}</td>
                                     </tr>`;
                 });
                 tableHTML += '</tbody></table>';
                 overdueListContainer.innerHTML = tableHTML;

                 overdueModal.show();
                 
                  
                  setTimeout(() => {
                      const newTable = $(`#${tableId}`);
                      if (newTable.length) {
                          newTable.DataTable({
                              "pageLength": 10,
                              "order": [[4, "desc"]], 
                              "language": {
                                  "search": "Search:",
                                  "lengthMenu": "Show _MENU_ overdue items",
                                  "info": "Showing _START_ to _END_ of _TOTAL_ overdue items",
                                  "infoEmpty": "No overdue items found",
                                  "infoFiltered": "(filtered from _MAX_ total items)",
                                  "zeroRecords": "No matching overdue items found",
                                  "paginate": { "first": "First", "last": "Last", "next": "Next", "previous": "Previous" }
                              },
                              "destroy": true 
                          });
                      }
                  }, 200); 
            }
        });
    }

});
</script>

</body>
</html>