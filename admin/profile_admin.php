<?php
session_start();
include '../config.php'; 

// 1. Pastikan admin sudah log masuk
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php"); 
    exit();
}

// 2. Guna nama pembolehubah yang betul ($admin_id)
$admin_id = (int) $_SESSION['admin_id'];

// 3. Ambil maklumat terkini dari jadual 'admin' dan simpan ke dalam $admin
$stmt = $conn->prepare("SELECT name, email, phoneNum FROM admin WHERE admin_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

if (!$admin) {
    // Jika admin tidak wujud, paksa log keluar
    session_destroy();
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1"> 
    <title>My Profile — Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* CSS Lengkap & Seragam Untuk Tema Anda */
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background-color: #f8fafc; color: #334155; min-height: 100vh; overflow-x: hidden; }
        
        /* DESKTOP STYLES */
        .sidebar { width: 250px; position: fixed; top: 0; bottom: 0; left: 0; background: #ffffff; padding: 20px; border-right: 1px solid #e5e7eb; z-index: 1000; display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.3s ease; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 1040; }
        .sidebar-overlay.active { display: block; }

        .sidebar-header { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
        .logo-icon { width: 40px; height: 40px; background-color: #3b82f6; color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .logo-text strong { display: block; font-size: 16px; color: #1e293b; }
        .logo-text span { font-size: 12px; color: #94a3b8; }
        .sidebar a { display: flex; align-items: center; gap: 12px; color: #64748b; text-decoration: none; padding: 12px 15px; margin-bottom: 8px; border-radius: 8px; font-weight: 500; font-size: 15px; transition: all 0.2s ease-in-out; }
        .sidebar a.active, .sidebar a:hover { background: #3b82f6; color: #fff; }
        .sidebar a.logout-link { color: #ef4444; font-weight: 600; margin-top: auto; }
        .sidebar a.logout-link:hover { color: #fff; background: #ef4444; }
        
        .main-content { margin-left: 250px; transition: margin-left 0.3s ease; }
        
        .topbar { background: #ffffff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; }
        .topbar h3 { font-weight: 600; margin: 0; color: #1e293b; font-size: 22px; }
        .container-fluid { padding: 30px; }
        .card { border-radius: 16px; padding: 25px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); background: #fff; margin-bottom: 25px; border: 1px solid #e2e8f0; }
        .card h5 { font-weight: 600; color: #1e293b; }
        .profile-header-card { text-align: center; }
        .avatar { width: 100px; height: 100px; border-radius: 50%; background: #3b82f6; color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 48px; font-weight: 600; }
        .btn { border-radius: 8px; padding: 10px 20px; font-weight: 500; }
        .btn-primary { background-color: #3b82f6; border: none; }
        .btn-primary:hover { background-color: #2563eb; }
        
        /* Tambah ini untuk mobile tables */
        .table-responsive-sm {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch; 
        }

        /* --- MOBILE VIEW (MAX-WIDTH 992px) --- */
        #sidebar-toggle-btn {
            display: none; 
            background: none;
            border: none;
            color: #334155;
            font-size: 20px;
            padding: 0;
            margin-right: 15px;
        }

        @media (max-width: 992px) {
            /* General Layout & Sidebar */
            #sidebar-toggle-btn { display: block; }
            .sidebar { 
                transform: translateX(-100%); 
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.15); 
                z-index: 1050; 
            }
            .sidebar.open { 
                transform: translateX(0); 
            }
            .main-content { 
                margin-left: 0; 
                width: 100%; 
            }

            /* Topbar - Diperkemas */
            .topbar { 
                padding: 10px 15px; 
                justify-content: space-between; /* Tukar balik ke space-between */
            } 
            .topbar h3 { 
                font-size: 18px; 
                flex-grow: 1; /* Biar tajuk ambil ruang di tengah */
                text-align: center;
                margin-left: -35px; /* Adjust untuk center, menampung ikon toggle */
            }
            .topbar .d-flex {
                /* Untuk ikon profil di kanan */
                margin-left: auto;
            }
            
            /* Content Area */
            .container-fluid { 
                padding: 15px; 
            }
            .card {
                padding: 20px;
            }

            /* Profile Card Stacks Vertically */
            .col-lg-4, .col-lg-8 {
                width: 100%; 
            }
            .profile-header-card {
                margin-bottom: 15px;
            }

            /* Buttons */
            #viewMode .d-flex {
                flex-direction: column;
                align-items: flex-start !important;
            }
            #viewMode .d-flex button {
                width: 100%;
                margin-top: 15px;
            }
            #editMode .btn {
                width: 100%;
                margin-top: 10px;
            }
            #editMode .btn-secondary {
                /* Tukar margin-top asal dari 10px ke 0 untuk butang 'Cancel' */
                margin-top: 0; 
            }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebar-overlay"></div>

<div class="sidebar" id="admin-sidebar">
    <div>
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fa-solid fa-user-shield"></i></div>
            <div class="logo-text"><strong>UniKL Admin</strong><span>System Control</span></div>
        </div>
        <a href="manageItem_admin.php"><i class="fa-solid fa-box-archive"></i> Manage Items</a>
        <a href="manage_accounts.php"><i class="fa-solid fa-users-cog"></i> Manage Accounts</a>
        <a href="report_admin.php"><i class="fa-solid fa-chart-pie"></i> System Report</a>
    </div>
    <a href="logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>

<div class="main-content">
    <div class="topbar">
        <button id="sidebar-toggle-btn" class="me-3"><i class="fa fa-bars"></i></button>
        <h3>My Profile</h3>
        <div class="d-flex align-items-center">
            <a href="profile_admin.php" title="My Profile" style="color: inherit; text-decoration: none;">
                <i class="fa-solid fa-user-circle fa-2x text-secondary"></i>
            </a>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-4">
                <div class="card profile-header-card h-100">
                    <div class="avatar">
                        <?= htmlspecialchars(strtoupper(substr($admin['name'], 0, 1))) ?>
                    </div>
                    <h4 class="fw-bold"><?= htmlspecialchars($admin['name']) ?></h4>
                    <p class="text-muted mb-0"><?= htmlspecialchars($admin['email']) ?></p>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card">
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-success"><?= $_SESSION['message']; unset($_SESSION['message']); ?></div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                    <?php endif; ?>

                    <div id="viewMode">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5><i class="fa-solid fa-id-card me-2 text-primary"></i> Personal Information</h5>
                            <button id="editBtn" class="btn btn-primary"><i class="fa-solid fa-pen me-2"></i> Edit Profile</button>
                        </div>
                        <hr>
                        <p><strong>Full Name:</strong> <?= htmlspecialchars($admin['name']) ?></p>
                        <p><strong>Email Address:</strong> <?= htmlspecialchars($admin['email']) ?></p>
                        <p class="mb-0"><strong>Phone Number:</strong> <?= htmlspecialchars($admin['phoneNum']) ?></p>
                    </div>

                    <div id="editMode" style="display: none;">
                        <h5><i class="fa-solid fa-pen-to-square me-2 text-primary"></i> Edit Your Information</h5>
                        <hr>
                        <form action="update_profile_admin.php" method="POST">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($admin['name']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($admin['email']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="phoneNum" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" name="phoneNum" value="<?= htmlspecialchars($admin['phoneNum']) ?>" required>
                            </div>
                            <hr>
                            <p class="text-muted">Update your password (optional)</p>
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
                            <button type="button" id="cancelBtn" class="btn btn-secondary">Cancel</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // --- JS UNTUK TOGGLE SIDEBAR (MOBILE ONLY) ---
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('admin-sidebar');
        const toggleBtn = document.getElementById('sidebar-toggle-btn');
        const overlay = document.getElementById('sidebar-overlay');
        
        if (toggleBtn) {
            function toggleSidebar() {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('active');
            }

            toggleBtn.addEventListener('click', toggleSidebar);
            overlay.addEventListener('click', toggleSidebar);
            
            const sidebarLinks = sidebar.querySelectorAll('a');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 992) {
                        setTimeout(() => { 
                            sidebar.classList.remove('open');
                            overlay.classList.remove('active');
                        }, 100);
                    }
                });
            });
        }
    });

    // --- JS UNTUK TOGGLE VIEW/EDIT MODE ---
    const viewMode = document.getElementById('viewMode');
    const editMode = document.getElementById('editMode');
    const editBtn = document.getElementById('editBtn');
    const cancelBtn = document.getElementById('cancelBtn');

    editBtn.addEventListener('click', () => {
        viewMode.style.display = 'none';
        editMode.style.display = 'block';
    });

    cancelBtn.addEventListener('click', () => {
        editMode.style.display = 'none';
        viewMode.style.display = 'block';
    });
</script>

</body>
</html>