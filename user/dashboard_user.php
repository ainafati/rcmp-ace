<?php
session_start();


include '../config.php'; 
$allowed_roles = ['User', 'Technician', 'Admin'];

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

if (!isset($_SESSION['person_id']) || !isset($_SESSION['logged_in_role']) || !in_array($_SESSION['logged_in_role'], $allowed_roles)) {
    session_unset();
    session_destroy();
    header("Location: ../login.php"); 
    exit();
}

$user_id = (int) $_SESSION['person_id']; 


$limit = 5; 


function renderReservationTable($data, $status_filter_string, $current_page = 1, $total_pages = 1, $total_rows = 0, $limit = 5, $is_paginated = false) {
    
    ob_start(); 
    ?>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th>Date Reserved</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No reservations matching this status found.</td></tr>
                    <?php else: 
                        $i = (($current_page - 1) * $limit) + 1; 
                        foreach($data as $r): 
                            $status = strtolower($r['status']);
                            $badge_class = 'secondary'; $info_icon = ''; 

                            if ($status == 'approved' || $status == 'checked out') {
                                $badge_class = 'success';
                            } elseif ($status == 'pending') {
                                $badge_class = 'warning';
                            } elseif ($status == 'rejected' || $status == 'completed') {
                                $badge_class = 'danger';
                                
                                if ($status == 'rejected' && !empty($r['rejection_reason'])) {
                                    $reason = htmlspecialchars($r['rejection_reason']);
                                    $info_icon = "<i class=\"fa-solid fa-circle-info ms-1 info-icon\" data-bs-toggle=\"tooltip\" data-bs-placement=\"top\" data-bs-title='Sebab: $reason'></i>";
                                }
                            }
                        ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><strong><?= htmlspecialchars($r['item_name']) ?></strong></td>
                            <td><?= date("d M Y", strtotime($r['reserve_date'])) ?></td>
                            <td>
                                <span class="badge rounded-pill text-bg-<?= $badge_class ?>"><?= htmlspecialchars(ucfirst($r['status'])) ?></span>
                                <?= $info_icon ?> 
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($is_paginated && $total_pages > 1): ?>
        <nav aria-label="Reservation Pagination" class="d-flex justify-content-center p-3">
            <ul class="pagination mb-0">
                <li class="page-item <?= ($current_page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link page-ajax-link" href="#" data-page="<?= max(1, $current_page - 1) ?>" data-status="<?= $status_filter_string ?>">Previous</a>
                </li>
                <?php 
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);

                if ($start_page > 1) { echo '<li class="page-item"><a class="page-link page-ajax-link" href="#" data-page="1" data-status="' . $status_filter_string . '">1</a></li>'; }
                if ($start_page > 2) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }

                for($i = $start_page; $i <= $end_page; $i++): ?>
                    <li class="page-item <?= ($i == $current_page) ? 'active' : '' ?>">
                        <a class="page-link page-ajax-link" href="#" data-page="<?= $i ?>" data-status="<?= $status_filter_string ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                
                <?php 
                if ($end_page < $total_pages - 1) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
                if ($end_page < $total_pages) { echo '<li class="page-item"><a class="page-link page-ajax-link" href="#" data-page="' . $total_pages . '" data-status="' . $status_filter_string . '">' . $total_pages . '</a></li>'; }
                ?>

                <li class="page-item <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link page-ajax-link" href="#" data-page="<?= min($total_pages, $current_page + 1) ?>" data-status="<?= $status_filter_string ?>">Next</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
    <?php
    return ob_get_clean(); 
}



