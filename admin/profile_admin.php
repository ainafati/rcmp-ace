<?php
session_start();
include '../config.php';

// SEMAK LOG MASUK
if (!isset($_SESSION['person_id'])) {
    header("Location: ../login.php");
    exit();
}

$person_id = (int)$_SESSION['person_id'];

// AMBIL DATA PENGGUNA
$stmt = $conn->prepare("SELECT name, email, phoneNum, id FROM person WHERE person_id = ?");
if ($stmt === false) {
    die("SQL Error: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $person_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc(); 
$stmt->close();

if (!$admin) {
    session_destroy();
    header("Location: ../login.php"); 
    exit();
}

$current_page = 'profile_admin.php'; 


// LOGIK DISPLAY NAME
$fullName = $admin['name'] ?? 'Admin';
$lowerName = strtolower($fullName);
$shortName = $fullName;
$separators = [' binti ', ' bin ', ' a/l ', ' a/p '];
foreach ($separators as $sep) {
    $pos = strpos($lowerName, $sep);
    if ($pos !== false) {
        $shortName = substr($fullName, 0, $pos);
        break;
    }
}
$parts = explode(' ', trim($shortName));
$displayName = (count($parts) > 1) ? $parts[0] . ' ' . $parts[1] : $parts[0];
$displayName = htmlspecialchars($displayName);
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Premium Profile — UniKL Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    
    <style>
	
	/* Pastikan badge requirement nampak jelas */
.req-badge {
    font-size: 0.7rem !important;
    font-weight: 600 !important;
    padding: 8px 12px !important;
    border-radius: 10px !important;
    border: 1px solid #e2e8f0 !important; /* Tambah border supaya nampak atas background putih */
    display: inline-flex;
    align-items: center;
    transition: all 0.3s ease;
    background-color: #ffffff !important; /* Paksa warna putih */
}

/* Warna bila dah lepas syarat */
.req-badge.text-success {
    background-color: #f0fdf4 !important;
    border-color: #bbf7d0 !important;
}

.strength-meter {
    height: 8px;
    background: #e2e8f0;
    border-radius: 10px;
    margin: 15px 0;
    overflow: hidden;
}
        :root {
            --primary-gradient: linear-gradient(135deg, #0f172a 0%, #334155 100%);
            --accent-gradient: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%);
            --glass-bg: rgba(255, 255, 255, 0.95);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f8fafc;
        }

        .main-content {
            background: #f1f5f9 url('https://www.transparenttextures.com/patterns/cubes.png');
            min-height: 100vh;
            padding: 2rem;
            transition: all 0.3s;
        }

        .profile-container {
            max-width: 1000px;
            margin: 20px auto;
        }

        .inventory-card {
            border: none !important;
            border-radius: 30px !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08) !important;
            background: var(--glass-bg);
            overflow: hidden;
            position: relative;
        }

        .profile-header-banner {
            background: var(--primary-gradient);
            height: 160px;
            position: relative;
        }

        .profile-avatar-wrapper {
            position: absolute;
            bottom: -60px;
            left: 50px;
            background: white;
            padding: 8px;
            border-radius: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }

        .info-tile {
            padding: 25px;
            border-radius: 24px;
            background: #ffffff;
            border: 1px solid #f1f5f9;
            height: 100%;
            transition: all 0.3s ease;
        }

        .tile-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: #eff6ff;
            color: #3b82f6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-bottom: 15px;
        }

        .btn-update {
            background: var(--accent-gradient);
            color: white;
            border: none;
            padding: 12px 40px;
            border-radius: 14px;
            font-weight: 600;
        }

        /* CUSTOM RESPONSIVE SPACING */
        .profile-header-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 3rem;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .profile-header-info {
                flex-direction: column;
                align-items: flex-start;
                margin-top: 1.5rem; /* Tambah ruang lepas avatar */
            }
            .btn-update {
                width: 100%; /* Butang jadi lebar penuh kat mobile biar senang tekan */
                margin-top: 10px;
            }
            .main-content {
                padding: 1rem;
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

    </style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay"></div> 
<div class="sidebar" id="admin-sidebar">
    <div> <div class="sidebar-header">
    <div class="logo-icon"><i class="fa-solid fa-wrench"></i></div>
    <div class="logo-text">
        <strong>UniKL Admin</strong>
        <span class="d-block">System Control</span> </div>
</div>
        
        <div class="sidebar-nav"> 
<a href="manageItem_admin.php" ><i class="fa-solid fa-box-archive"></i> Manage Items</a>
        <a href="manage_accounts.php" ><i class="fa-solid fa-users-cog"></i> Manage Accounts</a>
        <a href="report_admin.php" ><i class="fa-solid fa-chart-pie"></i> System Report</a>        </div>
    </div>
    
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-link"><i class="fa-solid fa-sign-out-alt"></i> Logout</a> 
    </div>
</div>

<div class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <h3 class="mb-0 fw-bold">Account Settings</h3>
        </div>
         <div class="topbar-right">
            <a href="profile_admin.php" class="user-pill text-decoration-none shadow-sm">
                    <div class="text-end me-2 d-none d-md-block">
                        <div class="user-name" style="text-transform: capitalize; font-weight: 600; color: #1e293b; line-height: 1;">
                            <?= htmlspecialchars($displayName) ?>
                        </div>
                        <small class="text-muted" style="font-size: 0.75rem;">Administrator</small>
                    </div>
                    <div class="profile-avatar">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($displayName) ?>&background=06b6d4&color=fff" class="rounded-circle" width="35">
                    </div>
                </a>
        </div>
    </div>

    <div class="profile-container animate-fade">
        <div class="inventory-card">
            <div class="profile-header-banner">
                <div class="profile-avatar-wrapper">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($displayName) ?>&background=3b82f6&color=fff&size=110&bold=true" class="rounded-circle" width="110">
                </div>
            </div>

            <div class="p-4 p-md-5 mt-5 mt-md-4">
                <div id="viewMode">
                    <div class="profile-header-info">
                        <div>
                            <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill mb-2">Verified Admin</span>
                            <h2 class="fw-bold text-dark mb-0 text-capitalize"><?= htmlspecialchars($admin['name']) ?></h2>
                        </div>
                        <button id="editBtn" class="btn btn-update">
                            <i class="fa-solid fa-user-gear me-2"></i>Edit Profile
                        </button>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="info-tile">
                                <div class="tile-icon"><i class="fa-solid fa-id-badge"></i></div>
                                <small class="text-uppercase fw-bold text-muted">Employee ID</small>
                                <div class="fw-bold fs-5">#<?= htmlspecialchars($admin['id']) ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-tile">
                                <div class="tile-icon"><i class="fa-solid fa-envelope"></i></div>
                                <small class="text-uppercase fw-bold text-muted">Email</small>
                                <div class="fw-bold text-truncate"><?= htmlspecialchars($admin['email']) ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-tile">
                                <div class="tile-icon"><i class="fa-solid fa-phone"></i></div>
                                <small class="text-uppercase fw-bold text-muted">Contact</small>
                                <div class="fw-bold fs-5"><?= htmlspecialchars($admin['phoneNum']) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

               <div id="editMode" style="display: none;">
                    <h4 class="fw-bold mb-4 text-dark">Modify Information</h4>
                    <form action="update_profile_admin.php" method="POST" id="profileForm">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Full Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-regular fa-user"></i></span>
                                    <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($admin['name']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-phone"></i></span>
                                    <input type="text" class="form-control" name="phoneNum" value="<?= htmlspecialchars($admin['phoneNum']) ?>" required>
                                </div>
                            </div>

                            <div class="col-12 mt-4">
    <div class="p-4 rounded-4" style="background: #f8fafc; border: 1px dashed #cbd5e1;">
        <h6 class="fw-bold mb-3"><i class="fa-solid fa-shield-halved me-2"></i>Security Update</h6>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label small fw-bold">New Password</label>
                <input type="password" class="form-control shadow-sm" name="new_password" id="new_password" placeholder="Leave blank to keep current">
                
                <div class="strength-meter">
                    <div id="strength-bar" class="strength-meter-fill" style="width: 0%; height: 100%; transition: 0.3s;"></div>
                </div>
                
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <div id="req-length" class="req-badge text-muted">
                        <i class="fa-solid fa-circle-xmark me-2"></i>8+ Char
                    </div>
                    <div id="req-upper" class="req-badge text-muted">
                        <i class="fa-solid fa-circle-xmark me-2"></i>Upper
                    </div>
                    <div id="req-number" class="req-badge text-muted">
                        <i class="fa-solid fa-circle-xmark me-2"></i>Number
                    </div>
                    <div id="req-special" class="req-badge text-muted">
                        <i class="fa-solid fa-circle-xmark me-2"></i>Special
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <label class="form-label small fw-bold">Confirm Password</label>
                <input type="password" class="form-control shadow-sm" name="confirm_password" id="confirm_password" placeholder="••••••••">
                <div id="match-msg" class="small mt-2" style="min-height: 20px;"></div>
            </div>
        </div>
    </div>
</div>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-update px-5">Save Changes</button>
                            <button type="button" id="cancelBtn" class="btn btn-light px-4 rounded-4 fw-bold border">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<nav class="mobile-bottom-nav d-none">
    <a href="manageItem_admin.php"><i class="fa-solid fa-box-archive"></i>Items</a>
    <a href="manage_accounts.php"><i class="fa-solid fa-users-cog"></i>Accounts</a>
    <a href="report_admin.php"><i class="fa-solid fa-chart-pie"></i>Report</a>
    <a href="profile_admin.php" class="active"><i class="fa-solid fa-user"></i>Profile</a>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editBtn = document.getElementById('editBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const viewMode = document.getElementById('viewMode');
    const editMode = document.getElementById('editMode');

    editBtn.onclick = () => { viewMode.style.display = 'none'; editMode.style.display = 'block'; };
    cancelBtn.onclick = () => { editMode.style.display = 'none'; viewMode.style.display = 'block'; };

    const newPass = document.getElementById('new_password');
    const confirmPass = document.getElementById('confirm_password');

    newPass.addEventListener('input', function() {
        const val = this.value;
        const bar = document.getElementById('strength-bar');
        
        // Peraturan Password
        const rules = {
            'req-length': val.length >= 8,
            'req-upper': /[A-Z]/.test(val),
            'req-number': /[0-9]/.test(val), // SYARAT NOMBOR
            'req-special': /[!@#$%^&*(),.?":{}|<> ]/.test(val)
        };

        let passedCount = 0;
        for (const [id, passed] of Object.entries(rules)) {
            const el = document.getElementById(id);
            if(passed) {
                el.classList.replace('bg-light', 'bg-success-subtle');
                el.classList.replace('text-muted', 'text-success');
                el.querySelector('i').classList.replace('fa-circle-xmark', 'fa-circle-check');
                passedCount++;
            } else {
                el.classList.add('bg-light');
                el.classList.remove('bg-success-subtle', 'text-success');
                el.querySelector('i').classList.add('fa-circle-xmark');
                el.querySelector('i').classList.remove('fa-circle-check');
            }
        }

        // Strength Bar logic
        let strength = (passedCount / 4) * 100;
        bar.style.width = strength + "%";
        bar.style.backgroundColor = strength < 50 ? "#ef4444" : strength < 100 ? "#f59e0b" : "#10b981";
    });

    confirmPass.onkeyup = function() {
        const msg = document.getElementById('match-msg');
        if(this.value === "") msg.innerHTML = "";
        else if(this.value === newPass.value) msg.innerHTML = "<span class='text-success fw-bold'>✓ Passwords match</span>";
        else msg.innerHTML = "<span class='text-danger fw-bold'>✗ Passwords mismatch</span>";
    };
});
</script>
</body>
</html>