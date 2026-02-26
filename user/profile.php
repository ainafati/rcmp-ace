<?php
session_start();
include '../config.php';

if (!$conn || !isset($_SESSION['person_id'])) {
    header("Location: ../login.php");
    exit();
}

$person_id = (int) $_SESSION['person_id'];
$stmt = $conn->prepare("SELECT id, name, email, phoneNum FROM person WHERE person_id = ?");
$stmt->bind_param("i", $person_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// Logic Nama Pendek
$fullName = $user['name'] ?? 'Guest User';
$displayName = trim(preg_split('/ (bin|binti) /i', $fullName)[0]);
$user['person_id'] = $person_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — UniKL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            --primary-cyan: #06b6d4;
            --bg-light: #f8fafc;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        body {
            background-color: var(--bg-light);
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
        }

        /* Card Container */
        .profile-container {
            padding-top: 20px;
            padding-bottom: 100px; /* Space for mobile nav */
        }

        .card {
            border: none;
            border-radius: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.04);
            background: #ffffff;
            overflow: hidden;
            padding: 30px;
        }

        /* Profile Header Section */
        .profile-header-alt {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 25px;
            border-bottom: 1px solid #f1f5f9;
        }

        .profile-header-alt img {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            object-fit: cover;
            border: 4px solid #f0fdfa;
        }

        /* Info Grid Layout */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
        }

        .info-item {
            display: flex;
            align-items: center;
            padding: 18px;
            background: #fdfdfd;
            border: 1px solid #f1f5f9;
            border-radius: 18px;
            transition: 0.3s;
        }

        .info-item:hover {
            border-color: var(--primary-cyan);
            background: #fff;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .icon-box {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .icon-id { background: #eff6ff; color: #3b82f6; }
        .icon-mail { background: #fef2f2; color: #ef4444; }
        .icon-phone { background: #f0fdf4; color: #22c55e; }
        .icon-user { background: #faf5ff; color: #a855f7; }

        .info-content strong {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .info-content span {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-main);
        }

        /* Form Styling */
        .form-label { font-weight: 600; font-size: 14px; color: var(--text-main); }
        .form-control {
            border-radius: 12px;
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .form-control:focus {
            background: #fff;
            border-color: var(--primary-cyan);
            box-shadow: 0 0 0 4px rgba(6, 182, 212, 0.1);
        }

        /* --- MOBILE BOTTOM NAV (THEMED DARK) --- */
.mobile-bottom-nav {
    display: flex;
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    /* TUKAR: Warna gelap ikut tema sidebar laptop */
    background: #1e293b !important; 
    border-top: 1px solid rgba(255, 255, 255, 0.1); 
    padding: 12px 0 25px; /* Kekalkan padding bawah untuk iPhone */
    z-index: 1000;
    box-shadow: 0 -8px 25px rgba(0,0,0,0.2);
    justify-content: space-around;
}

.mobile-bottom-nav a {
    flex: 1;
    text-align: center;
    text-decoration: none !important;
    /* Warna icon masa tak aktif (kelabu) */
    color: #94a3b8 !important; 
    font-size: 11px;
    font-weight: 600;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    transition: all 0.3s ease;
}

.mobile-bottom-nav a i { 
    font-size: 22px; /* Besarkan sikit biar puas hati */
}

/* Warna bila menu Profile ni aktif (Cyan) */
.mobile-bottom-nav a.active { 
    color: #06b6d4 !important; 
}

/* Effect bila user sentuh/click */
.mobile-bottom-nav a:active {
    transform: scale(0.9);
}

        @media (min-width: 992px) {
            .mobile-bottom-nav { display: none; }
        }
    </style>
</head>
<body>

<div id="overlay"></div>

<div class="sidebar" id="admin-sidebar">
    <div>
        <div class="sidebar-header">
    <div class="logo-icon">
        <i class="fa-solid fa-wrench"></i>
    </div>
    <div class="logo-text">
        <strong class="brand-name">UniKL User</strong><br>
        <span>Equipment System</span>
    </div>
</div>
<div class="sidebar-nav">
    <a href="dashboard_user.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'dashboard_user.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-house"></i> Dashboard
    </a>

  <a href="item_user.php" class="item-availability-link collapsed" id="itemAvailabilityToggle">
            <i class="fa-solid fa-calendar-plus"></i>Book Equipment
            <i class="fa-solid fa-angle-down ms-auto" style="font-size: 12px;"></i>
        </a>
		
		<div class="category-submenu ps-4" id="categorySubmenu" style="display: none;">
    <a href="#" class="category-filter-link py-2 d-block text-decoration-none" 
       data-category="" 
       style="color: #cbd5e1; font-size: 0.9rem;">
        <i class="fa-solid fa-layer-group me-2"></i> All Items
    </a>

    <?php foreach ($categories as $category): ?>
        <a href="#" class="category-filter-link py-2 d-block text-decoration-none" 
           data-category="<?= htmlspecialchars($category['category_name']) ?>" 
           style="color: #cbd5e1; font-size: 0.9rem;">
            <i class="fa-solid fa-tag me-2"></i> <?= htmlspecialchars($category['category_name']) ?>
        </a>
    <?php endforeach; ?>
</div>
    <a href="history.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'history.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-clock-rotate-left"></i> My Loan
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
        <nav aria-label="breadcrumb">
            <h4 class="fw-bold">My Profile</h4>
        </nav>
    </div>

    <div class="topbar-right">
    <?php 
        // Ambil nama file sekarang, contoh: 'profile.php'
        $current_page = basename($_SERVER['PHP_SELF']); 
    ?>
    
   <a href="profile.php" class="user-pill text-decoration-none d-flex align-items-center">
    <div class="text-end me-2 d-none d-md-block">
        <div class="user-name fw-bold" style="color: #1e293b; line-height: 1.2;">
            <?= htmlspecialchars($displayName) ?>
        </div>
        <div style="font-size: 0.7rem; color: #64748b;">
            Student / Staff
        </div>
    </div>

    <div class="profile-avatar">
        <img src="https://ui-avatars.com/api/?name=<?= urlencode($displayName ?? 'U') ?>&background=06b6d4&color=fff" 
             class="rounded-circle" 
             width="35">
    </div>
</a>
</div>
</div>

    <div class="container profile-container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-success border-0 rounded-4 mb-4">
                            <i class="fa-solid fa-check-circle me-2"></i><?= $_SESSION['message']; unset($_SESSION['message']); ?>
                        </div>
                    <?php endif; ?>

                    <div id="viewMode">
                        <div class="profile-header-alt">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($displayName) ?>&background=06b6d4&color=fff&bold=true" alt="Avatar">
                            <div>
                                <h4 class="fw-bold mb-1" style="text-transform: capitalize;"><?= htmlspecialchars($displayName) ?></h4>
                                <p class="text-muted mb-0 small"><i class="fa-solid fa-circle-check text-success me-1"></i> Verified UniKL Member</p>
                            </div>
                            <button id="editBtn" class="btn btn-primary ms-auto rounded-pill px-4">
                                <i class="fa-solid fa-user-pen me-2"></i> Edit
                            </button>
                        </div>

                        <div class="info-grid">
                            <div class="info-item">
                                <div class="icon-box icon-id"><i class="fa-solid fa-fingerprint"></i></div>
                                <div class="info-content">
                                    <strong>Identification ID</strong>
                                    <span><?= htmlspecialchars($user['id']) ?></span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="icon-box icon-mail"><i class="fa-solid fa-envelope"></i></div>
                                <div class="info-content">
                                    <strong>Email Address</strong>
                                    <span><?= htmlspecialchars($user['email']) ?></span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="icon-box icon-phone"><i class="fa-solid fa-phone"></i></div>
                                <div class="info-content">
                                    <strong>Phone Number</strong>
                                    <span><?= htmlspecialchars($user['phoneNum']) ?></span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="icon-box icon-user"><i class="fa-solid fa-id-badge"></i></div>
                                <div class="info-content">
                                    <strong>Full Name</strong>
                                    <span><?= htmlspecialchars($user['name']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="editMode" style="display: none;">
                        <div class="d-flex align-items-center mb-4">
                            <h5 class="fw-bold mb-0">Update Profile Details</h5>
                        </div>
                        <form action="update_profile.php" method="POST">
                            <input type="hidden" name="person_id" value="<?= $person_id ?>">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">ID (Read-only)</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['id']) ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Full Name (Read-only)</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" name="phoneNum" class="form-control" value="<?= htmlspecialchars($user['phoneNum']) ?>" required>
                                </div>
                                <div class="col-12 mt-4">
    <p class="fw-bold small text-muted text-uppercase mb-2">Security Settings</p>
    
    <div class="alert alert-info border-0 shadow-sm rounded-4 mb-3" style="background-color: #f0f9ff;">
        <div class="d-flex">
            <i class="fa-solid fa-circle-info mt-1 me-2 text-info"></i>
            <div>
                <strong class="small d-block mb-1 text-info">Password Requirements:</strong>
                <ul class="mb-0 small ps-3 text-muted">
                    <li>At least <strong>8 characters</strong> long</li>
                    <li>At least one <strong>uppercase</strong> (A-Z) & <strong>lowercase</strong> (a-z)</li>
                    <li>At least one <strong>number</strong> (0-9) & <strong>special character</strong> (@$!%*?)</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label small">New Password</label>
            <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current">
        </div>
        <div class="col-md-6">
            <label class="form-label small">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" placeholder="Re-type new password">
        </div>
    </div>
</div>
                            </div>
                            <div class="mt-4 pt-3 border-top">
                                <button type="submit" class="btn btn-primary px-4 rounded-pill">Save Changes</button>
                                <button type="button" id="cancelBtn" class="btn btn-light px-4 rounded-pill ms-2">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<nav class="mobile-bottom-nav">
    <a href="dashboard_user.php"><i class="fa-solid fa-house"></i><span>Dashboard</span></a>
    <a href="item_user.php"><i class="fa-solid fa-calendar-plus"></i><span>Book Equipment</span></a>
    <a href="history.php"><i class="fa-solid fa-clock-rotate-left"></i><span>My Loan</span></a>
    <a href="profile.php" class="active"><i class="fa-solid fa-user"></i><span>Profile</span></a>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const viewMode = document.getElementById('viewMode');
    const editMode = document.getElementById('editMode');
    const editBtn = document.getElementById('editBtn');
    const cancelBtn = document.getElementById('cancelBtn');

    editBtn.onclick = () => { viewMode.style.display = 'none'; editMode.style.display = 'block'; };
    cancelBtn.onclick = () => { editMode.style.display = 'none'; viewMode.style.display = 'block'; };
});
</script>
</body>
</html>