if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
    $page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $limit; 

    $where_clause = "";
    $header_text = "";
    $bg_class = "";
    $icon_class = "";

    switch ($status_filter) {
        case 'approved,checked out':
            $where_clause = "AND (ri.status = 'Approved' OR ri.status = 'Checked Out')";
            $header_text = "Approved & Checked Out";
            $bg_class = "bg-success text-white";
            $icon_class = "fa-check-to-slot";
            break;
        case 'pending':
            $where_clause = "AND ri.status = 'Pending'";
            $header_text = "Pending Reservations";
            $bg_class = "bg-warning text-dark";
            $icon_class = "fa-triangle-exclamation";
            break;
        case 'rejected,completed':
            $where_clause = "AND (ri.status = 'Rejected' OR ri.status = 'Completed')";
            $header_text = "Completed & Rejected";
            $bg_class = "bg-danger text-white";
            $icon_class = "fa-ban";
            break;
        case 'all':
        default:
            $where_clause = "";
            $header_text = "All Reservations";
            $bg_class = "bg-primary text-white";
            $icon_class = "fa-table";
            break;
    }
    
    $count_sql = "
        SELECT COUNT(ri.id) AS total 
        FROM reservation_items ri 
        JOIN reservations r ON ri.reserve_id = r.reserve_id 
        WHERE r.person_id = ? $where_clause
    ";
    $stmt_count = $conn->prepare($count_sql);
    if ($stmt_count === false) { die("SQL Prepare Error (Count): " . $conn->error); }
    
    $stmt_count->bind_param("i", $user_id);
    $stmt_count->execute();
    $total_rows = $stmt_count->get_result()->fetch_assoc()['total'];
    $stmt_count->close();
    
    $total_pages = ceil($total_rows / $limit); 

    $data_sql = "
        SELECT ri.id, i.item_name, ri.reserve_date, ri.status, ri.rejection_reason 
        FROM reservation_items ri
        JOIN item i ON ri.item_id = i.item_id
        JOIN reservations r ON ri.reserve_id = r.reserve_id
        WHERE r.person_id = ? $where_clause
        ORDER BY ri.reserve_date DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt_data = $conn->prepare($data_sql);
    if ($stmt_data === false) { die("SQL Prepare Error (Data): " . $conn->error); }
    
    $stmt_data->bind_param("iii", $user_id, $limit, $offset);
    $stmt_data->execute();
    $paginated_data = $stmt_data->get_result()->fetch_all(MYSQLI_ASSOC); 
    $stmt_data->close();
    
    ?>
    <div class="card">
        <div class="card-header <?= $bg_class ?> py-3 fw-bold rounded-top-2">
            <i class="fa-solid <?= $icon_class ?> me-2"></i> <?= $header_text ?> (Showing <?= $offset + 1 ?>-<?= min($offset + $limit, $total_rows) ?> of <?= $total_rows ?> Items)
        </div>
        <?php 
        echo renderReservationTable($paginated_data, $status_filter, $page, $total_pages, $total_rows, $limit, true); 
        ?>
    </div>
    <?php
    exit; 
}




$stmt = $conn->prepare("SELECT name, email, phoneNum FROM person WHERE person_id = ?");
if ($stmt === false) { die("Database Error (User Info): " . $conn->error); }
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
}



