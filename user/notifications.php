<?php
session_start();
include '../config.php'; 

if (!isset($_SESSION['person_id'])) {
    header("Location: ../login.php");
    exit();
}

// 1. Ambil data session
$person_id = $_SESSION['person_id'] ?? 0;

if (!$person_id) {
    header("Location: ../login.php");
    exit();
}

// 2. Tarik Data Technician
$stmt = $conn->prepare("SELECT name FROM person WHERE person_id = ?");
$techName = "Guest User";
if ($stmt) {
    $stmt->bind_param("i", $person_id);
    $stmt->execute();
    $resultTech = $stmt->get_result()->fetch_assoc();
    if ($resultTech) {
        $techName = $resultTech['name'];
    }
    $stmt->close();
}

// 3. Logik Potong Nama (Aina Fatihah Binti Jamil -> Aina Fatihah)
$role = "Staff/Student"; // Kita tambah balik variable yang hilang ni
$fullName = $techName;

// Kita cari kedudukan ' binti ' atau ' bin ' (case insensitive)
$lowerName = strtolower($fullName);
$posBinti = strpos($lowerName, ' binti');
$posBin = strpos($lowerName, ' bin');

if ($posBinti !== false) {
    $displayName = substr($fullName, 0, $posBinti);
} elseif ($posBin !== false) {
    $displayName = substr($fullName, 0, $posBin);
} else {
    $displayName = $fullName;
}

$displayName = trim($displayName); // Buang space kalau ada


// 2. Ambil notifikasi
$query = "SELECT * FROM notifications WHERE person_id = ? ORDER BY created_at DESC LIMIT 50";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $person_id); 
$stmt->execute();
$result = $stmt->get_result();
$notifications = $result->fetch_all(MYSQLI_ASSOC);

// 3. Logic Grouping ikut Tarikh (Sebab kau pakai foreach $grouped_notifications)
$grouped_notifications = [];
foreach ($notifications as $n) {
    $date = date('Y-m-d', strtotime($n['created_at']));
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime("-1 day"));

    if ($date == $today) {
        $label = "Today";
    } elseif ($date == $yesterday) {
        $label = "Yesterday";
    } else {
        $label = date('d M Y', strtotime($date));
    }
    $grouped_notifications[$label][] = $n;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification History | UniKL ACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css"> 
