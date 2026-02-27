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

$categories = [];
$cat_query = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name ASC");
if ($cat_query) {
    while ($row = mysqli_fetch_assoc($cat_query)) {
        $categories[] = $row;
    }
}

function renderReservationTable($data, $status_filter_string, $current_page = 1, $total_pages = 1, $total_rows = 0, $limit = 5, $is_paginated = false) {
    ob_start(); 
    ?>
    <div class="card-body p-0"> 
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th class="small text-muted">#</th>
                        <th class="small text-muted">ITEM NAME</th>
                        <th class="small text-muted">DATE RESERVED</th>
                        <th class="small text-muted">STATUS</th>
                        <th class="small text-muted text-center">REMARK</th> 
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = (($current_page - 1) * $limit) + 1;
                    
                    // GUNA FOREACH supaya dia loop semua data dalam array $data
                    if (!empty($data)):
                        foreach ($data as $row): 
                            $status_lower = strtolower($row['status']);
                            // Logic warna badge
                            $badge_class = 'bg-secondary-soft text-secondary';
                            if ($status_lower == 'approved' || $status_lower == 'completed') $badge_class = 'bg-success-soft text-success';
                            if ($status_lower == 'pending') $badge_class = 'bg-warning-soft text-warning';
                            if (in_array($status_lower, ['rejected', 'voided', 'cancelled'])) $badge_class = 'bg-danger-soft text-danger';
                    ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($row['item_name']) ?></td>
                        <td class="small text-muted">
                            <i class="fa-regular fa-calendar me-1"></i> <?= date('d M Y', strtotime($row['reserve_date'])) ?>
                        </td>
                        <td>
                            <span class="badge modern-badge <?= $badge_class ?>"><?= strtoupper($row['status']) ?></span>
                        </td>
                        <td class="text-center">
                            <?php if (!empty($row['rejection_reason'])): ?>
                                <button type="button" 
                                        class="btn btn-sm btn-light border shadow-sm rounded-circle" 
                                        data-bs-toggle="tooltip" 
                                        title="<?= htmlspecialchars($row['rejection_reason']) ?>"
                                        style="width: 32px; height: 32px; padding: 0; color: #dc3545;">
                                    <i class="fa-solid fa-circle-info"></i>
                                </button>
                            <?php else: ?>
                                <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php 
                        endforeach; 
                    else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        </div>
		<?php if ($is_paginated && $total_pages > 1): ?>
            <div class="card-footer bg-white border-top p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="small text-muted">
                        Showing <strong><?= (($current_page - 1) * $limit) + 1 ?></strong> to 
                        <strong><?= min($current_page * $limit, $total_rows) ?></strong> of 
                        <strong><?= $total_rows ?></strong> entries
                    </div>
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= ($current_page <= 1) ? 'disabled' : '' ?>">
    <a class="page-link page-ajax-link shadow-none" href="#" 
       data-status="<?= $status_filter_string ?>" 
       data-page="<?= $current_page - 1 ?>">
        <i class="fa-solid fa-chevron-left"></i>
    </a>
</li>

<?php for ($p = 1; $p <= $total_pages; $p++): ?>
    <li class="page-item <?= ($p == $current_page) ? 'active' : '' ?>">
        <a class="page-link page-ajax-link shadow-none" href="#" 
           data-status="<?= $status_filter_string ?>" 
           data-page="<?= $p ?>">
            <?= $p ?>
        </a>
    </li>
<?php endfor; ?>

<li class="page-item <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>">
    <a class="page-link page-ajax-link shadow-none" href="#" 
       data-status="<?= $status_filter_string ?>" 
       data-page="<?= $current_page + 1 ?>">
        <i class="fa-solid fa-chevron-right"></i>
    </a>
</li>
                        </ul>
                    </nav>
                </div>
            </div>
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
            $header_text = "Approved";
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
 $where_clause = "AND ri.status IN ('Rejected', 'Completed', 'Voided', 'Cancelled')"; 
    $header_text = "Rejected / Selesai";
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
        SELECT ri.id, i.item_name, r.reserve_date, ri.status, ri.rejection_reason 
        FROM reservation_items ri
        JOIN item i ON ri.item_id = i.item_id
        JOIN reservations r ON ri.reserve_id = r.reserve_id
        WHERE r.person_id = ? $where_clause
        ORDER BY r.reserve_date DESC
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

// 2. BARU PROSES NAMA PENDEK (Gunakan logik yang kita bincang)
$fullName = $user['name'] ?? 'Guest User';
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


$total = 0; $approved = 0; $pending = 0; $rejected_completed = 0;
$summary_sql = "
    SELECT
        COUNT(ri.id) AS total,
        SUM(CASE WHEN ri.status IN ('Approved', 'Checked Out') THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN ri.status = 'Pending' THEN 1 ELSE 0 END) AS pending,
        /* Masukkan Voided di sini */
        SUM(CASE WHEN ri.status IN ('Rejected', 'Completed', 'Voided', 'Cancelled') THEN 1 ELSE 0 END) AS rejected_completed
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
    SELECT ri.id, i.item_name, r.return_date AS due_date  
    FROM reservation_items ri
    JOIN item i ON ri.item_id = i.item_id
    JOIN reservations r ON ri.reserve_id = r.reserve_id
    WHERE r.person_id = ? AND (ri.status = 'Approved' OR ri.status = 'Checked Out') 
    AND r.return_date IS NOT NULL AND r.return_date <= ? 
    ORDER BY r.return_date ASC 
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
    SELECT ri.id, i.item_name, r.reserve_date 
    FROM reservation_items ri
    JOIN item i ON ri.item_id = i.item_id
    JOIN reservations r ON ri.reserve_id = r.reserve_id
    WHERE r.person_id = ? AND ri.status = 'Approved'
    ORDER BY r.reserve_date DESC
    LIMIT 3
";
$stmt_new_approved = $conn->prepare($newly_approved_sql);
if ($stmt_new_approved === false) { die("SQL Prepare Error (Newly Approved): " . $conn->error); }

$stmt_new_approved->bind_param("i", $user_id);
$stmt_new_approved->execute();
$newly_approved_items = $stmt_new_approved->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_new_approved->close();


$stmt_stats = $conn->prepare("SELECT status, COUNT(*) as count FROM reservation_items ri 
                              JOIN reservations r ON ri.reserve_id = r.reserve_id 
                              WHERE r.person_id = ? GROUP BY status");
$stmt_stats->bind_param("i", $user_id);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_all(MYSQLI_ASSOC);

$chart_data = ['Approved' => 0, 'Pending' => 0, 'Rejected' => 0];
foreach($stats as $row) {
    $s = strtolower($row['status']);
    if($s == 'approved' || $s == 'checked out') $chart_data['Approved'] += $row['count'];
    if($s == 'pending') $chart_data['Pending'] += $row['count'];
    if(in_array($s, ['rejected', 'voided', 'cancelled'])) $chart_data['Rejected'] += $row['count'];
}

$page_initial = 1;
$offset_initial = ($page_initial - 1) * $limit; 
$total_pages_initial = ceil($total / $limit); 

$initial_data_sql = "
    SELECT ri.id, i.item_name, r.reserve_date, ri.status, ri.rejection_reason 
    FROM reservation_items ri
    JOIN item i ON ri.item_id = i.item_id
    JOIN reservations r ON ri.reserve_id = r.reserve_id
    WHERE r.person_id = ?
    ORDER BY r.reserve_date DESC
    LIMIT ? OFFSET ?
";
$stmt_initial = $conn->prepare($initial_data_sql);

if ($stmt_initial === false) { die("Database Error (Initial Data): " . $conn->error); }

$stmt_initial->bind_param("iii", $user_id, $limit, $offset_initial);
$stmt_initial->execute();
$initial_reservations_data = $stmt_initial->get_result()->fetch_all(MYSQLI_ASSOC); 
$stmt_initial->close();


// --- GET RESERVATIONS FOR FULLCALENDAR ---
$calendar_events = [];
$calendar_sql = "
    SELECT 
        ri.id, 
        i.item_name, 
        r.reserve_date, 
        r.return_date, 
        ri.status
    FROM reservation_items ri
    JOIN item i ON ri.item_id = i.item_id
    JOIN reservations r ON ri.reserve_id = r.reserve_id
    WHERE r.person_id = ? AND ri.status IN ('Approved', 'Checked Out')
";
$stmt_calendar = $conn->prepare($calendar_sql);

if ($stmt_calendar === false) { die("SQL Prepare Error (Calendar): " . $conn->error); }

$stmt_calendar->bind_param("i", $user_id);
$stmt_calendar->execute();
$calendar_data = $stmt_calendar->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_calendar->close();

// Format data ke dalam format FullCalendar
foreach ($calendar_data as $res) {
    $title = $res['item_name'];
    $start_date = $res['reserve_date'];
    $end_date = $res['return_date'];
    $status = strtolower($res['status']);
    
    // Tentukan warna berdasarkan status
    $color = '#06b6d4'; // Primary Blue (Approved/Checked Out)
    if ($status === 'checked out') {
        $color = '#22c55e'; // Success Green
    }
    
    // FullCalendar memerlukan tarikh akhir (end) ditambah 1 hari 
    // untuk memastikan acara pada hari akhir dipaparkan sepenuhnya.
    $end_date_fc = date('Y-m-d', strtotime($end_date . ' +1 day'));

    $calendar_events[] = [
        'title' => $title,
        'start' => $start_date,
        'end' => $end_date_fc, // Penting: +1 day
        'color' => $color,
        'extendedProps' => ['reservation_id' => $res['id'], 'status' => $res['status']]
    ];
}

// Convert data kepada JSON untuk kegunaan JavaScript
$calendar_events_json = json_encode($calendar_events);

// Tambah column 'is_read' dan 'related_id' supaya HTML kau tak error
$notif_sql = "
    SELECT id, message, type, created_at, is_read, related_id 
    FROM notifications 
    WHERE person_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
"; 
// Aku cadangkan LIMIT 10 supaya dropdown nampak penuh sikit
$stmt_notif_list = $conn->prepare($notif_sql);
if ($stmt_notif_list === false) { die("Database Error (Notifications): " . $conn->error); }

$stmt_notif_list->bind_param("i", $user_id);
$stmt_notif_list->execute();
$new_notifications = $stmt_notif_list->get_result()->fetch_all(MYSQLI_ASSOC);
$new_notif_count = count($new_notifications);
$stmt_notif_list->close();

// --- CHECK FOR RECENT VOIDED ITEMS (Untuk Alert Banner) ---
// Kita cari item yang status 'Voided' dalam masa 48 jam terakhir
$void_alert_sql = "
    SELECT i.item_name, ri.rejection_reason, r.reserve_date
    FROM reservation_items ri
    JOIN item i ON ri.item_id = i.item_id
    JOIN reservations r ON ri.reserve_id = r.reserve_id
    WHERE r.person_id = ? 
    AND ri.status = 'Voided'
    AND ri.id IN (
        SELECT id FROM reservation_items 
        WHERE status = 'Voided'
    )
    ORDER BY r.reserve_date DESC
    LIMIT 1
";
$stmt_void_alert = $conn->prepare($void_alert_sql);
$stmt_void_alert->bind_param("i", $user_id);
$stmt_void_alert->execute();
$recent_voided_item = $stmt_void_alert->get_result()->fetch_assoc();
$stmt_void_alert->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>User Dashboard — UniKL Equipment System</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.13/main.min.css' rel='stylesheet' />
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.13/index.global.min.css' rel='stylesheet' />
    <link rel="stylesheet" href="../css/style.css">

    <style>
       :root {
    /* Gunakan satu identiti warna sahaja */
    --primary-color: #06b6d4;      /* Cyan asal anda */
    --primary-hover: #0891b2;
    --primary-light: #ecfeff;
    
    /* Warna Latar Belakang */
    --bg-color: #f8fafc;           /* Sangat bersih */
    --text-main: #1e293b;
    --text-muted: #94a3b8;
    
    --soft-shadow: 0px 10px 30px rgba(0, 0, 0, 0.05);
}

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-color) !important;
            color: var(--text-main);
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
    padding: 2rem;
    
    /* Memastikan background meliputi seluruh ruang */
    background-repeat: repeat;
}


        /* --- DASHBOARD HEADER --- */
        .page-title { font-weight: 800; letter-spacing: -1px; color: var(--text-main); margin-bottom: 5px; }
        .page-subtitle { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 30px; }


        
        /* --- ACTION CENTER (STYLING GAMBAR 3) --- */
        .action-item-soft {
            background: #fff9f0; /* Soft Peach/Orange */
            border: 1px solid #ffe8cc;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            transition: 0.2s;
        }
        .action-item-soft:hover { transform: translateY(-2px); }
        
        .badge-urgent {
            background: #fff0f0;
            color: #ff5b5b;
            font-weight: 700;
            font-size: 0.65rem;
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: uppercase;
        }

        /* --- CALENDAR OVERRIDE --- */
        #fullCalendarContainer { background: white; border-radius: 15px; }
        .fc-toolbar-title { font-size: 1.1rem !important; font-weight: 700 !important; color: var(--text-main); }
        .fc-button-primary { background-color: #f4f7fe !important; border: none !important; color: var(--text-main) !important; }
        
       
        canvas { max-height: 250px !important; }
		

/* Card Styling */
.card-custom-soft {
    border: none;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    background: #ffffff;
    padding: 20px;
    transition: transform 0.2s;
}

/* Sidebar Active State (Bagi sama dengan My Loan) */
.sidebar .nav-link.active {
    background-color: var(--theme-cyan) !important;
    color: white !important;
    box-shadow: 0 4px 15px rgba(6, 182, 212, 0.3);
}

/* Info Row Styling (Service Hours & Contact) */
.info-box {
    background: #f1f5f9;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
}

.info-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
}