$total = 0; $approved = 0; $pending = 0; $rejected_completed = 0;
$summary_sql = "
    SELECT
        COUNT(ri.id) AS total,
        SUM(CASE WHEN ri.status = 'Approved' OR ri.status = 'Checked Out' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN ri.status = 'Pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN ri.status = 'Rejected' OR ri.status = 'Completed' THEN 1 ELSE 0 END) AS rejected_completed
    FROM reservation_items ri
    JOIN reservations r ON ri.reserve_id = r.reserve_id
    WHERE r.person_id = ?
";
$stmt_summary = $conn->prepare($summary_sql);
if ($stmt_summary === false) { die("Database Error (Summary): " . $conn->error); }
$stmt_summary->bind_param("i", $user_id);
$stmt_summary->execute();
$summary_result = $stmt_summary->get_result();
if ($summary_row = $summary_result->fetch_assoc()) {
    $total = (int)$summary_row['total'];
    $approved = (int)$summary_row['approved']; 
    $pending = (int)$summary_row['pending'];
    $rejected_completed = (int)$summary_row['rejected_completed']; 
}
$stmt_summary->close();


$top_items = [];
$top_items_sql = "
    SELECT 
        i.item_name, 
        COUNT(ri.item_id) as reservation_count
    FROM reservation_items ri
    JOIN item i ON ri.item_id = i.item_id
    JOIN reservations r ON ri.reserve_id = r.reserve_id
    WHERE r.person_id = ?
    GROUP BY i.item_name
    ORDER BY reservation_count DESC
    LIMIT 5
";
$stmt_top = $conn->prepare($top_items_sql);
if ($stmt_top === false) { die("Database Error (Top Items): " . $conn->error); }
$stmt_top->bind_param("i", $user_id);
$stmt_top->execute();
$top_items = $stmt_top->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_top->close();





$due_date_limit = date('Y-m-d', strtotime('+3 days'));
$due_soon_sql = "
    SELECT ri.id, i.item_name, ri.return_date AS due_date  
    FROM reservation_items ri
    JOIN item i ON ri.item_id = i.item_id
    JOIN reservations r ON ri.reserve_id = r.reserve_id
    WHERE r.person_id = ? AND (ri.status = 'Approved' OR ri.status = 'Checked Out') 
    AND ri.return_date IS NOT NULL AND ri.return_date <= ? 
    ORDER BY ri.return_date ASC 
    LIMIT 3
";
$stmt_due = $conn->prepare($due_soon_sql);

if ($stmt_due === false) {
    die("SQL Prepare Error for Due Soon Items: " . $conn->error); 
}

$stmt_due->bind_param("is", $user_id, $due_date_limit);
$stmt_due->execute();
$due_soon_items = $stmt_due->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_due->close();



$newly_approved_sql = "
    SELECT ri.id, i.item_name, ri.reserve_date 
    FROM reservation_items ri
    JOIN item i ON ri.item_id = i.item_id
    JOIN reservations r ON ri.reserve_id = r.reserve_id
    WHERE r.person_id = ? AND ri.status = 'Approved'
    ORDER BY ri.reserve_date DESC
    LIMIT 3
";
$stmt_new_approved = $conn->prepare($newly_approved_sql);
if ($stmt_new_approved === false) { die("SQL Prepare Error (Newly Approved): " . $conn->error); }

$stmt_new_approved->bind_param("i", $user_id);
$stmt_new_approved->execute();
$newly_approved_items = $stmt_new_approved->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_new_approved->close();





$page_initial = 1;
$offset_initial = ($page_initial - 1) * $limit; 
$total_pages_initial = ceil($total / $limit); 

$initial_data_sql = "
    SELECT ri.id, i.item_name, ri.reserve_date, ri.status, ri.rejection_reason 
    FROM reservation_items ri
    JOIN item i ON ri.item_id = i.item_id
    JOIN reservations r ON ri.reserve_id = r.reserve_id
    WHERE r.person_id = ?
    ORDER BY ri.reserve_date DESC
    LIMIT ? OFFSET ?
";
$stmt_initial = $conn->prepare($initial_data_sql);

if ($stmt_initial === false) { die("Database Error (Initial Data): " . $conn->error); }

$stmt_initial->bind_param("iii", $user_id, $limit, $offset_initial);
$stmt_initial->execute();
$initial_reservations_data = $stmt_initial->get_result()->fetch_all(MYSQLI_ASSOC); 
$stmt_initial->close();



$notif_sql = "
    SELECT id, message, type, created_at 
    FROM notifications 
    WHERE person_id = ? AND is_read = 0 
    ORDER BY created_at DESC 
    LIMIT 5
";
$stmt_notif_list = $conn->prepare($notif_sql);
if ($stmt_notif_list === false) { die("Database Error (Notifications): " . $conn->error); }

$stmt_notif_list->bind_param("i", $user_id);
$stmt_notif_list->execute();
$new_notifications = $stmt_notif_list->get_result()->fetch_all(MYSQLI_ASSOC);
$new_notif_count = count($new_notifications);
$stmt_notif_list->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>User Dashboard — UniKL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

    <style>
        /* --- CSS STYLES (Kekal Sama) --- */
        :root {
            --primary-color: #06b6d4; 
            --primary-light: #f0f9ff; 
            --primary-hover: #0891b2; 
            --bg-light-gray: #f4f7f9; 
            --card-bg: #ffffff;
            --text-dark: #1e293b; 
            --text-muted: #64748b; 
            --shadow-light: 0 4px 12px rgba(0, 0, 0, 0.05); 
        }
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: var(--bg-light-gray); 
            color: var(--text-dark); 
            min-height: 100vh; 
        }
        .sidebar { 
            width: 280px;
            position: fixed; 
            top: 0; 
            bottom: 0; 
            left: 0; 
            background: var(--card-bg);
            padding: 20px; 
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
            z-index: 1000; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
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
        }
        .card:hover {
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.08); 
        }
        
        .card-summary { 
            text-align: left; 
            border: 1px solid #eef1f4;
            border-radius: 12px;
            overflow: hidden; 
            box-shadow: none; 
            transition: transform 0.2s, box-shadow 0.2s, outline 0.2s;
        }
        .card-summary:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1) !important;
        }
        
        .card-clickable {
            cursor: pointer;
        }
        .card-clickable.active {
            outline: 2px solid var(--primary-color); 
            box-shadow: 0 0 0 5px var(--primary-light), 0 6px 15px rgba(0, 0, 0, 0.1) !important; 
            transform: none !important; 
        }
        
        .card-summary .card-body { 
            padding: 25px !important;
            border-left: 5px solid; 
        }
        
        .card-summary h3 { font-weight: 700; font-size: 28px; margin: 0; color: var(--text-dark); }
        .card-summary p { font-size: 12px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; }
        .card-summary i { opacity: 0.8; }
        
        .border-left-primary { border-left-color: var(--primary-color) !important; background-color: var(--primary-light) !important; }
        .border-left-success { border-left-color: #22c55e !important; background-color: #f0fdf4 !important; } 
        .border-left-warning { border-left-color: #f59e0b !important; background-color: #fffbeb !important; } 
        .border-left-danger { border-left-color: #ef4444 !important; background-color: #fff7f7 !important; } 
        
        .card.pickup-schedule { 
            background-color: #ecfdf5 !important;
            border: 1px solid #14b8a6;
            border-left: 5px solid #14b8a6 !important; 
            padding: 30px; 
            box-shadow: var(--shadow-light);
        }
        
        /* New Styles for Timely Alerts */
        .alert-card {
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid;
            font-size: 14px;
        }
        .alert-card.alert-return {
            background-color: #fef3c7; 
            border-color: #f59e0b; 
            color: #b45309; 
        }
        .alert-card.alert-pickup {
            background-color: #d1fae5; 
            border-color: #10b981; 
            color: #065f46; 
        }

        .table-responsive { 
            padding: 0 30px 30px 30px; 
        }

        .table thead th { 
            background: #f1f5f9; 
            color: var(--text-muted); 
            border: none; 
            font-weight: 600; 
            text-transform: uppercase; 
            font-size: 11px; 
            padding: 12px 18px; 
        }
        .table tbody td { 
            border-bottom: 1px solid #eef1f4; 
            padding: 15px 18px; 
            vertical-align: middle; 
            font-size: 14px;
        }
        .table tbody tr:last-child td { border-bottom: none; }
        .table tbody tr:hover {
            background-color: #fafbfd; 
            cursor: pointer;
        }
        
        .badge { font-weight: 700; padding: 0.5em 0.8em; }
        
        #reservationTables .card-header {
            border-top-left-radius: 12px !important;
            border-top-right-radius: 12px !important;
            border-bottom: none;
            padding: 15px 30px !important;
        }
        
        .page-link {
            color: var(--primary-color);
            border-radius: 8px;
            margin: 0 3px;
        }
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            z-index: 1;
        }
        .pagination .page-item.disabled .page-link {
             color: var(--text-muted);
        }

        .menu-toggle-btn { display: none; }
        #overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 999; display: none; }

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
            .card-summary .card-body { padding: 20px !important; }
            .card-summary h3 { font-size: 24px; }
            .table-responsive { padding: 0 15px 15px 15px; }
            .table { min-width: 450px; font-size: 13px; }
            .table thead th, .table tbody td { padding: 10px 12px; }
        }
    </style>
