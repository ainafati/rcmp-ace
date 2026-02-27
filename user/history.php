<?php
session_start();
include '../config.php';

// --- 1. MESTI CHECK LOGIN DULU ---
if (!isset($_SESSION['person_id'])) {
    header("Location: ../login.php");
    exit();
}

$person_id = (int)$_SESSION['person_id']; // Sekarang $person_id dah ada nilai


// LOGIK NAMA PENDEK (Dah betul)
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

// --- 2. PROSES PEMBATALAN (SOFT DELETE) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_id'])) {
    $cancel_id = (int)$_POST['cancel_id'];

    // Semak status & pemilik asal
    $check_sql = "SELECT ri.status, r.person_id FROM reservation_items ri 
                  JOIN reservations r ON ri.reserve_id = r.reserve_id 
                  WHERE ri.id = ?";
    $stmt_check = $conn->prepare($check_sql);
    $stmt_check->bind_param("i", $cancel_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result()->fetch_assoc();

    if (!$result) {
        $_SESSION['msg'] = "Error: Item not found.";
    } elseif ((int)$result['person_id'] !== $person_id) {
        $_SESSION['msg'] = "Error: Unauthorized action.";
    } elseif (strtolower($result['status']) !== 'pending') {
        $_SESSION['msg'] = "Item is already being processed and cannot be cancelled.";
    } else {
        // PROSES SOFT DELETE: Hanya tukar status sahaja
        $update_sql = "UPDATE reservation_items SET status = 'Cancelled' WHERE id = ?";
        $stmt_update = $conn->prepare($update_sql);
        $stmt_update->bind_param("i", $cancel_id);
        
        if ($stmt_update->execute()) {
            $_SESSION['msg'] = "Reservation has been successfully cancelled.";
        } else {
            $_SESSION['msg'] = "Database error.";
        }
        $stmt_update->close();
    }
    $stmt_check->close();

    header("Location: history.php?tab=active");
    exit();
}

// --- 3. AMBIL DATA UNTUK PAPARAN ---
// (Teruskan dengan kod ambil data user, pagination, dan fetch data macam biasa...)


if (!isset($_SESSION['person_id'])) {
    header("Location: ../login.php");
    exit();
}

$person_id = (int)$_SESSION['person_id'];

