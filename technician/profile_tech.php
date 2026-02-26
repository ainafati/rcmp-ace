<?php
session_start();
include '../config.php';

// 1. Function untuk kira pending requests (Badge)
function get_pending_count($conn) {
    $sql = "SELECT COUNT(id) AS total FROM reservation_items WHERE LOWER(TRIM(status)) = 'pending'";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        return (int)$row['total'];
    }
    return 0;
}

$pending_count_for_badge = get_pending_count($conn); 

// 2. Pastikan pengguna log masuk
if (!isset($_SESSION['person_id'])) {
    header("Location: ../login.php");
    exit();
}

$person_id = (int)$_SESSION['person_id'];

// 3. Dapatkan butiran Juruteknik
$stmt = $conn->prepare("SELECT id AS staff_id, name, email, phoneNum FROM person WHERE person_id = ?");
$stmt->bind_param("i", $person_id);
$stmt->execute();
$result = $stmt->get_result();
$tech = $result->fetch_assoc();
$stmt->close();

if (!$tech) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// 4. Logik Nama Pendek (Shorten name logic)
$fullName = $tech['name'] ?? 'Technician';
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

// 5. Pengurusan Mod Edit (Jika redirect balik dari update_profile_tech.php ada error)
$keep_edit_mode = false;
if (isset($_SESSION['keep_edit_mode']) && $_SESSION['keep_edit_mode'] === true) {
    $keep_edit_mode = true;
    unset($_SESSION['keep_edit_mode']); 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — UniKL Technician</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            --glass-bg: rgba(255, 255, 255, 0.9);
        }

        body.profile-page {
            background-color: #f8fafc;
            font-family: 'Inter', sans-serif;
        }

        /* Profile Card Styling */
        .profile-header-card {
            background: var(--glass-bg);
            border-radius: 20px !important;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .avatar-main {
            width: 100px; 
            height: 100px; 
            background: var(--primary-gradient); 
            color: white; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 2.5rem; 
            font-weight: 900; 
            box-shadow: 0 10px 20px -5px rgba(6, 182, 212, 0.5);
            margin: 0 auto 1.5rem;
        }

        .info-box {
            background: #ffffff;
            border-radius: 12px;
            padding: 1rem;
            border: 1px solid #edf2f7;
            transition: all 0.3s ease;
        }

        .info-box:hover {
            border-color: #06b6d4;
            transform: translateY(-2px);
        }

        /* Password Requirement Styles */
        .pw-requirement {
            font-size: 0.8rem;
            color: #94a3b8;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            transition: color 0.3s ease;
        }

        .pw-requirement i { margin-right: 8px; font-size: 0.7rem; }
        
        .requirement-met {
            color: #10b981 !important; /* Green */
            font-weight: 600;
        }

        .btn-upgrade {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-upgrade:hover {
            box-shadow: 0 4px 12px rgba(6, 182, 212, 0.4);
            color: white;
        }

        /* Sidebar Badge Styling */
        .badge.rounded-pill {
            background-color: #ef4444; /* Red */
            font-size: 0.7rem;
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

/* --- PEMBETULAN MOBILE VIEW (991px ke bawah) --- */
@media (max-width: 991px) {
    /* 1. Paksa sembunyi sidebar dan overlay sepenuhnya */
    .sidebar, 
    .sidebar-overlay,
    #sidebar-backdrop {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
        width: 0 !important;
    }

    /* 2. Pastikan main-content memenuhi 100% skrin tanpa margin kiri */
    .main-content {
        margin-left: 0 !important;
        padding-left: 15px !important;
        padding-right: 15px !important;
        width: 100% !important;
        min-width: 100% !important;
        padding-bottom: 100px !important; /* Ruang supaya tidak kena tutup dengan nav bawah */
    }

    /* 3. Pastikan topbar berada di paling atas tanpa anjakan */
    .topbar {
        left: 0 !important;
        width: 100% !important;
        padding: 10px 15px !important;
    }

    /* 4. Sembunyikan butang toggle sidebar (ikon 3 garis) jika anda guna bottom nav */
    #sidebarToggle {
        display: none !important;
    }

    .mobile-bottom-nav {
        display: flex !important;
    }
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
            <a href="dashboard_tech.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
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
            <h3 class="mb-0">Settings</h3>
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

    <div class="container-fluid py-4 py-lg-5">
        <div class="row justify-content-center">
            <div class="col-lg-11">
                <div class="row g-4">
                    
                    <div class="col-lg-4">
                        <div class="card profile-header-card border-0 shadow-sm p-4 text-center h-100">
                            <div class="avatar-main">
                                <?= htmlspecialchars(strtoupper(substr($tech['name'] ?? 'U', 0, 1))) ?>
                            </div>
                            <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($tech['name'] ?? '') ?></h4>
                            <p class="text-muted small mb-3"> IT Staff</p>
                            <hr class="w-25 mx-auto">
                            <div class="mt-3 text-start small text-muted px-2">
                                <div class="mb-2"><i class="fa-solid fa-id-badge me-2 text-primary"></i> <strong>Staff ID:</strong> <?= htmlspecialchars($tech['staff_id'] ?? '') ?></div>
                                <div><i class="fa-solid fa-calendar-check me-2 text-primary"></i> <strong>Role:</strong> Technician</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm p-4 p-md-5 rounded-4 h-100">
                            
                            <?php if (isset($_SESSION['message'])): ?>
                                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                                    <i class="fa-solid fa-check-circle me-2"></i><?= $_SESSION['message']; unset($_SESSION['message']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <div id="viewMode" style="display: <?= $keep_edit_mode ? 'none' : 'block' ?>;">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5 class="fw-bold m-0">Contact Details</h5>
                                    <button id="editBtn" class="btn btn-upgrade btn-sm">Edit Profile</button>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="info-box">
                                            <label class="text-uppercase text-muted fw-bold small" style="letter-spacing: 0.5px; font-size: 0.7rem;">Email Address</label>
                                            <div class="fw-semibold text-dark mt-1"><?= htmlspecialchars($tech['email'] ?? '') ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-box">
                                            <label class="text-uppercase text-muted fw-bold small" style="letter-spacing: 0.5px; font-size: 0.7rem;">Phone Number</label>
                                            <div class="fw-semibold text-dark mt-1"><?= htmlspecialchars($tech['phoneNum'] ?? '') ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="editMode" style="display: <?= $keep_edit_mode ? 'block' : 'none' ?>;">
                                <h5 class="fw-bold mb-4">Update Information</h5>
                                <form action="update_profile_tech.php" method="POST">
                                    <input type="hidden" name="person_id" value="<?= $person_id ?>">
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-muted">FULL NAME</label>
                                            <input type="text" class="form-control form-control-lg fs-6" name="name" value="<?= htmlspecialchars($tech['name'] ?? '') ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-muted">PHONE NUMBER</label>
                                            <input type="text" class="form-control form-control-lg fs-6" name="phoneNum" value="<?= htmlspecialchars($tech['phoneNum'] ?? '') ?>" required>
                                        </div>
                                    </div>

                                    <div class="mt-4 p-4 rounded-4" style="background: #f1f5f9; border: 1px dashed #cbd5e1;">
                                        <h6 class="fw-bold mb-3"><i class="fa-solid fa-lock me-2"></i>Change Password</h6>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <input type="password" class="form-control" id="new_password" name="new_password" placeholder="New Password">
                                            </div>
                                            <div class="col-md-6">
                                                <input type="password" class="form-control" name="confirm_password" placeholder="Confirm New Password">
                                            </div>
                                        </div>

                                        <div class="mt-3 bg-white p-3 rounded-3">
                                            <div class="row g-2">
                                                <div class="col-sm-6">
                                                    <div class="pw-requirement" id="req-length"><i class="fa-solid fa-circle"></i> Min 8 characters</div>
                                                    <div class="pw-requirement" id="req-upper"><i class="fa-solid fa-circle"></i> One uppercase (A-Z)</div>
                                                </div>
                                                <div class="col-sm-6">
                                                    <div class="pw-requirement" id="req-number"><i class="fa-solid fa-circle"></i> One number (0-9)</div>
                                                    <div class="pw-requirement" id="req-special"><i class="fa-solid fa-circle"></i> One special character</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex gap-2 mt-4">
                                        <button type="submit" class="btn btn-upgrade px-4">Save Changes</button>
                                        <button type="button" id="cancelBtn" class="btn btn-light px-4 text-muted">Cancel</button>
                                    </div>
                                </form>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle View/Edit Mode
    const viewMode = document.getElementById('viewMode');
    const editMode = document.getElementById('editMode');
    const editBtn = document.getElementById('editBtn');
    const cancelBtn = document.getElementById('cancelBtn');

    editBtn?.addEventListener('click', () => {
        viewMode.style.display = 'none';
        editMode.style.display = 'block';
    });

    cancelBtn?.addEventListener('click', () => {
        editMode.style.display = 'none';
        viewMode.style.display = 'block';
    });

    // Real-time Password Validator
    const pwdInput = document.getElementById('new_password');
    const reqs = {
        length: document.getElementById('req-length'),
        upper: document.getElementById('req-upper'),
        number: document.getElementById('req-number'),
        special: document.getElementById('req-special')
    };

    pwdInput?.addEventListener('input', function() {
        const val = this.value;
        const checks = {
            length: val.length >= 8,
            upper: /[A-Z]/.test(val),
            number: /[0-9]/.test(val),
            special: /[!@#$%^&*(),.?":{}|<>]/.test(val)
        };

        Object.keys(checks).forEach(k => {
            if (checks[k]) {
                reqs[k].classList.add('requirement-met');
                reqs[k].querySelector('i').className = 'fa-solid fa-check-circle';
            } else {
                reqs[k].classList.remove('requirement-met');
                reqs[k].querySelector('i').className = 'fa-solid fa-circle';
            }
        });
    });

    // Sidebar Toggle for Mobile
    const sidebar = document.getElementById('offcanvasSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const backdrop = document.getElementById('sidebar-backdrop');

    toggleBtn?.addEventListener('click', () => {
        sidebar.style.transform = 'translateX(0)';
        backdrop.style.display = 'block';
        setTimeout(() => backdrop.classList.add('show'), 10);
    });

    backdrop?.addEventListener('click', () => {
        sidebar.style.transform = 'translateX(-280px)';
        backdrop.classList.remove('show');
        setTimeout(() => backdrop.style.display = 'none', 300);
    });
</script>


<nav class="mobile-bottom-nav">
    <a href="dashboard_tech.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'dashboard_tech.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-table-columns"></i>
        <span>Dashboard</span>
    </a>
    <a href="check_out.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'check_out.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-dolly"></i>
        <span>Manage Request</span>
    </a>
    <a href="manageItem_tech.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'manageItem_tech.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-box-archive"></i>
        <span>Manage Items</span>
    </a>
    <a href="report.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'report.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-chart-line"></i>
        <span>Report</span>
    </a>
    <a href="profile_tech.php" class="active">
        <i class="fa-solid fa-user"></i>
        <span>Profile</span>
    </a>
</nav></body>
</html>