</head>
<body>

<div id="overlay"></div> 

<div class="sidebar">
    <div>
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fa-solid fa-cube"></i></div>
            <div class="logo-text"><strong>UniKL User</strong><span>Equipment System</span></div>
        </div>
        <a href="dashboard_user.php" class="active"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        <a href="item_user.php"><i class="fa-solid fa-box"></i> Item Availability</a>
        <a href="history.php"><i class="fa-solid fa-clock-rotate-left"></i> History</a>
    </div>
    <a href="logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>

<div class="main-content">
    <div class="topbar">
        <button class="menu-toggle-btn" id="menuToggle">
            <i class="fa fa-bars"></i>
        </button>
        <h3>Dashboard</h3>
        <div class="user-profile">
            <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
            
            <div class="dropdown me-3" style="position: relative;">
                <button class="btn btn-link text-secondary p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-bell fa-xl"></i>
                    <?php if ($new_notif_count > 0): ?>
                        <span class="position-absolute translate-middle badge rounded-circle bg-danger border border-light p-1" style="top: 2px; right: -5px; font-size: 0.6em; z-index: 1001;">
                            <?= $new_notif_count ?>
                        </span>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" style="width: 300px;" id="notificationList">
                    <h6 class="dropdown-header">Notifications (<?= $new_notif_count ?> New)</h6>
                    <?php if ($new_notif_count > 0): ?>
                        <?php foreach($new_notifications as $notif): 
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
                        <li><span class="dropdown-item text-center text-muted">No new notifications.</span></li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <a href="profile.php" title="Go to My Profile" style="color: inherit; text-decoration: none;">
                <i class="fa-solid fa-circle-user fa-2x text-secondary"></i>
            </a>
        </div>
    </div>
    <div class="container-fluid">
        
        <div class="row mb-5">
            
            <div class="col-lg-3 col-sm-6 mb-4"> 
                <div class="card card-summary card-clickable  text-primary border-left-primary h-100" 
                    data-bs-toggle="collapse" 
                    data-bs-target="#tableCollapseTotal" 
                    aria-expanded="false" 
                    aria-controls="tableCollapseTotal" 
                    data-status-filter="all"> 
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted text-uppercase mb-1 small fw-bold">Total Reservations</p> 
                            <h3 class="mb-0"><?= $total ?></h3>
                        </div>
                        <i class="fa-solid fa-layer-group fa-3x text-primary"></i> 
                    </div>
                </div> 
            </div>
            
            <div class="col-lg-3 col-sm-6 mb-4"> 
                <div class="card card-summary card-clickable text-success border-left-success h-100"
                    data-bs-toggle="collapse" 
                    data-bs-target="#tableCollapseApproved" 
                    aria-expanded="false" 
                    aria-controls="tableCollapseApproved"
                    data-status-filter="approved,checked out"> 
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted text-uppercase mb-1 small fw-bold">Approved</p> 
                            <h3 class="mb-0"><?= $approved ?></h3>
                        </div>
                        <i class="fa-solid fa-circle-check fa-3x text-success"></i> 
                    </div>
                </div> 
            </div>

            <div class="col-lg-3 col-sm-6 mb-4"> 
                <div class="card card-summary card-clickable text-warning border-left-warning h-100"
                    data-bs-toggle="collapse" 
                    data-bs-target="#tableCollapsePending" 
                    aria-expanded="false" 
                    aria-controls="tableCollapsePending"
                    data-status-filter="pending"> 
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted text-uppercase mb-1 small fw-bold">Pending</p> 
                            <h3 class="mb-0"><?= $pending ?></h3>
                        </div>
                        <i class="fa-solid fa-hourglass-half fa-3x text-warning"></i> 
                    </div>
                </div> 
            </div>

            <div class="col-lg-3 col-sm-6 mb-4"> 
                <div class="card card-summary card-clickable text-danger border-left-danger h-100"
                    data-bs-toggle="collapse" 
                    data-bs-target="#tableCollapseRejected" 
                    aria-expanded="false" 
                    aria-controls="tableCollapseRejected"
                    data-status-filter="rejected,completed"> 
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted text-uppercase mb-1 small fw-bold">Rejected</p> 
                            <h3 class="mb-0"><?= $rejected_completed ?></h3>
                        </div>
                        <i class="fa-solid fa-circle-xmark fa-3x text-danger"></i> 
                    </div>
                </div> 
            </div>
        </div>
        <div id="reservationTables" class="mt-4">

            <div class="collapse multi-collapse mb-4" id="tableCollapseTotal" data-bs-parent="#reservationTables">
                <div class="card card-content-container" data-status-id="all">
                    <div class="card-header bg-primary text-white py-3 fw-bold rounded-top-2">
                        <i class="fa-solid fa-table me-2"></i> All Reservations (Showing <?= $offset_initial + 1 ?>-<?= min($offset_initial + $limit, $total) ?> of <?= $total ?> Items)
                    </div>
                    <?php 
                    echo renderReservationTable($initial_reservations_data, 'all', $page_initial, $total_pages_initial, $total, $limit, true); 
                    ?>
                </div>
            </div>

            <div class="collapse multi-collapse mb-4" id="tableCollapseApproved" data-bs-parent="#reservationTables">
                 <div class="card card-content-container" data-status-id="approved,checked out">
                    <div class="d-flex justify-content-center align-items-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <span class="ms-3 text-primary fw-bold">Loading Data...</span>
                    </div>
                </div>
            </div>

            <div class="collapse multi-collapse mb-4" id="tableCollapsePending" data-bs-parent="#reservationTables">
                 <div class="card card-content-container" data-status-id="pending">
                    <div class="d-flex justify-content-center align-items-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <span class="ms-3 text-primary fw-bold">Loading Data...</span>
                    </div>
                </div>
            </div>
            
            <div class="collapse multi-collapse mb-4" id="tableCollapseRejected" data-bs-parent="#reservationTables">
                 <div class="card card-content-container" data-status-id="rejected,completed">
                    <div class="d-flex justify-content-center align-items-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <span class="ms-3 text-primary fw-bold">Loading Data...</span>
                    </div>
                </div>
            </div>

        </div>
        <div class="row mt-4">
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-bold"><i class="fa-solid fa-id-card me-2 text-primary"></i> User Information</h5>
                        <hr class="mt-3 mb-3">
                        <div class="row small py-1">
                            <div class="col-sm-4 text-muted">Name:</div>
                            <div class="col-sm-8 fw-semibold"><?= htmlspecialchars($user['name']) ?></div>
                        </div>
                        <div class="row small py-1">
                            <div class="col-sm-4 text-muted">Email:</div>
                            <div class="col-sm-8 fw-semibold"><?= htmlspecialchars($user['email']) ?></div>
                        </div>
                        <div class="row small py-1">
                            <div class="col-sm-4 text-muted">Phone:</div>
                            <div class="col-sm-8 fw-semibold"><?= htmlspecialchars($user['phoneNum']) ?></div>
                        </div>
                        <div class="mt-4 text-end">
                            <a href="profile.php" class="btn btn-sm btn-primary">Update Profile <i class="fa-solid fa-arrow-right-long ms-2"></i></a>
                        </div>
                    </div>
                </div>
                
            </div>
            <div class="col-md-6 mb-3">
                <div class="card h-100 pickup-schedule">
                    <h5 class="fw-bold"><i class="fa-solid fa-calendar-days me-2" style="color:#14b8a6;"></i> Pickup Schedule</h5>
                    <hr class="mt-3 mb-3">
                    <div class="row fw-semibold">
                        <div class="col-6">Monday – Thursday:</div>
                        <div class="col-6">9:00 AM – 5:00 PM</div>
                    </div>
                    <div class="row mt-2 fw-semibold">
                        <div class="col-6">Friday:</div>
                        <div class="col-6">9:00 AM – 12:00 PM    (Break 1:00 PM – 2:45 PM)</div>
                    </div>
                    <p class="text-muted mt-3 mb-0 small">Make sure the item is picked up within the above working hours.</p>
                </div>
            </div>
        </div>

		        <div class="row mb-5">
            <div class="col-lg-6 mb-4">
                <div class="card p-4 h-100">
                    <h5 class="fw-bold mb-3"><i class="fa-solid fa-chart-pie me-2 text-primary"></i> Reservation Status Breakdown</h5>
                    <?php if ($total > 0): ?>
                        <div style="max-height: 350px;">
                             <canvas id="statusChart"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-secondary text-center py-5">No reservation data to display a chart.</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="card p-4 h-100">
                    <h5 class="fw-bold mb-3"><i class="fa-solid fa-ranking-star me-2 text-success"></i> Top 5 Most Reserved Items (Personal)</h5>
                    <?php if (!empty($top_items)): ?>
                        <div style="max-height: 350px;">
                            <canvas id="topItemsChart"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-secondary text-center py-5">No reservation history to determine top items.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>