// --- 1. AMBIL DATA USER ---
$stmt_user = $conn->prepare("SELECT name FROM person WHERE person_id = ?");
$stmt_user->bind_param("i", $person_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

$fullName = $user_data['name'] ?? 'Guest User';

// 1. Buang " bin " atau " binti " dan semua teks selepasnya (Case Insensitive)
$cleanName = preg_split('/\s+(bin|binti)\s+/i', $fullName)[0];

// 2. Pecahkan nama kepada perkataan-perkataan individu
$nameParts = explode(' ', trim($cleanName));

// 3. Ambil 2 perkataan pertama sahaja
$shortNameArray = array_slice($nameParts, 0, 2);

// 4. Cantumkan semula dan tukar kepada Huruf Besar (Optional, ikut gambar anda)
$displayName = strtoupper(implode(' ', $shortNameArray));
// --- 2. LOGIK PAGINATION ---
$limit = 5; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'active'; 
$start = ($page - 1) * $limit;

// Tentukan filter status berdasarkan tab
// Tab 'active' = Pending/Approved, Tab 'completed' = Returned/Rejected/Cancelled
if ($active_tab == 'completed') {
    $status_condition = "ri.status IN ('Returned', 'Rejected', 'Cancelled', 'Voided')";
} else {
    $status_condition = "ri.status IN ('Pending', 'Approved')";
}

// --- 3. KIRA TOTAL (Mesti ada WHERE status yang sama) ---
$total_sql = "SELECT COUNT(*) FROM reservations r 
              JOIN reservation_items ri ON r.reserve_id = ri.reserve_id 
              WHERE r.person_id = ? AND $status_condition";
$stmt_total = $conn->prepare($total_sql);
$stmt_total->bind_param("i", $person_id);
$stmt_total->execute();
$total_items = $stmt_total->get_result()->fetch_row()[0];
$total_pages = ceil($total_items / $limit);
$stmt_total->close();

// --- 4. FETCH DATA (Mesti ada WHERE status yang sama) ---
$history = [];
$sql = "SELECT ri.id AS id, i.item_name, r.reserve_date, r.return_date, 
               r.reason, ri.status, ri.quantity, ri.rejection_reason -- TAMBAH NI
        FROM reservations r
        JOIN reservation_items ri ON r.reserve_id = ri.reserve_id
        JOIN item i ON ri.item_id = i.item_id
        WHERE r.person_id = ? AND $status_condition
        ORDER BY r.reserve_date DESC
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $person_id, $start, $limit);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My History — UniKL Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .nav-tabs { border: none; gap: 10px; }
        .nav-tabs .nav-link { 
            border: none !important; color: #64748b; font-weight: 600; padding: 8px 16px; border-radius: 10px !important; transition: all 0.3s; font-size: 0.85rem; 
        }
        .nav-tabs .nav-link.active { 
            background: var(--primary-color) !important; color: white !important; box-shadow: 0 4px 12px rgba(6, 182, 212, 0.2);
        }
        .table-separated { border-collapse: separate !important; border-spacing: 0 8px !important; }
        .table-separated tr { background: #ffffff; box-shadow: 0 2px 4px rgba(0,0,0,0.02); border-radius: 10px; }
        .table-separated td { border: none !important; padding: 12px 15px !important; vertical-align: middle; }
        .table-separated thead th { font-size: 0.7rem !important; letter-spacing: 0.05em; padding: 0 15px 5px !important; }
        .item-main-text { font-size: 0.9rem !important; font-weight: 700; color: #1e293b; margin-bottom: 2px; }
        .text-muted.small { font-size: 0.75rem !important; }
        .date-text { font-size: 0.85rem !important; font-weight: 600; }
        .badge { font-size: 0.65rem !important; font-weight: 700; padding: 5px 12px !important; text-transform: uppercase; letter-spacing: 0.02em; }
        .btn-sm { font-size: 0.7rem !important; padding: 5px 10px !important; border-radius: 8px !important; }
        .table-separated td:first-child { border-radius: 10px 0 0 10px; }
        .table-separated td:last-child { border-radius: 0 10px 10px 0; }
        .badge-pending { background: #fff7ed; color: #c2410c; }
        .badge-approved { background: #ecfdf5; color: #059669; }
        .badge-checked { background: #eff6ff; color: #2563eb; }
		.badge-voided { 
    background: #f1f5f9; /* Warna kelabu cair */
    color: #475569;      /* Warna teks slate gelap */
    border: 1px solid #e2e8f0;
}
        .badge-cancelled { background: #fef2f2; color: #dc2626; }
        .pagination { gap: 5px; }
        .page-link { border: none; border-radius: 8px !important; color: #64748b; font-weight: 600; font-size: 0.85rem; padding: 8px 14px; }
        .page-item.active .page-link { background-color: #06b6d4 !important; color: white !important; box-shadow: 0 4px 10px rgba(6, 182, 212, 0.2); }

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

        @media (max-width: 768px) {
            .table-separated thead { display: none; }
            .table-separated tr { display: block; margin-bottom: 15px; padding: 10px; border: 1px solid #f1f5f9; }
            .table-separated td { display: flex; justify-content: space-between; align-items: center; text-align: right; padding: 8px 5px !important; }
            .table-separated td::before { content: attr(data-label); font-weight: 700; text-transform: uppercase; font-size: 0.65rem; color: #94a3b8; float: left; }
            .item-main-text { font-size: 1rem !important; }
        }

        /* --- MOBILE BOTTOM NAV (THEMED DARK) --- */
@media (max-width: 991.98px) {
    .sidebar, #admin-sidebar, #sidebar-wrapper, .sidebar-header { 
        display: none !important; 
    }
    
    .main-content { 
        margin-left: 0 !important; 
        padding: 15px 10px 120px 10px !important; 
        width: 100% !important; 
    }

    .mobile-bottom-nav { 
        display: flex !important; 
        position: fixed; 
        bottom: 0; 
        left: 0; 
        width: 100%; 
        /* TUKAR KAT SINI: Guna warna navy gelap */
        background: #1e293b !important; 
        border-top: 1px solid rgba(255, 255, 255, 0.1); 
        z-index: 9999; 
        justify-content: space-around; 
        padding: 12px 0; 
        box-shadow: 0 -4px 15px rgba(0,0,0,0.2); 
    }

    .mobile-bottom-nav a { 
        /* Warna icon/teks masa tak aktif */
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
    }

    .mobile-bottom-nav a i { 
        font-size: 20px; 
    }

    /* Warna bila menu aktif (Cyan) */
    .mobile-bottom-nav a.active { 
        color: #06b6d4 !important; 
    }
}

        @media (min-width: 992px) { .mobile-bottom-nav { display: none !important; } }
		
		@media (max-width: 768px) {
    /* Pastikan setiap baris (row) dalam table mobile ada ruang */
    .table-separated tr {
        display: block;
        margin-bottom: 20px;
        padding: 15px !important;
        border: 1px solid #f1f5f9;
        border-radius: 16px !important; /* Bagi nampak lebih bulat & moden */
    }
@media (max-width: 768px) {
    /* Sembunyikan header table asal */
    .table-separated thead { display: none; }

    /* Kotak setiap rekod */
    .table-separated tr { 
        display: block; 
        margin-bottom: 20px; 
        padding: 15px !important; 
        background: #ffffff;
        border: 1px solid #f1f5f9;
        border-radius: 16px !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    }

    /* Susunan umum cell */
    .table-separated td { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        padding: 10px 5px !important;
        border-bottom: 1px solid #f8fafc !important;
    }

    /* KHAS UNTUK ITEM DETAILS - Biar dia susun atas bawah, tak bertindih */
    .table-separated td[data-label="Item Details"], 
    .table-separated td[data-label="Item"] {
        flex-direction: column;
        align-items: flex-start;
        text-align: left;
    }

    /* Label kecik kat tepi/atas */
    .table-separated td::before { 
        content: attr(data-label); 
        font-weight: 700; 
        text-transform: uppercase; 
        font-size: 0.65rem; 
        color: #94a3b8;
        margin-bottom: 4px;
    }

    /* Nama Barang - kasi bold dan besar sikit */
    .item-main-text { 
        font-size: 1rem !important; 
        font-weight: 800;
        color: #0f172a;
        display: block;
        width: 100%;
    }

    /* Kuantiti & Admin Remarks */
    .text-muted.small { 
        font-size: 0.8rem !important; 
        display: block;
    }

    /* Buang border bawah untuk cell terakhir dalam satu row */
    .table-separated td:last-child {
        border-bottom: none !important;
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
            <a href="history.php" class="active"><i class="fa-solid fa-clock-rotate-left"></i> My Loan</a>
        </div>
    </div>
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-link"><i class="fa-solid fa-sign-out-alt"></i> Logout</a> 
    </div> 	
</div>


<div class="main-content">
    <div class="topbar">
        <h3 class="m-0">Reservation History</h3>
        <div class="topbar-right">
            <a href="profile.php" class="user-pill text-decoration-none">
                <div class="text-end d-none d-sm-block me-2">
                    <div class="user-name fw-bold" style="color: #1e293b;"><?= htmlspecialchars($displayName) ?></div>
                    <div style="font-size: 0.7rem; color: #64748b;">Student / Staff</div>
                </div>
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($displayName) ?>&background=06b6d4&color=fff" class="rounded-circle" width="35" alt="avatar">
            </a>
        </div>
    </div>

    <div class="container-fluid">
        <?php if(isset($_SESSION['msg'])): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4"><?= $_SESSION['msg']; unset($_SESSION['msg']); ?></div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 p-4" style="border-radius: 24px;">
            <ul class="nav nav-tabs mb-4" id="loanTab" role="tablist">
    <li class="nav-item">
        <a href="history.php?tab=active" 
           class="nav-link <?= ($active_tab == 'active') ? 'active' : '' ?>">
           Active Requests
        </a>
    </li>
    <li class="nav-item">
        <a href="history.php?tab=completed" 
           class="nav-link <?= ($active_tab == 'completed') ? 'active' : '' ?>">
           Completed
        </a>
    </li>
</ul>
            <div class="tab-content">
               <div class="tab-pane fade <?= ($active_tab == 'active') ? 'show active' : '' ?>" id="active-tab">
                    <div class="table-responsive">
                        <table class="table table-separated">
                            <thead>
                                <tr class="text-muted small text-uppercase">
                                    <th style="padding-left: 20px;">Item Details</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th class="text-end" style="padding-right: 20px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
    <?php if(!empty($history)): ?>
        <?php foreach ($history as $loan): 
            $s = strtolower(trim($loan['status']));
            // Set warna badge berdasarkan status
            $statusBadge = 'badge-pending';
            if($s == 'approved') $statusBadge = 'badge-approved';
            if($s == 'checked out') $statusBadge = 'badge-checked';
        ?>
        <tr>
            <td data-label="Item Details">
                <div class="item-main-text"><?= htmlspecialchars($loan['item_name']) ?></div>
                <div class="text-muted small">Qty: <?= $loan['quantity'] ?> units</div>
            </td>
            <td data-label="Duration">
                <div class="date-text text-dark"><?= date("d M Y", strtotime($loan['reserve_date'])) ?></div>
                <div class="text-muted small">to <?= date("d M Y", strtotime($loan['return_date'])) ?></div>
            </td>
            <td data-label="Status">
                <span class="badge rounded-pill <?= $statusBadge ?>">
                    <i class="fa-solid fa-circle me-1" style="font-size: 0.3rem;"></i><?= strtoupper($s) ?>
                </span>
            </td>
            <td data-label="Action" class="text-end">
                <?php if($s == 'pending'): ?>
                    <form method="POST" onsubmit="return confirm('Cancel?');" style="display:inline;">
<input type="hidden" name="cancel_id" value="<?= $loan['id'] ?>">
                        <button type="submit" class="btn btn-sm fw-bold shadow-sm" style="background: #fee2e2; color: #ef4444; border: 1px solid #fecaca;">
                            <i class="fa-solid fa-xmark"></i> Cancel
                        </button>
                    </form>
                <?php else: ?>
                    <span class="text-muted small"><i class="fa-solid fa-lock"></i> Locked</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="4" class="text-center py-5 text-muted">
            <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" width="60" class="mb-3 opacity-25"><br>
            No active reservations found.
        </td></tr>
    <?php endif; ?>
</tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade <?= ($active_tab == 'completed') ? 'show active' : '' ?>" id="past-tab">
                    <div class="table-responsive">
                        <table class="table table-separated">
                            <thead>
    <tr class="text-muted small text-uppercase">
        <th style="padding-left: 20px;">Item</th>
        <th>Duration</th> <th>Outcome</th>
        <th style="padding-right: 20px;">Admin Remarks</th>
    </tr>
</thead>
                            <tr style="opacity: 1;"> 
 
<tbody>
    <?php 
    $hasHistory = false;
    foreach ($history as $loan): 
        $s = strtolower(trim($loan['status']));
        
        if(in_array($s, ['returned', 'rejected', 'cancelled', 'voided'])): 
            $hasHistory = true;
            
            // Set warna badge
            $outcomeClass = 'badge-cancelled'; 
            if($s == 'returned') $outcomeClass = 'badge-approved';
            if($s == 'voided') $outcomeClass = 'badge-voided';
    ?>
    <tr> <td data-label="Item">
            <div class="item-main-text"><?= htmlspecialchars($loan['item_name']) ?></div>
            <div class="text-muted small">Qty: <?= $loan['quantity'] ?></div>
        </td>

        <td data-label="Duration">
            <div class="date-text text-dark"><?= date("d M Y", strtotime($loan['reserve_date'])) ?></div>
            <div class="text-muted small">to <?= date("d M Y", strtotime($loan['return_date'])) ?></div>
        </td>

        <td data-label="Outcome">
            <?php if($s == 'voided'): ?>
                <span class="badge rounded-pill badge-voided">VOIDED</span>
            <?php else: ?>
                <span class="badge rounded-pill <?= $outcomeClass ?>"><?= strtoupper($s) ?></span>
            <?php endif; ?>
        </td>

        <td data-label="Admin Remarks" class="text-muted small">
            <?php 
                if($s == 'voided') {
                    echo '<i class="fa-solid fa-robot me-1"></i> ' . htmlspecialchars($loan['rejection_reason'] ?: 'Lapsed: No approval received.');
                } elseif($s == 'rejected') {
                    echo '<i class="fa-solid fa-comment-dots me-1" style="color: #ef4444;"></i> ' . htmlspecialchars($loan['rejection_reason'] ?: 'Rejected by Admin.');
                } elseif($s == 'returned') {
                    echo '<span class="text-success"><i class="fa-solid fa-check-double me-1"></i> Equipment successfully returned.</span>';
                } elseif($s == 'cancelled') {
                    echo '<i class="fa-solid fa-user-xmark me-1"></i> Cancelled by user.';
                }
            ?>
        </td>
    </tr>
    <?php endif; endforeach; 
    if(!$hasHistory): ?>
        <tr><td colspan="4" class="text-center py-5 text-muted small">Your history is empty.</td></tr>
    <?php endif; ?>
</tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
    <a class="page-link" href="?page=<?= $page - 1 ?>&tab=<?= $active_tab ?>"><i class="fa-solid fa-chevron-left"></i></a>
</li>

<?php for ($i = 1; $i <= $total_pages; $i++): ?>
    <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
        <a class="page-link" href="?page=<?= $i ?>&tab=<?= $active_tab ?>"><?= $i ?></a>
    </li>
<?php endfor; ?>

<li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
    <a class="page-link" href="?page=<?= $page + 1 ?>&tab=<?= $active_tab ?>"><i class="fa-solid fa-chevron-right"></i></a>
</li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Simpan tab aktif dalam localStorage bila user klik tab
    const tabs = document.querySelectorAll('button[data-bs-toggle="tab"]');
    tabs.forEach(tab => {
        tab.addEventListener('shown.bs.tab', function (e) {
            localStorage.setItem('activeTab', e.target.getAttribute('data-bs-target'));
        });
    });

    // 2. Bila page refresh, check localStorage atau URL fragment
    const activeTab = localStorage.getItem('activeTab');
    if (activeTab) {
        const tabTrigger = document.querySelector(`button[data-bs-target="${activeTab}"]`);
        if (tabTrigger) {
            const bsTab = new bootstrap.Tab(tabTrigger);
            bsTab.show();
        }
    }
});
</script>

<nav class="mobile-bottom-nav">
    <a href="dashboard_user.php"><i class="fa-solid fa-house"></i><span>Dashboard</span></a>
    <a href="item_user.php"><i class="fa-solid fa-calendar-plus"></i><span>Reservation</span></a>
    <a href="history.php" class="active"><i class="fa-solid fa-clock-rotate-left"></i><span>My Loan</span></a>
    <a href="profile.php"><i class="fa-solid fa-user"></i><span>Profile</span></a>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>