<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background-color: #f1f5f9;
    }

    /* Layout Fixes */
    .page-wrapper { transition: all 0.3s; }
    
    /* Desktop default padding */
    .content-container { padding: 30px 40px; max-width: 900px; margin: auto; }

    /* Header Section */
    .page-header { display: flex; align-items: center; margin-bottom: 20px; }
    .header-icon { 
        background: #ffffff; padding: 10px; border-radius: 10px; 
        margin-right: 12px; color: #475569; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    }
    .page-header h2 { font-size: 1.25rem; font-weight: 700; margin-bottom: 0; }
    .unread-count { color: #64748b; font-size: 0.8rem; }

    /* Date Label */
    .date-label { 
        font-weight: 700; color: #94a3b8; font-size: 0.65rem; 
        letter-spacing: 1px; margin: 20px 0 10px 0; text-transform: uppercase;
    }

    /* Notif Card */
    .notif-card {
        background: white; border-radius: 14px; border: 1px solid #f1f5f9;
        padding: 14px; margin-bottom: 10px; display: flex; align-items: flex-start;
        transition: all 0.2s ease;
    }
    .notif-card.unread { border-left: 4px solid #1e3a8a; }

    .icon-box {
        width: 40px; height: 40px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        margin-right: 12px; font-size: 0.9rem; flex-shrink: 0;
    }

    /* Colors */
    .bg-incoming { background: #eff6ff; color: #1d4ed8; }
    .badge-incoming { background: #dbeafe; color: #1e40af; }
    .bg-self { background: #f5f3ff; color: #6d28d9; }
    .badge-self { background: #ede9fe; color: #5b21b6; }
    .bg-alert { background: #fff7ed; color: #c2410c; }
    .badge-alert { background: #ffedd5; color: #9a3412; }
    .bg-system { background: #f8fafc; color: #475569; }
    .badge-system { background: #f1f5f9; color: #334155; }

    .notif-title { font-weight: 600; font-size: 0.9rem; color: #1e293b; margin-bottom: 2px; }
    .notif-desc { color: #64748b; font-size: 0.8rem; margin-bottom: 6px; line-height: 1.4; }
    .notif-meta { display: flex; align-items: center; gap: 10px; font-size: 0.7rem; color: #94a3b8; }
    
    .view-link { color: #0284c7; text-decoration: none; font-weight: 600; cursor: pointer; }
    .type-badge { font-size: 0.6rem; padding: 2px 6px; border-radius: 4px; font-weight: 600; margin-left: 6px; }

    /* Main Content Background */
    .main-content {
        min-height: 100vh;
        background-color: #f1f5f9;
        background-image: url('https://www.transparenttextures.com/patterns/cubes.png');
        background-attachment: fixed;
    }

    /* --- MOBILE OPTIMIZATION (THE MAGIC PART) --- */
    @media (max-width: 768px) {
        .page-wrapper { margin-left: 0 !important; }
        .sidebar { display: none; } /* Sembunyi sidebar asal */
        
        .content-container { 
            padding: 15px 12px; 
            margin-bottom: 80px; /* Supaya tak kena tutup dgn bottom nav */
        }

        .topbar { padding: 10px 15px; }
        .topbar h3 { font-size: 1.1rem; }

        .notif-card {
            padding: 12px;
            border-radius: 12px;
        }

        .icon-box {
            width: 36px;
            height: 36px;
            font-size: 0.85rem;
        }

        .notif-title {
            font-size: 0.85rem;
            display: block; /* Supaya emoji & text tak lari skrin */
        }

        .type-badge {
            display: inline-block;
            margin-top: 2px;
            margin-left: 0;
        }

        /* Hide "Back" text on mobile */
        .btn-back span { display: none; }
        .btn-back { padding: 8px 12px; border-radius: 50%; }
        
        /* Mobile Bottom Nav Bar */
        .mobile-bottom-nav {
            display: flex;
            position: fixed;
            bottom: 0; left: 0; right: 0;
            background: #1e293b;
            padding: 12px 0;
            justify-content: space-around;
            z-index: 1000;
            box-shadow: 0 -4px 10px rgba(0,0,0,0.1);
        }

        .mobile-bottom-nav a {
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.65rem;
            text-align: center;
        }

        .mobile-bottom-nav a.active { color: #06b6d4; }
        .mobile-bottom-nav a i { font-size: 1.2rem; display: block; margin-bottom: 4px; }
    }

    /* Desktop Bottom Nav - Hide */
    @media (min-width: 769px) {
        .mobile-bottom-nav { display: none; }
        .page-wrapper { margin-left: 260px; }
    }
	
	/* --- PREMIUM BACK BUTTON DESIGN --- */
.btn-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 10px 20px;
    border-radius: 50px; /* Bentuk pill/kapsul */
    background: #ffffff;
    border: 1px solid #e2e8f0;
    color: #1e293b;
    font-size: 0.85rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    margin-right: 15px;
}

/* Efek bila hover (Desktop) */
.btn-back:hover {
    background-color: #f8fafc;
    color: #2563eb; 
    border-color: #bfdbfe;
    transform: translateX(-5px); /* Gerak ke kiri sikit */
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

/* Efek bila klik (Active) */
.btn-back:active {
    transform: scale(0.95);
}

/* --- MOBILE OPTIMIZATION UNTUK BACK BUTTON --- */
@media (max-width: 768px) {
    .btn-back {
        padding: 0;
        width: 42px;    /* Jadikan bentuk bulat sempurna */
        height: 42px;
        border-radius: 50%;
        background: #ffffff;
        border: 2px solid #f1f5f9; /* Border tebal sikit di mobile */
    }

    .btn-back i {
        font-size: 1.1rem;
        margin: 0;
    }

    .btn-back span {
        display: none; /* Sembunyikan teks "Back" */
    }
}
</style>
</head>
<body style="background-color: #f8fafc;">

<div class="sidebar" id="admin-sidebar">
    <div>
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fa-solid fa-wrench"></i></div>
            <div class="logo-text"><strong>UniKL User</strong><br><span style="font-size: 0.85rem; color: #64748b;">Equipment System</span></div>
        </div>
        <div class="sidebar-nav">
            <a href="dashboard_user.php"><i class="fa-solid fa-house"></i> Dashboard</a>
            <a href="item_user.php"><i class="fa-solid fa-calendar-plus"></i> Book Equipment</a>
            <a href="history.php"><i class="fa-solid fa-clock-rotate-left"></i> My Loan</a>
        </div>
    </div>
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-link"><i class="fa-solid fa-sign-out-alt"></i> Logout</a> 
    </div> 	
</div>


<div class="main-content">
    <div class="topbar">
        <div class="topbar-left d-flex align-items-center">
        <a href="dashboard_user.php" class="btn-back">
            <i class="fa-solid fa-arrow-left"></i> 
            <span>Back</span>
        </a>

        <button id="sidebarToggle" class="btn d-none">
            <i class="fas fa-bars"></i>
        </button>
        <h3 class="mb-0">Notification History</h3>
    </div>
	<div class="topbar-right">
    <a href="profile.php" class="user-pill text-decoration-none d-flex align-items-center">
        <div class="text-end me-2">
            <div class="user-name" style="text-transform: capitalize; font-weight: 700; color: #1e293b; line-height: 1.2; font-size: 0.9rem;">
                <?= htmlspecialchars($displayName) ?>
            </div>
            <div class="user-role" style="font-size: 0.75rem; color: #64748b; font-weight: 500;">
                <?= $role ?>
            </div>
        </div>
        <div class="profile-avatar">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($displayName) ?>&background=06b6d4&color=fff" class="rounded-circle" width="35" alt="Profile">
        </div>
    </a>
</div>
    </div>

    <div class="content-container">
        <div class="page-header">
            <div class="header-icon"><i class="fa-solid fa-bell"></i></div>
            <div>
                <h2>History</h2>
                <div class="unread-count">Here is your recent activity log.</div>
            </div>
        </div>

        <?php if (empty($grouped_notifications)): ?>
            <div class="text-center py-5">
                <i class="fa-solid fa-box-open fa-3x text-muted mb-3"></i>
                <p class="text-muted">Tiada notifikasi setakat ini.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($grouped_notifications as $day => $items): ?>
            <div class="date-label"><?= $day ?></div>
            
            <?php foreach ($items as $row): 
    $msg = $row['message'];
    $is_unread = ($row['is_read'] == 0);

    // Default (System)
    $icon = "fa-info-circle"; $bgClass = "bg-system"; $badgeClass = "badge-system"; $typeText = "System"; $emoji = "📢";

    // 1. DIRI SENDIRI RESERVE (Technician as User)
    // Detect keyword 'Your request' atau 'You have'
    if (stripos($msg, 'Your request') !== false || stripos($msg, 'You have') !== false) {
        $icon = "fa-user-gear"; 
        $bgClass = "bg-self"; 
        $badgeClass = "badge-self"; 
        $typeText = "My Reserve";
        $emoji = "🛠️";
    } 
    // 2. ORANG LAIN RESERVE (Technician as Admin/Approver)
    // Detect keyword 'requires approval' atau nama orang lain
    elseif (stripos($msg, 'requires approval') !== false || stripos($msg, 'from') !== false) {
        $icon = "fa-box-archive"; 
        $bgClass = "bg-incoming"; 
        $badgeClass = "badge-incoming"; 
        $typeText = "Reservation";
        $emoji = "📦";
    }
    // 3. STOCK ALERT
    elseif (stripos($msg, 'Stock') !== false || stripos($msg, 'Low') !== false) {
        $icon = "fa-triangle-exclamation"; 
        $bgClass = "bg-alert"; 
        $badgeClass = "badge-alert"; 
        $typeText = "Alert";
        $emoji = "⚠️";
    }
?>
<div class="notif-card <?= $is_unread ? 'unread' : '' ?>">
    <div class="icon-box <?= $bgClass ?>">
        <i class="fa-solid <?= $icon ?>"></i>
    </div>

    <div class="flex-grow-1">
        <div class="d-flex align-items-center mb-1">
            <div class="notif-title"><?= $emoji ?> <?= htmlspecialchars($msg) ?></div>
            <span class="type-badge ms-2 <?= $badgeClass ?>"><?= $typeText ?></span>
        </div>
        
        <div class="notif-desc">
            <?php 
                if($typeText == "My Reserve") echo "This is your personal reservation record.";
                elseif($typeText == "Reservation") echo "Incoming request from a user. Please review.";
                else echo "System information and maintenance updates.";
            ?>
        </div>

        <div class="notif-meta">
            <span><i class="fa-regular fa-clock"></i> <?= date('H:i', strtotime($row['created_at'])) ?></span>
            <div class="view-link view-btn" data-id="<?= $row['related_id'] ?>" data-bs-toggle="modal" data-bs-target="#resModal">
                <i class="fa-regular fa-eye"></i> View Details
            </div>
        </div>
    </div>
</div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal fade" id="resModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 18px;">
            <div id="modalContent">
                <div class="p-5 text-center"><div class="spinner-border text-primary spinner-border-sm"></div></div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    $('.view-btn').on('click', function() {
        var resId = $(this).data('id');
        $('#modalContent').load('reservations.php?id=' + resId);
    });
});
</script>

</body>
</html>