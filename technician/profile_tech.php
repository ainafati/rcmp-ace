<?php

session_start();

include '../config.php'; 


if (!isset($_SESSION['tech_id'])) {
    header("Location: ../login.php");
    exit();
}

$tech_id = (int) $_SESSION['tech_id'];


$stmt = $conn->prepare("SELECT name, email, phoneNum FROM technician WHERE tech_id = ?");
if ($stmt === false) {
    die("SQL Error: " . htmlspecialchars($conn->error));
}
$stmt->bind_param("i", $tech_id);
$stmt->execute();
$result = $stmt->get_result();
$tech = $result->fetch_assoc();
$stmt->close();

if (!$tech) {
    session_destroy();
    header("Location: ../login.php"); 
    exit();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Technician</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* 1. FONT & BODY BACKGROUND */
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background-color: #f8fafc; color: #334155; min-height: 100vh; }

        /* 2. SIDEBAR (Desktop Default: Fixed di Kiri) */
        .sidebar { 
            width: 250px; 
            position: fixed; 
            top: 0; 
            bottom: 0; 
            left: 0; 
            background: #ffffff; 
            padding: 20px; 
            border-right: 1px solid #e5e7eb; 
            z-index: 1050; /* Ditingkatkan untuk memastikan ia di atas backdrop */
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
            transition: transform 0.3s ease-in-out; 
        }
        .sidebar-header { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
        .logo-icon { width: 40px; height: 40px; background-color: #3b82f6; color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .logo-text strong { display: block; font-size: 16px; color: #1e293b; }
        .logo-text span { font-size: 12px; color: #94a3b8; }
        .sidebar a { display: flex; align-items: center; gap: 12px; color: #64748b; text-decoration: none; padding: 12px 15px; margin-bottom: 8px; border-radius: 8px; font-weight: 500; font-size: 15px; transition: all 0.2s ease-in-out; }
        .sidebar a.active, .sidebar a:hover { background: #3b82f6; color: #fff; }
        .sidebar a.logout-link { color: #ef4444; font-weight: 600; margin-top: auto; }
        .sidebar a.logout-link:hover { color: #fff; background: #ef4444; }
        
        /* 3. MAIN LAYOUT & TOPBAR */
        .main-content { margin-left: 250px; transition: margin-left 0.3s ease-in-out; }
        .topbar { 
            background: #ffffff; 
            padding: 15px 30px; 
            display: flex; 
            justify-content: flex-start; 
            align-items: center; 
            border-bottom: 1px solid #e5e7eb; 
            /* FIX: Tambah sticky/fixed untuk Z-index berfungsi */
            position: sticky; 
            top: 0;
            z-index: 990; /* Diletakkan di bawah sidebar */
        }
        .topbar h3 { font-weight: 600; margin: 0; color: #1e293b; font-size: 22px; }
        .container-fluid { padding: 30px; }

        /* 4. CARD & PROFILE STYLING (tidak berubah) */
        .card { border-radius: 16px; padding: 25px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); background: #fff; margin-bottom: 25px; border: 1px solid #e2e8f0; }
        .card h5 { font-weight: 600; color: #1e293b; }
        .profile-header-card { text-align: center; }
        .avatar { width: 100px; height: 100px; border-radius: 50%; background: #3b82f6; color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 48px; font-weight: 600; }
        .btn { border-radius: 8px; padding: 10px 20px; font-weight: 500; }
        .btn-primary { background-color: #3b82f6; border: none; }
        .btn-primary:hover { background-color: #2563eb; }

        /* 5. MOBILE OPTIMIZATIONS (Off-Canvas Logic) */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%); 
            }
            
            .offcanvas-open .sidebar {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0; 
                padding-top: 80px; /* FIX: Tambah padding agar tidak terlindung di bawah fixed topbar */
            }

            .topbar { 
                padding: 15px; 
                position: fixed; /* FIX: Jadikan fixed di mobile */
                width: 100%;
                left: 0;
            }
            .topbar .btn-sm { display: inline-block !important; } 
            .topbar h3 { font-size: 1.2rem; }
            .container-fluid { padding: 15px; }
        }
        
        /* Backdrop for Off-Canvas effect */
        .offcanvas-backdrop {
            position: fixed;
            top: 0; left: 0;
            z-index: 1040; /* FIX: Diletakkan di bawah Sidebar (1050) */
            width: 100vw; height: 100vh;
            background-color: #000;
            opacity: 0.5;
            transition: opacity 0.3s ease-in-out;
            display: none; 
        }
        .offcanvas-open .offcanvas-backdrop {
            display: block;
        }

    </style>
</head>
<body>

<div class="offcanvas-backdrop fade" id="sidebar-backdrop"></div>

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
    </div>

    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-4">
                <div class="card profile-header-card h-100">
                    <div class="avatar">
                        <?= htmlspecialchars(strtoupper(substr($tech['name'], 0, 1))) ?>
                    </div>
                    <h4 class="fw-bold"><?= htmlspecialchars($tech['name']) ?></h4>
                    <p class="text-muted mb-0">Technician (ID: <?= $tech_id ?>)</p>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card">
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

                    <div id="viewMode">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5><i class="fa-solid fa-id-card me-2 text-primary"></i> Personal Information</h5>
                            <button id="editBtn" class="btn btn-primary"><i class="fa-solid fa-pen me-2"></i> Edit Profile</button>
                        </div>
                        <hr>
                        <p><strong>Full Name:</strong> <?= htmlspecialchars($tech['name']) ?></p>
                        <p><strong>Email Address:</strong> <?= htmlspecialchars($tech['email']) ?></p>
                        <p class="mb-0"><strong>Phone Number:</strong> <?= htmlspecialchars($tech['phoneNum']) ?></p>
                    </div>

                    <div id="editMode" style="display: none;">
                        <h5><i class="fa-solid fa-pen-to-square me-2 text-primary"></i> Edit Your Information</h5>
                        <hr>
                        <form action="update_profile_tech.php" method="POST">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($tech['name']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($tech['email']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="phoneNum" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="phoneNum" name="phoneNum" value="<?= htmlspecialchars($tech['phoneNum']) ?>" required>
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
                            <button type="button" id="cancelBtn" class="btn btn-secondary"><i class="fa-solid fa-times me-2"></i> Cancel</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const viewMode = document.getElementById('viewMode');
    const editMode = document.getElementById('editMode');
    const editBtn = document.getElementById('editBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const sidebar = document.getElementById('offcanvasSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const backdrop = document.getElementById('sidebar-backdrop');
    const body = document.body;

    
    editBtn.addEventListener('click', () => {
        viewMode.style.display = 'none';
        editMode.style.display = 'block';
    });

    cancelBtn.addEventListener('click', () => {
        editMode.style.display = 'none';
        viewMode.style.display = 'block';
    });

    
    function toggleSidebar() {
        body.classList.toggle('offcanvas-open');
        
        if (body.classList.contains('offcanvas-open')) {
            backdrop.style.display = 'block';
        } else {
            
            setTimeout(() => {
                backdrop.style.display = 'none';
            }, 300); 
        }
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', toggleSidebar);
    }
    
    if (backdrop) {
        backdrop.addEventListener('click', toggleSidebar);
    }
</script>

</body>
</html>