.topbar h4.fw-bold {
    background: linear-gradient(90deg, #1e293b, #06b6d4);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    display: inline-block;
}
    /* Body Background - Dashboard standard mahal guna warna ni */
    body { background-color: #f8fafc; }

    /* Card Summary Styling */
    .card-exclusive {
        border: none !important;
        border-radius: 20px !important;
        transition: all 0.3s ease;
        background: #ffffff;
        overflow: hidden;
        position: relative;
    }

    .card-exclusive:hover {
        transform: translateY(-7px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.04) !important;
    }

    /* Decorative Top Border (Nampak lebih premium dari border-left) */
    .accent-primary { border-top: 5px solid #4361ee !important; }
    .accent-success { border-top: 5px solid #2ec4b6 !important; }
    .accent-warning { border-top: 5px solid #ff9f1c !important; }
    .accent-danger  { border-top: 5px solid #e71d36 !important; }

    /* Icon Glass Box */
    .icon-box-modern {
        width: 60px;
        height: 60px;
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
    }

    .bg-blue-soft { background: #eef2ff; color: #4361ee; }
    .bg-green-soft { background: #e7f9f7; color: #2ec4b6; }
    .bg-orange-soft { background: #fff7ed; color: #ff9f1c; }
    .bg-red-soft { background: #fff1f2; color: #e71d36; }

    /* Text Styling */
    .stat-label {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: #94a3b8;
    }

    .stat-value {
        font-size: 2rem;
        font-weight: 800;
        color: #1e293b;
        letter-spacing: -1px;
    }

    /* Table Container Styling */
    .table-exclusive-card {
        border: none;
        border-radius: 24px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.03);
    }

    .modern-badge {
        padding: 6px 14px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.7rem;
    }
	 /* Reset & Container Background */
    .dashboard-wrapper { background-color: #f8fafc; padding: 20px; }

   
    .card-exclusive:hover { transform: translateY(-7px); box-shadow: 0 15px 30px rgba(0,0,0,0.08) !important; }
    
    .accent-primary { border-top: 5px solid #4361ee !important; }
    .accent-success { border-top: 5px solid #2ec4b6 !important; }
    .accent-warning { border-top: 5px solid #ff9f1c !important; }
    .accent-danger  { border-top: 5px solid #e71d36 !important; }

    .icon-box-modern {
        width: 55px; height: 55px; border-radius: 15px;
        display: flex; align-items: center; justify-content: center; font-size: 1.3rem;
    }
    .bg-blue-soft   { background: #eef2ff; color: #4361ee; }
    .bg-green-soft  { background: #e7f9f7; color: #2ec4b6; }
    .bg-orange-soft { background: #fff7ed; color: #ff9f1c; }
    .bg-red-soft    { background: #fff1f2; color: #e71d36; }

    .stat-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.5px; }
    .stat-value { font-size: 1.8rem; font-weight: 800; color: #1e293b; letter-spacing: -1px; }

    /* Table Card Styling */
    .table-exclusive-card {
        border: none !important;
        border-radius: 24px !important;
        background: #ffffff !important;
        box-shadow: 0 10px 40px rgba(0,0,0,0.03) !important;
        overflow: hidden !important;
    }

    
	
	/* Pastikan table dalam modal tak kembang sangat */
#modalContentContainer .card {
    border: none !important;
    box-shadow: none !important;
}

#modalContentContainer .table td {
    padding: 10px 15px !important; /* Kecikkan padding */
}

.modal-xl {
    max-width: 900px; /* Jangan lebar sangat sampai nampak kosong */
}

.notification-dot {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 10px;
    height: 10px;
    background-color: #ef4444; /* Merah */
    border: 2px solid white;
    border-radius: 50%;
    animation: pulse-red 2s infinite;
}

@keyframes pulse-red {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}

/* --- FIX FULLCALENDAR BUTTON OVERFLOW --- */
.fc .fc-toolbar {
    flex-wrap: wrap; /* Bagi dia turun bawah kalau tak muat */
    gap: 10px;
    justify-content: center;
}

.fc .fc-toolbar-chunk {
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Kecikkan font butang month/week supaya muat */
.fc .fc-button {
    padding: 4px 8px !important;
    font-size: 0.75rem !important;
    text-transform: capitalize !important;
}

/* Fix untuk skrin paling kecil (Phone) */
@media (max-width: 576px) {
    .fc-toolbar-title {
        font-size: 0.9rem !important; /* Kecikkan tajuk bulan */
        width: 100%;
        text-align: center;
        order: -1; /* Letak tajuk kat atas sekali */
    }
    
    .fc .fc-toolbar {
        flex-direction: column; /* Susun menegak kat mobile */
    }
}
/* --- MOBILE BOTTOM NAV (THEMED TO SIDEBAR) --- */
@media (max-width: 991px) {
    body {
        padding-bottom: 80px; 
    }

    .mobile-bottom-nav {
        display: flex !important;
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        /* TUKAR KAT SINI: Guna warna gelap macam sidebar laptop */
        background: #1e293b; 
        border-top: 1px solid rgba(255,255,255,0.1); /* Border nipis supaya tak nampak kaku */
        z-index: 10000;
        justify-content: space-around;
        padding: 12px 0;
        box-shadow: 0 -5px 25px rgba(0,0,0,0.2);
    }

    .mobile-bottom-nav a {
        /* Warna icon/text masa tak tekan (kelabu cerah) */
        color: #94a3b8; 
        text-decoration: none !important;
        text-align: center;
        font-size: 11px;
        font-weight: 500;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        flex: 1;
        transition: all 0.3s ease;
    }

    /* Warna bila AKTIF atau HOVER (Cyan menyala) */
    .mobile-bottom-nav a.active, 
    .mobile-bottom-nav a:hover {
        color: #06b6d4; 
    }

    .mobile-bottom-nav a i {
        font-size: 20px;
        transition: transform 0.2s;
    }

    /* Effect sikit bila klik */
    .mobile-bottom-nav a:active i {
        transform: scale(0.9);
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
            <div class="logo-text"><strong>UniKL User</strong><br><span style="font-size: 0.85rem; color: #64748b;">Equipment System</span></div>
        </div>
        
        <div class="sidebar-nav">
  

  <a href="dashboard_user.php" class="active"><i class="fa-solid fa-house"></i> Dashboard</a>

 <a href="item_user.php"><i class="fa-solid fa-calendar-plus"></i> Book Equipment</a>
    <a href="history.php" class="nav-link"><i class="fa-solid fa-clock-rotate-left"></i> My Loan</a>
</div>
    </div>
    
<div class="sidebar-footer">
    <a href="logout.php" class="logout-link"><i class="fa-solid fa-sign-out-alt"></i> Logout</a> 
</div> 	
</div>



<div class="main-content">
<div class="topbar">
    <div class="topbar-left">
        <nav aria-label="breadcrumb">
            <p class="mb-0 text-muted small">Pages / Dashboard</p>
            <h4 class="fw-bold">Main Dashboard</h4>
        </nav>
    </div>
<div class="topbar-right">
    <div class="dropdown me-3">
        <div class="notification-wrapper" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer; position: relative;">
            <i class="fa-solid fa-bell"></i>
            <?php if (($new_notif_count ?? 0) > 0): ?>
                <span class="notification-dot"></span>
            <?php endif; ?>
        </div>
        
        <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="width: 320px; max-height: 450px; overflow-y: auto; border-radius: 15px;" id="notificationList">
            
            <div class="p-3 d-flex justify-content-between align-items-center border-bottom bg-light" style="border-radius: 15px 15px 0 0;">
                <h6 class="mb-0 fw-bold text-dark">Notifications</h6>
                <button type="button" id="markAllReadBtn" class="btn btn-sm fw-bold" 
    style="font-size: 0.7rem; background-color: #e0f2fe; color: #0369a1; border-radius: 8px; padding: 4px 10px;">
    Mark As Read
</button>
            </div>

           <div id="notifItemsContainer">
    <?php if (!empty($new_notifications)): // Guna variable dari Part 1 ?>
        <?php foreach($new_notifications as $notif): 
            $isUnread = (isset($notif['is_read']) && $notif['is_read'] == 0);
            $msg = $notif['message'];
            
            // Logic Warna & Icon
            $iconClass = 'text-secondary';
            $itemBg = $isUnread ? '#f8fafc' : '#ffffff';
            $accentColor = '#cbd5e1'; // Default grey

            if (stripos($msg, 'approved') !== false) { $iconClass = 'text-success'; $accentColor = '#22c55e'; }
            elseif (stripos($msg, 'rejected') !== false || stripos($msg, 'voided') !== false) { $iconClass = 'text-danger'; $accentColor = '#ef4444'; }
            elseif (stripos($msg, 'pending') !== false) { $iconClass = 'text-warning'; $accentColor = '#f59e0b'; }
        ?>
            <li class="notif-item-wrapper">
                <a class="dropdown-item p-3" href="history.php" 
                   style="background-color: <?= $itemBg ?>; border-left: 4px solid <?= $isUnread ? $accentColor : 'transparent' ?>; white-space: normal;">
                    <div class="d-flex align-items-start">
                        <div class="me-3 mt-1">
                            <i class="fa-solid fa-circle-info <?= $iconClass ?>"></i>
                        </div>
                        <div class="flex-grow-1">
                            <p class="mb-1 small <?= $isUnread ? 'fw-bold text-dark' : 'text-muted' ?>">
                                <?= htmlspecialchars($msg) ?>
                            </p>
                            <small class="text-muted" style="font-size: 0.65rem;">
                                <i class="fa-regular fa-clock me-1"></i><?= date('d M, H:i', strtotime($notif['created_at'])) ?>
                            </small>
                        </div>
                    </div>
                </a>
            </li>
        <?php endforeach; ?>
    <?php else: ?>
        <li class="p-4 text-center text-muted small">No new notifications</li>
    <?php endif; ?>
</div>
            <?php if (($new_notif_count ?? 0) > 0): ?>
                <li class="mark-all-container">
                    <a href="notifications.php" class="dropdown-item text-center small py-3 fw-bold text-primary" style="font-size: 0.8rem; border-top: 1px solid #eee; background-color: #f8f9fa;">
                    View All Notifications <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>

    <a href="profile.php" class="user-pill text-decoration-none">
        <div class="text-end me-2 d-none d-md-block">
            <div class="user-name" style="text-transform: capitalize; font-weight: 600; color: #1e293b; line-height: 1;">
                <?= htmlspecialchars($displayName) ?>
            </div>
            <small class="text-muted" style="font-size: 0.75rem;">Student/Staff</small>
        </div>
        <div class="profile-avatar">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($displayName) ?>&background=06b6d4&color=fff" class="rounded-circle" width="35">
        </div>
    </a>
</div>
</div> 

    <div class="container-fluid p-0">
        
        <h2 class="page-title">Dashboard</h2>
        <p class="page-subtitle">Welcome back! Here's your reservation overview.</p>

<div class="row mb-4">

    <div class="col-md-3 col-12 mb-3"> 
        <div class="card card-summary card-clickable border-0 shadow-sm border-left-primary h-100" 
             data-bs-toggle="modal" data-bs-target="#reservationModal" data-status-filter="all"> 
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <p class="text-muted text-uppercase mb-0 fw-bold" style="font-size: 0.65rem;">Total Reservations</p>
                    <h3 class="mb-0 text-primary" style="font-size: 1.4rem; font-weight: 800;"><?= $total ?></h3>
                </div>
                <div class="icon-circle bg-primary-light">
                    <i class="fa-solid fa-layer-group text-primary opacity-50"></i>
                </div>
            </div>
        </div> 
    </div>
   
    <div class="col-md-3 col-12 mb-3"> 
        <div class="card card-summary card-clickable border-0 shadow-sm border-left-success h-100"
             data-bs-toggle="modal" data-bs-target="#reservationModal" data-status-filter="approved,checked out"> 
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <p class="text-muted text-uppercase mb-0 fw-bold" style="font-size: 0.65rem;">Approved</p>
                    <h3 class="mb-0 text-success" style="font-size: 1.4rem; font-weight: 800;"><?= $approved ?></h3>
                </div>
                <i class="fa-solid fa-circle-check fa-2x text-success opacity-25"></i>
            </div>
        </div> 
    </div>

    <div class="col-md-3 col-12 mb-3"> 
        <div class="card card-summary card-clickable border-0 shadow-sm border-left-warning h-100"
             data-bs-toggle="modal" data-bs-target="#reservationModal" data-status-filter="pending"> 
            <div class="card-body d-flex align-items-center justify-content-between p-3">
                <div>
                    <p class="text-muted text-uppercase mb-0 fw-bold" style="font-size: 0.65rem;">Pending</p>
                    <h3 class="mb-0 text-warning" style="font-size: 1.4rem; font-weight: 800;"><?= $pending ?></h3>
                </div>
                <i class="fa-solid fa-hourglass-half fa-2x text-warning opacity-25"></i>
            </div>
        </div> 
    </div>
<div class="col-md-3 col-12 mb-3"> 
    <div class="card card-summary card-clickable border-0 shadow-sm border-left-danger h-100"
         data-bs-toggle="modal" data-bs-target="#reservationModal" data-status-filter="rejected,completed"> 
        <div class="card-body d-flex align-items-center justify-content-between p-3">
            <div>
                <p class="text-muted text-uppercase mb-0 fw-bold" style="font-size: 0.65rem;">Inactive Requests</p>
                <h3 class="mb-0 text-danger" style="font-size: 1.4rem; font-weight: 800;"><?= $rejected_completed ?></h3>
            </div>
            <i class="fa-solid fa-ban fa-2x text-danger opacity-25"></i>
        </div>
    </div> 
</div>
</div>

<div class="modal fade" id="reservationModal" tabindex="-1" aria-labelledby="reservationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered shadow-lg">
        <div class="modal-content border-0" style="border-radius: 20px; overflow: hidden;">
          <div class="modal-header border-bottom bg-white p-3">
    <div class="d-flex align-items-center">
        <div class="text-secondary me-2">
            <i class="fa-solid fa-list-ul"></i>
        </div>
        <div>
            <h6 class="modal-title fw-bold mb-0" id="modalTitle">Records</h6>
            <small class="text-muted" id="modalSubtitle" style="font-size: 0.7rem;"></small>
        </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

            <div class="modal-body p-0" id="modalContentContainer">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted small fw-bold">Fetching latest data...</p>
                </div>
            </div>
            <div class="modal-footer border-0 p-3 bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="font-size: 0.8rem;">Close</button>
            </div>
        </div>
    </div>
</div>		
        <div class="row g-4 mb-4">
    <div class="col-12"> <div class="card card-custom-soft shadow-sm border-0">
            <div class="card-body p-4"> <div class="d-flex align-items-center mb-4">
                    <div class="icon-wrapper bg-light text-primary p-2 rounded-3 me-2">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>
                    <h5 class="mb-0 fw-bold">Reservation Calendar</h5>
                </div>
                
               <div id="fullCalendarContainer" style="min-height: 500px; background: white;"></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card card-custom-soft shadow-sm border-0">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-2">
                    <div class="icon-wrapper bg-light text-warning p-2 rounded-3 me-2">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <h5 class="mb-0 fw-bold">Action Center</h5>
                </div>
                <p class="text-muted small mb-4">Pending returns & urgent tasks</p>

                <div class="action-list">
                    <?php if (empty($due_soon_items)): ?>
                        <div class="text-center py-4 bg-light rounded-4">
                            <i class="fa-solid fa-calendar-check text-muted mb-2 fa-2x"></i>
                            <p class="text-muted small mb-0">No urgent returns for now!</p>
                        </div>
                    <?php else: ?>
                       <?php foreach ($due_soon_items as $task): ?>
                            <div class="action-item-soft mb-2 p-3 d-flex align-items-center justify-content-between" style="background: #fffcf5; border: 1px solid #ffeeba; border-radius: 12px;">
                                <div class="d-flex align-items-center">
                                    <div class="bg-white rounded-circle p-2 me-3 shadow-sm text-warning d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fa-solid fa-clock"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 0.9rem;"><?= htmlspecialchars($task['item_name']) ?></div>
                                        <div class="text-muted small">Return by: <?= date('d M Y', strtotime($task['due_date'])) ?></div>
                                    </div>
                                </div>
                                <span class="badge bg-danger text-white px-2 py-1" style="font-size: 0.6rem; letter-spacing: 0.5px;">DUE SOON</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="text-center mt-3">
                    <a href="history.php" class="btn btn-link text-decoration-none small fw-bold p-0" style="font-size: 0.8rem;">
                        View All Tasks <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card card-custom-soft h-100 shadow-sm border-0">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="d-flex align-items-center">
                        <div class="icon-wrapper bg-light text-info p-2 rounded-3 me-2"><i class="fa-solid fa-chart-pie"></i></div>
                        <h5 class="mb-0 fw-bold">Request Overview</h5>
                    </div>
                    <div class="text-end">
                       <h4 class="mb-0 fw-bold"><?= $total ?></h4>
                        <small class="text-muted">Total</small>
                    </div>
                </div>
                <div class="d-flex justify-content-center">
                    <canvas id="statusChart" style="max-height: 250px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card card-custom-soft h-100 shadow-sm border-0">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-4">
                    <div class="icon-wrapper bg-light text-success p-2 rounded-3 me-2"><i class="fa-solid fa-chart-line"></i></div>
                    <h5 class="mb-0 fw-bold">Most Reserved Equipment</h5>
                </div>
                <div style="height: 250px;">
                    <canvas id="topItemsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row g-4 mt-2">
    <div class="col-md-6">
        <div class="card card-custom-soft h-100">
            <div class="d-flex align-items-center mb-4">
                <div class="icon-wrapper bg-light text-primary p-2 rounded-3 me-2">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <h5 class="mb-0 fw-bold">Service Hours</h5>
            </div>
            
            <div class="info-box">
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Monday - Thursday</span>
                        <span class="text-muted small">09:00 AM – 05:00 PM</span>
                    </div>
                </div>
            </div>

            <div class="info-box">
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Friday</span>
                        <span class="text-muted small">09:00 AM – 12:00 PM</span>
                    </div>
                </div>
            </div>
            <p class="text-muted small mt-2"><i>Note: Closed during public holidays.</i></p>
        </div>
    </div>

<div class="col-md-6">
        <div class="card card-custom-soft h-100 p-4"> <div class="d-flex align-items-center mb-4">
                <div class="icon-wrapper bg-light text-success p-2 rounded-3 me-2">
                    <i class="fa-solid fa-address-book"></i> </div>
                <h5 class="mb-0 fw-bold">Contact & Location</h5>
            </div>

            <div class="info-box mb-3 d-flex align-items-center">
                <div class="info-icon bg-blue-light text-primary me-3" style="width: 40px; text-align: center;">
                    <i class="fa-solid fa-location-dot"></i> </div>
                <div>
                    <span class="d-block small text-muted">Location:</span>
                    <span class="fw-bold">No 3, Jalan Greentown, 30450 Ipoh Perak</span>
                </div>
            </div>

            <div class="info-box mb-3 d-flex align-items-center">
                <div class="info-icon bg-red-light text-danger me-3" style="width: 40px; text-align: center;">
                    <i class="fa-solid fa-phone"></i> </div>
                <div>
                    <span class="d-block small text-muted">Contact No:</span>
                    <span class="fw-bold">1 300-22-7267</span>
                </div>
            </div>

            <div class="info-box d-flex align-items-center">
                <div class="info-icon bg-success-light text-success me-3" style="width: 40px; text-align: center;">
                    <i class="fa-solid fa-envelope"></i> </div>
                <div>
                    <span class="d-block small text-muted">Email Us:</span>
                    <span class="fw-bold">+605-2432 636</span>
                </div>
            </div>
        </div>
    </div></div>
    </div> 
	</div> 
	
	<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <?php if (false): ?>
    <div id="voidToast" class="toast show border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header bg-danger text-white">
            <i class="fa-solid fa-circle-exclamation me-2"></i>
            <strong class="me-auto">Application Cancelled</strong>
            <small>Just now</small>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close" onclick="dismissToast(<?= $recent_voided_item['id'] ?>)"></button>
        </div>
        <div class="toast-body">
            Item <strong><?= htmlspecialchars($recent_voided_item['item_name']) ?></strong> has been canceled.
            <div class="mt-2 pt-2 border-top small text-muted">
                Reason: <?= htmlspecialchars($recent_voided_item['rejection_reason']) ?>
            </div>
        </div>
    </div>
  <?php endif; ?>
</div>
	
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.13/index.global.min.js'></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>

// --- 1. SIDEBAR & OVERLAY LOGIC ---
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

if (menuToggle) menuToggle.addEventListener('click', toggleSidebar);
if (overlay) overlay.addEventListener('click', toggleSidebar);

window.addEventListener('resize', function() {
    if (window.innerWidth > 992 && sidebar.classList.contains('active')) {
        sidebar.classList.remove('active');
        overlay.style.display = 'none';
    }
});

// --- 2. FULLCALENDAR LOGIC ---
const calendarEl = document.getElementById('fullCalendarContainer');
const calendarEventsJson = <?= $calendar_events_json ?? '[]' ?>; 

if (calendarEl && typeof FullCalendar !== 'undefined') {
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
		handleWindowResize: true,
    windowResize: function(arg) {
        if (window.innerWidth < 768) {
            calendar.setOption('aspectRatio', 0.8); // Lebih tinggi kat phone
        } else {
            calendar.setOption('aspectRatio', 2); // Maintain lebar kat PC
        }
    },
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek'
        },
        events: calendarEventsJson,
        height: 'auto',
        contentHeight: 750,
        aspectRatio: 2,
        editable: false,
        navLinks: true, 
        eventClick: function(info) {
            if (info.event.url) {
                window.open(info.event.url);
                info.jsEvent.preventDefault();
            }
        },
    });
    calendar.render();
}
// --- 3. MODAL AJAX TABLE LOGIC (FIXED) ---
function loadReservationTable(status, label = "Records", count = "", page = 1) {
    const modalContent = document.getElementById('modalContentContainer');
    const modalTitle = document.getElementById('modalTitle');
    const modalSubtitle = document.getElementById('modalSubtitle');

    if (modalTitle) modalTitle.innerText = label.toUpperCase();
    if (modalSubtitle) modalSubtitle.innerText = count ? `Viewing ${count} records for ${label}` : "";
    
    modalContent.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
            <p class="mt-2 text-muted small">Fetching latest data...</p>
        </div>`;

    fetch(`dashboard_user.php?ajax=1&status=${status}&page=${page}`)
        .then(response => response.text())
        .then(data => {
            modalContent.innerHTML = data;
            
            // PENTING: Re-attach listener untuk butang paging yang baru masuk
            attachModalPagination(label); 

            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        })
        .catch(error => {
            console.error('Error:', error);
            modalContent.innerHTML = '<div class="p-4 text-center text-danger small">Failed to load data.</div>';
        });
}

function attachModalPagination(currentLabel) {
    // Cari semua link paging dalam modal
    document.querySelectorAll('.page-ajax-link').forEach(link => {
        link.onclick = function(e) {
            e.preventDefault();
            const status = this.getAttribute('data-status');
            const page = this.getAttribute('data-page');
            // Panggil semula fungsi dengan label yang betul
            loadReservationTable(status, currentLabel, "", page);
        };
    });
}

// --- 4. DOM CONTENT LOADED (CHARTS & LISTENERS) ---
document.addEventListener('DOMContentLoaded', function () {
    
    // Listener untuk Card Click (Dashboard Task Cards)
    document.querySelectorAll('.card-clickable').forEach(card => {
        card.addEventListener('click', function() {
            const status = this.getAttribute('data-status-filter');
            const label = this.querySelector('p') ? this.querySelector('p').innerText : "Tasks"; 
            const count = this.querySelector('h3') ? this.querySelector('h3').innerText : "";
            
            // Panggil fungsi ajax
            loadReservationTable(status, label, count);
        });
    });

    // Handle butang Quick View
    document.querySelectorAll('.quick-view-approved').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const approvedCard = document.querySelector('.card-clickable[data-status-filter="approved,checked out"]');
            if (approvedCard) approvedCard.click();
        });
    });

    // --- CHART LOGIC (DOUGHNUT) ---
    const total = <?= $total ?? 0 ?>;
    if (total > 0 && document.getElementById('statusChart')) {
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: ['Approved', 'Pending', 'Rejected'],
                datasets: [{
                    data: [<?= $approved ?? 0 ?>, <?= $pending ?? 0 ?>, <?= $rejected_completed ?? 0 ?>],
                    backgroundColor: ['#22c55e', '#f59e0b', '#ef4444'],
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }
    
    // --- CHART LOGIC (BAR - TOP ITEMS) ---
    const topItemsData = <?= json_encode($top_items ?? []); ?>;
    if (topItemsData.length > 0 && document.getElementById('topItemsChart')) {
        new Chart(document.getElementById('topItemsChart'), {
            type: 'bar',
            data: {
                labels: topItemsData.map(item => item.item_name),
                datasets: [{
                    label: 'Times Reserved',
                    data: topItemsData.map(item => item.reservation_count),
                    backgroundColor: ['#3b82f6', '#10b981', '#8b5cf6', '#f59e0b', '#ef4444'],
                    borderRadius: 10,
                    barThickness: 15,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { display: false, grid: { display: false } },
                    y: { grid: { display: false }, ticks: { font: { size: 11 } } }
                }
            }
        });
    }

if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation(); // PENTING: Biar dropdown tak tertutup
            
            // Tambah loading state sikit bagi nampak pro
            this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
            this.disabled = true;
            
            markAsRead('all');
        });
    }
});
function markAsRead(id) {
    const params = new URLSearchParams();
    params.append('action', 'mark_read');
    params.append('id', id);

    fetch('update_notification.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Cara paling mudah: Refresh page untuk update semua UI (termasuk dashboard cards)
            window.location.reload(); 
        } else {
            alert('Gagal mengemaskini notifikasi.');
            // Reset butang jika gagal
            const btn = document.getElementById('markAllReadBtn');
            if(btn) {
                btn.disabled = false;
                btn.innerHTML = 'Mark As Read';
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Fungsi Dismiss Toast (Kekalkan di luar DOMContentLoaded jika dipanggil via onclick HTML)
function dismissToast(id) {
    const toastEl = document.getElementById('voidToast');
    const toast = bootstrap.Toast.getInstance(toastEl);
    if (toast) toast.hide();

    const params = new URLSearchParams();
    params.append('action', 'dismiss_void');
    params.append('id', id);

    fetch('update_notification.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
    })
    .then(response => response.json())
    .catch(error => console.error('Error:', error));
}
    // Aktifkan semua tooltips dalam page
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
	
	function dismissToast(id) {
    // Sembunyikan toast secara visual
    const toastEl = document.getElementById('voidToast');
    const toast = bootstrap.Toast.getInstance(toastEl);
    if (toast) toast.hide();

    // Hantar request ke server untuk "Mark as Read/Dismiss"
    fetch('update_notification.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=dismiss_void&id=${id}`
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) console.error('Failed to dismiss in database');
    })
    .catch(error => console.error('Error:', error));
}
</script>
<nav class="mobile-bottom-nav">
    <a href="dashboard_user.php" class="nav-item active">
        <i class="fa-solid fa-house"></i>
        <span>Dashboard</span>
    </a>
    <a href="item_user.php" class="nav-item">
        <i class="fa-solid fa-calendar-plus"></i>
        <span>Book Equipment</span>
    </a>
    <a href="history.php" class="nav-item">
        <i class="fa-solid fa-clock-rotate-left"></i>
        <span>My Loan</span>
    </a>
    <a href="profile.php" class="nav-item">
        <i class="fa-solid fa-user"></i>
        <span>Profile</span>
    </a>
</nav></body>
</html>

