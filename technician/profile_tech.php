<?php
session_start();

include '../config.php';


if (!isset($_SESSION['person_id'])) {
    header("Location: ../login.php");
    exit();
}

$person_id = (int)$_SESSION['person_id'];
$tech_id = $person_id; 

if (!isset($conn) || $conn->connect_error) {
    die("Database Connection Error.");
}


$stmt = $conn->prepare("SELECT id AS staff_id, name, email, phoneNum FROM person WHERE person_id = ?");
if ($stmt === false) {
    die("SQL Error: " . htmlspecialchars($conn->error));
}

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
    <title>My Profile — Technician</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
<style>
    :root {
        --primary-color: #06b6d4; /* Cyan 600 */
        --primary-light: #f0f9ff;
        --primary-hover: #0891b2;
        --bg-light-gray: #f4f7f9;
        --card-bg: #ffffff;
        --text-dark: #1e293b;
        --text-muted: #64748b;
        --shadow-light: 0 4px 12px rgba(0, 0, 0, 0.05);
        --danger-color: #ef4444;
        --secondary-color: #f59e0b; /* Amber */
        --tertiary-color: #10b981; /* Emerald */
    }
    
    body {
        font-family: 'Inter', sans-serif;
        background-color: var(--bg-light-gray);
        color: var(--text-dark);
        min-height: 100vh;
    }
    
    /* --- Sidebar Styles (Technician Specific) --- */
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
    .sidebar a:hover:not(.active) { background: #eef1f4; color: var(--text-dark); }
    .sidebar a.logout-link { color: var(--danger-color); font-weight: 600; margin-top: 20px; }
    .sidebar a i { width: 20px; text-align: center; }

    /* --- Content & Topbar Styles --- */
    .main-content { margin-left: 280px; }
    .topbar { background: var(--card-bg); padding: 18px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eef1f4; z-index: 999; position: sticky; top: 0; }
    .topbar h3 { font-weight: 700; margin: 0; color: var(--text-dark); font-size: 24px; }
    .container-fluid { padding: 30px; }
        
    .card {
        border-radius: 12px; box-shadow: var(--shadow-light);
        background: var(--card-bg); margin-bottom: 25px;
        border: none; padding: 25px;
    }
    .card h5 { font-weight: 700; color: var(--text-dark); }
    
    /* --- PROFILE CARD DESIGN --- */
    .profile-header-card {
        text-align: center;
        padding: 40px 25px;
        background: linear-gradient(135deg, var(--primary-color) 0%, #4dd0e1 100%);
        color: white;
    }
    .profile-header-card h4 { color: white; margin-top: 15px; font-weight: 700; }
    .profile-header-card p { color: rgba(255, 255, 255, 0.8); margin-bottom: 0; }

    .avatar {
        width: 120px; height: 120px; border-radius: 50%;
        background: white;
        color: var(--primary-color);
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 20px auto;
        font-size: 55px; font-weight: 600;
        border: 5px solid white;
        box-shadow: 0 0 0 5px rgba(255, 255, 255, 0.3);
    }
    .basic-info strong { font-size: 1.5rem; font-weight: 700; display: block; margin-bottom: 5px; color: white; }
    .basic-info span { color: rgba(255, 255, 255, 0.9); font-size: 0.95rem; }


    /* Reka Bentuk Maklumat Dalam View Mode (Cards Pinned) */
    .info-card-container { margin-top: 20px; display: flex; flex-direction: column; gap: 15px; }
    .info-card {
        padding: 18px 25px;
        background-color: var(--bg-light-gray);
        border-radius: 10px;
        display: flex;
        align-items: center;
        gap: 20px;
        border: 1px solid #eef1f4;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .info-card:hover {
        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
        transform: translateY(-2px);
    }
    /* Icon khusus untuk ID */
    .info-icon-wrapper.id-wrapper {
        background-color: var(--text-muted); /* Kelabu */
    }
    .info-icon-wrapper {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }
    .info-details strong {
        display: block;
        font-size: 13px;
        color: var(--text-muted);
        text-transform: uppercase;
        margin-bottom: 2px;
    }
    .info-details span {
        font-weight: 700;
        color: var(--text-dark);
        font-size: 17px;
    }

    /* --- CSS BARU UNTUK CENTER DAN BOLD NAMA --- */
    .info-details.text-center-name {
        flex-grow: 1; 
        text-align: center;
    }
    .info-details.text-center-name span {
        font-weight: 900; 
        font-size: 1.25rem;
    }
    .info-details.text-center-name strong {
        font-size: 0.8rem;
        color: var(--text-muted);
    }
    /* --- AKHIR CSS BARU --- */

    /* === CSS TAMBAHAN UNTUK TOPBAR NAMA === */
    .topbar .user-profile {
        display: flex; 
        align-items: center; 
        gap: 10px;
    }
    .topbar .user-name {
        font-weight: 700; /* Untuk Bold */
        color: var(--text-dark); 
        /* Penjajaran kekal kanan kerana ia di topbar */
        font-size: 1rem;
    }
    /* === AKHIR CSS TAMBAHAN === */


    /* Form Styles */
    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(6, 182, 212, 0.25);
    }
    /* Untuk input yang Readonly agar kelihatan berbeza */
    .form-control[readonly] {
        background-color: #e9ecef; /* Slightly darker background */
        opacity: 0.8;
        cursor: not-allowed;
    }
    .btn { border-radius: 8px; padding: 10px 20px; font-weight: 600; }
    .btn-primary {
        background-color: var(--primary-color);
        border: none;
        transition: background-color 0.2s;
    }
    .btn-primary:hover { background-color: var(--primary-hover); }
    
    /* CSS tambahan untuk list requirement */
    .alert-info ul {
        list-style: none;
        padding-left: 0;
        margin-top: 5px;
    }
    .alert-info li {
        margin-bottom: 3px;
    }

    /* Mobile Optimizations */
    @media (max-width: 992px) {
        .sidebar { transform: translateX(-280px); left: 0; width: 280px; }
        .main-content { margin-left: 0; width: 100%; }
        .topbar { padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .topbar h3 { font-size: 20px; }
        .container-fluid { padding: 15px; }
        .info-card { padding: 15px; gap: 15px; }
    }
</style>
</head>
<body class="profile-page">

<div class="offcanvas-backdrop fade" id="sidebar-backdrop" style="display: none; z-index: 1040;"></div>

<div class="sidebar" id="offcanvasSidebar">
    <div>
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fa-solid fa-wrench"></i></div>
            <div class="logo-text"><strong>UniKL Technician</strong><span>Dashboard</span></div>
        </div>
        <a href="dashboard_tech.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        <a href="check_out.php"><i class="fa-solid fa-dolly"></i> Manage Requests</a>
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
        <h3>My Profile</h3>
            <div class="user-profile">
            <span class="user-name d-none d-md-inline fw-bold"><?= htmlspecialchars($tech['name'] ?? '') ?></span>
            <a href="#" title="My Profile" style="color: inherit; text-decoration: none;">
                <i class="fa-solid fa-circle-user fa-2x text-secondary"></i>
            </a>
            </div>
    </div>
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="row">
                    <div class="col-lg-4">
                        <div class="card profile-header-card h-100">
                            <div class="avatar">
                                <?= htmlspecialchars(strtoupper(substr($tech['name'] ?? 'U', 0, 1))) ?>
                            </div>
                            <h4 class="fw-bold"><?= htmlspecialchars($tech['name'] ?? '') ?></h4>
                            <p class="mb-0">Technician (ID: <?= htmlspecialchars($tech['staff_id'] ?? '') ?>)</p> 
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="card h-100">
                            
                            <?php if (isset($_SESSION['message'])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fa-solid fa-check-circle me-2"></i><?= $_SESSION['message']; unset($_SESSION['message']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fa-solid fa-exclamation-triangle me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            <div id="viewMode" style="display: <?= $keep_edit_mode ? 'none' : 'block' ?>;">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5 class="mb-0"><i class="fa-solid fa-id-card me-2 text-primary"></i> Contact Information</h5>
                                    <button id="editBtn" class="btn btn-primary"><i class="fa-solid fa-pen me-2"></i> Edit Profile</button>
                                </div>
                                
                                <div class="info-card-container">
                                    <div class="info-card">
                                        <div class="info-icon-wrapper id-wrapper"><i class="fa-solid fa-hashtag"></i></div>
                                        <div class="info-details">
                                            <strong>Technician ID (Staff ID)</strong>
                                            <span><?= htmlspecialchars($tech['staff_id'] ?? '') ?></span>
                                        </div>
                                    </div>

                                    <div class="info-card">
                                        <div class="info-icon-wrapper" style="background-color: var(--primary-color);"><i class="fa-solid fa-user"></i></div>
                                        <div class="info-details">
                                            <strong>Full Name</strong>
                                            <span><?= htmlspecialchars($tech['name'] ?? '') ?></span>
                                        </div>
                                    </div>
                                    <div class="info-card">
                                        <div class="info-icon-wrapper" style="background-color: var(--secondary-color);"><i class="fa-solid fa-envelope"></i></div>
                                        <div class="info-details">
                                            <strong>Email Address</strong>
                                            <span><?= htmlspecialchars($tech['email'] ?? '') ?></span>
                                        </div>
                                    </div>

                                    <div class="info-card">
                                        <div class="info-icon-wrapper" style="background-color: var(--tertiary-color);"><i class="fa-solid fa-phone"></i></div>
                                        <div class="info-details">
                                            <strong>Phone Number</strong>
                                            <span><?= htmlspecialchars($tech['phoneNum'] ?? '') ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="editMode" style="display: <?= $keep_edit_mode ? 'block' : 'none' ?>;">
                                <h5><i class="fa-solid fa-pen-to-square me-2 text-primary"></i> Update Your Details</h5>
                                <hr>
                                <form action="update_profile_tech.php" method="POST">
                                    <input type="hidden" name="person_id" value="<?= $tech_id ?>"> 
                                    
                                    <div class="mb-3">
                                        <label for="staff_id" class="form-label">Technician ID (Staff ID)</label>
                                        <input type="text" class="form-control" id="staff_id" name="staff_id" value="<?= htmlspecialchars($tech['staff_id'] ?? '') ?>" readonly>
                                        <small class="form-text text-muted"><i class="fa-solid fa-lock me-1"></i> Staff ID cannot be changed.</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($tech['name'] ?? '') ?>" required>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">Email Address</label>
                                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($tech['email'] ?? '') ?>" readonly>
                                            <small class="form-text text-muted"><i class="fa-solid fa-lock me-1"></i> Email cannot be changed.</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="phoneNum" class="form-label">Phone Number</label>
                                            <input type="text" class="form-control" name="phoneNum" value="<?= htmlspecialchars($tech['phoneNum'] ?? '') ?>" required>
                                        </div>
                                    </div>
                                    
                                    <hr class="mt-4">
                                    <p class="text-muted fw-bold">Change Password (optional)</p>
                                    
                                    <div class="alert alert-info py-2" role="alert">
                                        <h6 class="alert-heading fw-bold mb-1"><i class="fa-solid fa-key me-2"></i>Password Requirements:</h6>
                                        <ul class="list-unstyled mb-0" style="margin-left: -5px; font-size: 0.95rem;">
                                            <li><i class="fa-solid fa-check me-2 text-success"></i> Must be at least <strong>8 characters</strong> long.</li>
                                            <li><i class="fa-solid fa-check me-2 text-success"></i> Must include <strong>number</strong> (0-9).</li>
                                            <li><i class="fa-solid fa-check me-2 text-success"></i> Must include <strong>uppercase letter</strong> (A-Z).</li>
                                            <li><i class="fa-solid fa-check me-2 text-success"></i> Must include <strong>lowercase letter</strong> (a-z).</li>
                                            <li><i class="fa-solid fa-check me-2 text-success"></i> Must include <strong>special character</strong> (!@#$%..).</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="new_password" class="form-label">New Password</label>
                                            <input type="password" class="form-control" name="new_password" placeholder="Leave blank to keep current">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                                            <input type="password" class="form-control" name="confirm_password">
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-2"></i> Save Changes</button>
                                    <button type="button" id="cancelBtn" class="btn btn-secondary"><i class="fa-solid fa-xmark me-2"></i> Cancel</button>
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
    
    const shouldKeepEditMode = <?= json_encode($keep_edit_mode) ?>;

    const viewMode = document.getElementById('viewMode');
    const editMode = document.getElementById('editMode');
    const editBtn = document.getElementById('editBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const sidebar = document.getElementById('offcanvasSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const backdrop = document.getElementById('sidebar-backdrop');
    
    
    function switchToEditMode() {
        viewMode.style.display = 'none';
        editMode.style.display = 'block';
    }

    function switchToViewMode() {
        
        document.querySelector('input[name="new_password"]').value = '';
        document.querySelector('input[name="confirm_password"]').value = '';
        
        editMode.style.display = 'none';
        viewMode.style.display = 'block';
    }

    editBtn.addEventListener('click', switchToEditMode);
    cancelBtn.addEventListener('click', switchToViewMode);


    
    function toggleSidebar() {
        if (sidebar.style.transform === 'translateX(0px)' || window.getComputedStyle(sidebar).transform === 'matrix(1, 0, 0, 1, 0, 0)') {
            sidebar.style.transform = 'translateX(-280px)';
            backdrop.classList.remove('show');
            backdrop.style.display = 'none';
        } else {
            sidebar.style.transform = 'translateX(0px)';
            backdrop.style.display = 'block';
            backdrop.classList.add('show');
        }
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', toggleSidebar);
    }
    
    if (backdrop) {
        backdrop.addEventListener('click', toggleSidebar);
    }

    window.addEventListener('load', () => {
        
        if (window.innerWidth >= 992) {
            sidebar.style.transform = 'translateX(0px)';
        } else {
            sidebar.style.transform = 'translateX(-280px)';
        }

        
        
        if (shouldKeepEditMode) {
            
            viewMode.style.display = 'none';
            editMode.style.display = 'block';
        }
        
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 992) {
            sidebar.style.transform = 'translateX(0px)';
            backdrop.style.display = 'none';
        } else if (window.getComputedStyle(sidebar).transform !== 'translateX(0px)') {
            sidebar.style.transform = 'translateX(-280px)';
            backdrop.style.display = 'none';
        }
    });
</script>

</body>
</html>