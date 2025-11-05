<?php
session_start();
include 'config.php'; // Pastikan path ke config betul

// Check database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// pastikan user login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// ambil maklumat user
// Asumsi: Jadual pengguna adalah 'users' atau 'user'. Kita kekalkan 'user' seperti dalam kod asal.
$stmt = $conn->prepare("SELECT name, email, phoneNum FROM user WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// --- LOGIK DATABASE UNTUK DASHBOARD ---

// 1. Kad Ringkasan (Summary Cards)
$total = 0; $approved = 0; $pending = 0; $rejected = 0;
$summary_sql = "SELECT
                    COUNT(ri.id) AS total,
                    SUM(CASE WHEN ri.status = 'Approved' THEN 1 ELSE 0 END) AS approved,
                    SUM(CASE WHEN ri.status = 'Pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN ri.status = 'Rejected' THEN 1 ELSE 0 END) AS rejected
                FROM reservation_items ri
                JOIN reservations r ON ri.reserve_id = r.reserve_id
                WHERE r.user_id = ?";
$stmt_summary = $conn->prepare($summary_sql);
$stmt_summary->bind_param("i", $user_id);
$stmt_summary->execute();
$summary_result = $stmt_summary->get_result();
if ($summary_row = $summary_result->fetch_assoc()) {
    $total = (int)$summary_row['total'];
    $approved = (int)$summary_row['approved'];
    $pending = (int)$summary_row['pending'];
    $rejected = (int)$summary_row['rejected'];
}
$stmt_summary->close();


// 2. Jadual Tempahan Terkini (Recent Reservations)
$recent_sql = "SELECT
                    i.item_name,
                    ri.reserve_date,
                    ri.status
                FROM reservation_items ri
                JOIN item i ON ri.item_id = i.item_id
                JOIN reservations r ON ri.reserve_id = r.reserve_id
                WHERE r.user_id = ?
                ORDER BY ri.reserve_date DESC
                LIMIT 5";
$stmt_recent = $conn->prepare($recent_sql);
$stmt_recent->bind_param("i", $user_id);
$stmt_recent->execute();
$recent_result = $stmt_recent->get_result();
$recent = $recent_result->fetch_all(MYSQLI_ASSOC);
$stmt_recent->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>User Dashboard — UniKL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* DEFINING TEAL COLOR AS PRIMARY & MODERN STYLING */
        :root {
            --primary-color: #06b6d4; /* Cyan 600 */
            --primary-hover: #0891b2; /* Cyan 700 */
            --bg-light-gray: #f8fafc; /* Latar belakang utama yang sangat lembut */
            --card-bg: #ffffff;
            --text-dark: #1e293b;
            --text-muted: #64748b;
        }

        /* CSS DEFAULT DESKTOP */
        body { 
            font-family: 'Inter', 'Segoe UI', sans-serif; 
            background-color: var(--bg-light-gray); 
            color: var(--text-dark); 
            min-height: 100vh; 
        }
        .sidebar { 
            width: 250px; 
            position: fixed; 
            top: 0; 
            bottom: 0; 
            left: 0; 
            background: var(--card-bg);
            padding: 20px; 
            border-right: 1px solid #e2e8f0; 
            z-index: 1000; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
            transition: transform 0.3s ease-in-out; 
        }
        .sidebar-header { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
        .logo-icon { width: 40px; height: 40px; background-color: var(--primary-color); color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .logo-text strong { display: block; font-size: 16px; color: var(--text-dark); }
        .logo-text span { font-size: 12px; color: var(--text-muted); }
        .sidebar a { display: flex; align-items: center; gap: 12px; color: var(--text-muted); text-decoration: none; padding: 12px 15px; margin-bottom: 8px; border-radius: 8px; font-weight: 500; font-size: 15px; transition: all 0.2s; }
        .sidebar a.active, .sidebar a:hover { background: var(--primary-color); color: #fff; }
        .sidebar a.logout-link { color: #ef4444; font-weight: 600; margin-top: auto; }
        .main-content { margin-left: 250px; transition: margin-left 0.3s ease-in-out; }
        .topbar { 
            background: var(--card-bg); 
            padding: 15px 30px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 1px solid #e2e8f0; 
            z-index: 999; 
            position: sticky; 
            top: 0; 
        }
        .topbar h3 { font-weight: 600; margin: 0; color: var(--text-dark); font-size: 22px; }
        .topbar .user-profile { display: flex; align-items: center; gap: 12px; }
        .topbar .user-name { font-weight: 600; font-size: 15px; color: var(--text-dark); }
        .container-fluid { padding: 30px; }
        .card { 
            border-radius: 16px; 
            padding: 30px; 
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.08); 
            background: var(--card-bg); 
            margin-bottom: 25px; 
            border: none; 
        }
        .card h5 { font-weight: 600; color: var(--text-dark); margin-bottom: 15px; }
        .card-summary { 
            text-align: left; 
            padding: 20px; 
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05); 
            transition: transform 0.2s;
        }
        .card-summary:hover { transform: translateY(-3px); }
        .card-summary h3 { font-weight: 700; font-size: 28px; margin: 10px 0 5px; color: var(--text-dark); }
        .card-summary p { font-size: 14px; color: var(--text-muted); font-weight: 500; }
        .card-summary i { font-size: 24px; margin-bottom: 10px; }
        
        /* Warna Badge & Ikon */
        .text-primary i { color: var(--primary-color) !important; } 
        .card-summary.text-primary i { color: var(--primary-color); }
        .card-summary.text-success i { color: #22c55e; }
        .card-summary.text-warning i { color: #f59e0b; }
        .card-summary.text-danger i { color: #ef4444; }

        .table thead th { 
            background: var(--bg-light-gray); 
            color: var(--text-muted); 
            border: none; 
            font-weight: 600; 
            text-transform: uppercase; 
            font-size: 12px; 
            padding: 10px 15px; 
        }
        .table tbody td { border-bottom: 1px solid #f1f5f9; padding: 12px 15px; vertical-align: middle; }
        .table tbody tr:last-child td { border-bottom: none; }
        .badge { font-weight: 600; }
        .btn-primary { background-color: var(--primary-color); border-color: var(--primary-color); }
        .btn-primary:hover { background-color: var(--primary-hover); border-color: var(--primary-hover); }

        /* MOBILE STYLES START */
        .menu-toggle-btn { display: none; }
        #overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 999; display: none; }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-250px); left: 0; }
            .sidebar.active { transform: translateX(0); }
            .sidebar.active ~ #overlay { display: block; }
            .main-content { margin-left: 0; width: 100%; }
            .menu-toggle-btn { display: inline-block; order: -1; font-size: 20px; background: none; border: none; color: var(--text-dark); padding: 0; }
            .topbar { padding: 10px 15px; position: sticky; top: 0; display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 15px; }
            .topbar h3 { font-size: 18px; text-align: center; }
            .topbar .user-name { display: none; }
            .container-fluid { padding: 15px; }
            .card { padding: 15px; }
            .row.mb-4 > [class*="col-"] { flex: 0 0 50%; max-width: 50%; }
            .card-summary { padding: 10px; }
            .card-summary h3 { font-size: 22px; }
            .card-summary i { font-size: 20px; }
            .table-responsive { overflow-x: auto; }
            .table { min-width: 400px; font-size: 13px; }
            .table thead th { padding: 8px 10px; font-size: 11px; }
            .table tbody td { padding: 8px 10px; }
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
            <a href="profile.php" title="Go to My Profile" style="color: inherit; text-decoration: none;">
                <i class="fa-solid fa-circle-user fa-2x text-secondary"></i>
            </a>
        </div>
    </div>
    <div class="container-fluid">
        
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3"> 
                <div class="card card-summary text-primary"> <i class="fa-solid fa-list"></i> <h3><?= $total ?></h3> <p>Total Reservations</p> </div> 
            </div>
            <div class="col-md-3 col-sm-6 mb-3"> 
                <div class="card card-summary text-success"> <i class="fa-solid fa-circle-check"></i> <h3><?= $approved ?></h3> <p>Approved</p> </div> 
            </div>
            <div class="col-md-3 col-sm-6 mb-3"> 
                <div class="card card-summary text-warning"> <i class="fa-solid fa-hourglass-half"></i> <h3><?= $pending ?></h3> <p>Pending</p> </div> 
            </div>
            <div class="col-md-3 col-sm-6 mb-3"> 
                <div class="card card-summary text-danger"> <i class="fa-solid fa-circle-xmark"></i> <h3><?= $rejected ?></h3> <p>Rejected</p> </div> 
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <h5><i class="fa-solid fa-id-card me-2 text-primary"></i> User Information</h5>
                    <hr>
                    <div class="row">
                        <div class="col-sm-4 text-muted">Name:</div>
                        <div class="col-sm-8 fw-bold"><?= htmlspecialchars($user['name']) ?></div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-sm-4 text-muted">Email:</div>
                        <div class="col-sm-8 fw-bold"><?= htmlspecialchars($user['email']) ?></div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-sm-4 text-muted">Phone:</div>
                        <div class="col-sm-8 fw-bold"><?= htmlspecialchars($user['phoneNum']) ?></div>
                    </div>
                    <div class="mt-3 text-end">
                        <a href="profile.php" class="btn btn-sm btn-primary">Edit Profile <i class="fa-solid fa-arrow-right-long ms-2"></i></a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card h-100" style="background-color: #ecfdf5 !important; border-left: 5px solid #14b8a6;">
                    <h5><i class="fa-solid fa-calendar-days me-2" style="color:#14b8a6;"></i> Pickup Schedule</h5>
                    <hr>
                    <div class="row">
                        <div class="col-6"><strong>Monday – Thursday:</strong></div>
                        <div class="col-6">9:00 AM – 5:00 PM</div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-6"><strong>Friday:</strong></div>
                        <div class="col-6">9:00 AM – 12:00 PM</div>
                    </div>
                    <p class="text-muted mt-3 mb-0 small">Make sure the item is picked up within the above working hours.</p>
                </div>
            </div>
        </div>

        <div class="card">
            <h5><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i> Recent Reservations</h5>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead> <tr> <th>#</th> <th>Item</th> <th>Date Reserved</th> <th>Status</th> </tr> </thead>
                    <tbody>
                        <?php if (empty($recent)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No recent reservations found.</td></tr>
                        <?php else: $i=1; foreach($recent as $r): ?>
                            <?php
                                // Tentukan warna badge berdasarkan status
                                $status = strtolower($r['status']);
                                $badge_class = 'secondary'; // Lalai
                                if ($status == 'approved') $badge_class = 'success';
                                elseif ($status == 'pending') $badge_class = 'warning';
                                elseif ($status == 'rejected') $badge_class = 'danger';
                            ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($r['item_name']) ?></strong></td>
                                <td><?= date("d M Y", strtotime($r['reserve_date'])) ?></td>
                                <td><span class="badge rounded-pill text-bg-<?= $badge_class ?>"><?= htmlspecialchars(ucfirst($r['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// --- MOBILE SIDEBAR TOGGLE & OVERLAY CONTROL ---
const sidebar = document.querySelector('.sidebar');
const overlay = document.getElementById('overlay');
const menuToggle = document.getElementById('menuToggle');

function toggleSidebar() {
    sidebar.classList.toggle('active');
    
    // Kawal paparan overlay
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

// Tutup sidebar apabila mengubah saiz kepada desktop
window.addEventListener('resize', function() {
    if (window.innerWidth > 992 && sidebar.classList.contains('active')) {
        sidebar.classList.remove('active');
        overlay.style.display = 'none';
    }
});
</script>
</body>
</html>