const sidebar = document.querySelector('.sidebar');
const overlay = document.getElementById('overlay');
const menuToggle = document.getElementById('menuToggle');

function toggleSidebar() {
    sidebar.classList.toggle('active');
    
    if (sidebar.classList.contains('active')) {
        overlay.style.display = 'block';
    } else {
        overlay.style.display = 'none';
    }
}

if (menuToggle) {
    menuToggle.addEventListener('click', toggleSidebar);
}

if (overlay) { 
    overlay.addEventListener('click', toggleSidebar); 
}

window.addEventListener('resize', function() {
    if (window.innerWidth > 992 && sidebar.classList.contains('active')) {
        sidebar.classList.remove('active');
        overlay.style.display = 'none';
    }
});


document.addEventListener('DOMContentLoaded', function () {
    
    
    function initializeTooltips() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            var oldTooltip = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
            if (oldTooltip) { oldTooltip.dispose(); }
            
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                placement: tooltipTriggerEl.getAttribute('data-bs-placement') || 'bottom',
                html: true,
                title: tooltipTriggerEl.getAttribute('data-bs-title') 
            });
        });
    }
    
    
    function loadReservationTable(status_filter, page_num = 1) {
        const container = document.querySelector(`.card-content-container[data-status-id="${status_filter}"]`);
        if (!container) return;
        
        container.innerHTML = `
            <div class="d-flex justify-content-center align-items-center py-5">
                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                <span class="ms-3 text-primary fw-bold">Loading Page ${page_num}...</span>
            </div>
        `;

        fetch(`dashboard_user.php?ajax=1&status=${status_filter}&page=${page_num}`)
            .then(response => response.text())
            .then(html => {
                container.innerHTML = html;
                attachPaginationListeners(); 
                initializeTooltips();
            })
            .catch(error => {
                console.error('Error fetching paginated data:', error);
                container.innerHTML = '<div class="alert alert-danger p-4 m-4">Failed to load data. Please check network.</div>';
            });
    }
    
    
    function attachPaginationListeners() {
        document.querySelectorAll('.page-ajax-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const pageNum = this.getAttribute('data-page');
                const statusFilter = this.getAttribute('data-status');
                
                if (pageNum && statusFilter) {
                    loadReservationTable(statusFilter, pageNum);
                }
            });
        });
    }
    
    
    document.querySelectorAll('.card-clickable').forEach(card => {
        const targetId = card.getAttribute('data-bs-target');
        const targetCollapse = document.querySelector(targetId);
        const statusFilter = card.getAttribute('data-status-filter');
        
        if(targetCollapse) {
            
            card.addEventListener('click', function() {
                document.querySelectorAll('.card-clickable').forEach(c => c.classList.remove('active'));
                
                if (!targetCollapse.classList.contains('show')) {
                    this.classList.add('active');
                    loadReservationTable(statusFilter, 1); 
                }
            });
            
            targetCollapse.addEventListener('hidden.bs.collapse', function () {
                card.classList.remove('active');
            });
            targetCollapse.addEventListener('shown.bs.collapse', function () {
                card.classList.add('active');
            });
        }
    });

    
    document.querySelectorAll('.quick-view-approved').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const approvedCard = document.querySelector('.card-clickable[data-status-filter="approved,checked out"]');
            if (approvedCard) {
                
                approvedCard.click(); 
            }
        });
    });

    
    attachPaginationListeners();
    initializeTooltips();
    
    
    const total = <?= $total ?>;
    if (total > 0) {
        const statusData = {
            labels: [
                'Approved / On Loan',
                'Pending',
                'Completed / Rejected'
            ],
            datasets: [{
                label: 'Reservation Status',
                data: [<?= $approved ?>, <?= $pending ?>, <?= $rejected_completed ?>],
                backgroundColor: [
                    '#22c55e', 
                    '#f59e0b', 
                    '#ef4444'  
                ],
                hoverOffset: 4
            }]
        };

        new Chart(
            document.getElementById('statusChart'),
            {
                type: 'doughnut',
                data: statusData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20
                            }
                        },
                        title: {
                            display: false
                        }
                    }
                }
            }
        );
    }
    
    
    const topItemsData = <?= json_encode($top_items); ?>;
    
    if (topItemsData.length > 0) {
        const itemLabels = topItemsData.map(item => item.item_name);
        const itemCounts = topItemsData.map(item => item.reservation_count);

        const topItemsChartData = {
            labels: itemLabels,
            datasets: [
                {
                    label: 'Times Reserved',
                    data: itemCounts,
                    backgroundColor: [
                        '#06b6d4', 
                        '#10b981', 
                        '#8b5cf6',
                        '#f97316',
                        '#ef4444'
                    ],
                    borderColor: '#06b6d4',
                    borderWidth: 1,
                    borderRadius: 5,
                }
            ]
        };

        new Chart(
            document.getElementById('topItemsChart'),
            {
                type: 'bar',
                data: topItemsChartData,
                options: {
                    indexAxis: 'y', 
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false,
                        },
                        title: {
                            display: false,
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Reservations'
                            }
                        },
                        y: {
                            
                            ticks: {
                                font: {
                                    size: 10
                                }
                            }
                        }
                    }
                }
            }
        );
    }


    
    const markAllReadBtn = document.getElementById('markAllRead');
    
    const markAsRead = function(id = 'all') {
        fetch('update_notification.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=mark_read&id=${id}`
        })
        .then(response => {
             if (!response.ok) {
                 throw new Error('Server returned ' + response.status + ' status. Check network tab.');
             }
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.indexOf('application/json') !== -1) {
                return response.json();
            } else {
                 return response.text().then(text => {
                     throw new Error('Expected JSON response, received text/html: ' + text.substring(0, 100) + '...');
                 });
            }
        })
        .then(data => {
            if (data.success) {
                const badge = document.querySelector('.dropdown .badge');
                if (badge) {
                    badge.textContent = '0'; 
                    badge.style.display = 'none'; 
                }

                const header = document.querySelector('#notificationList h6');
                if (header) {
                    header.textContent = 'Notifications (0 New)';
                }

                const notifList = document.getElementById('notificationList');
                if (notifList) {
                    let children = Array.from(notifList.children);
                    for (let i = children.length - 1; i >= 1; i--) {
                        if(children[i].classList.contains('notif-item') || children[i].querySelector('.dropdown-divider') || children[i].querySelector('#markAllRead')) {
                           children[i].remove();
                        }
                    }
                    const noNotifLi = document.createElement('li');
                    noNotifLi.innerHTML = '<span class="dropdown-item text-center text-muted">No new notifications.</span>';
                    notifList.appendChild(noNotifLi);
                }
                
            } else {
                console.error('Failed to mark as read:', data.message);
                alert('Gagal menandakan notifikasi sebagai telah dibaca. Ralat: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error marking as read:', error);
            alert('Ralat rangkaian/server. Sila cuba lagi. (Lihat Console untuk butiran)');
        });
    };

    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            markAsRead('all');
        });
    }
});
</script>
</body>
</html>