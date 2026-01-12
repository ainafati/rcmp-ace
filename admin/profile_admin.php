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
$stmt = $conn->prepare("SELECT name, email, phoneNum FROM person WHERE person_id = ?");
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>My Profile — UniKL Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #06b6d4;
            --primary-hover: #0891b2;
            --danger-color: #ef4444;
            --bg-light-gray: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #1e293b; 
            --text-muted: #64748b; 
            --border-color: #e5e7eb;
            --sidebar-width: 260px;
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light-gray); color: #334155; overflow-x: hidden; }

        /* SIDEBAR */
        .sidebar { 
            width: var(--sidebar-width); 
            position: fixed; 
            top: 0; bottom: 0; left: 0; 
            background: var(--card-bg); 
            padding: 25px 20px; 
            border-right: 1px solid var(--border-color); 
            z-index: 1100; /* Paling atas */
            display: flex; 
            flex-direction: column; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
        }

        .sidebar-header { display: flex; align-items: center; gap: 12px; margin-bottom: 35px; }
        .logo-icon { width: 40px; height: 40px; background: var(--primary-color); color: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        
        .sidebar a { 
            display: flex; align-items: center; gap: 12px; color: var(--text-muted); 
            text-decoration: none; padding: 12px 15px; margin-bottom: 5px; 
            border-radius: 10px; font-weight: 500; transition: 0.2s; 
        }
        
        .sidebar a.active, .sidebar a:hover { background: var(--primary-color); color: #fff; }
        .sidebar a.logout-link { color: var(--danger-color); margin-top: auto; }
        .sidebar a.logout-link:hover { background: var(--danger-color); color: white; }

        /* OVERLAY */
        .sidebar-overlay { 
            display: none; position: fixed; 
            top: 0; left: 0; right: 0; bottom: 0; 
            background: rgba(0, 0, 0, 0.4); z-index: 1050; 
        }
        .sidebar-overlay.active { display: block; }

        /* MAIN CONTENT */
        .main-content { margin-left: var(--sidebar-width); transition: 0.3s; min-height: 100vh; }
        .topbar { background: var(--card-bg); padding: 15px 30px; display: flex; align-items: center; border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 1000; }
        
        /* AVATAR */
        .avatar { width: 90px; height: 90px; border-radius: 50%; background: var(--primary-color); color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 36px; font-weight: 700; }

        /* MOBILE VIEW */
        #sidebar-toggle-btn { display: none; background: #f1f5f9; border: none; padding: 8px 12px; border-radius: 8px; color: var(--text-dark); margin-right: 15px; }

        @media (max-width: 992px) {
            #sidebar-toggle-btn { display: block; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); box-shadow: 15px 0 30px rgba(0,0,0,0.1); }
            .main-content { margin-left: 0; }
            .topbar h3 { font-size: 1.2rem; flex-grow: 1; text-align: center; margin-bottom: 0; }
        }

        .card { border-radius: 20px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebar-overlay"></div>

<div class="sidebar" id="admin-sidebar">
    <div class="sidebar-header">
        <div class="logo-icon"><i class="fa-solid fa-user-shield"></i></div>
        <div class="logo-text"><strong>UniKL Admin</strong><span class="small text-muted d-block">System Control</span></div>
    </div>
    
    <nav class="flex-grow-1">
        <a href="manageItem_admin.php"><i class="fa-solid fa-box-archive"></i> Manage Items</a>
        <a href="manage_accounts.php"><i class="fa-solid fa-users-cog"></i> Manage Accounts</a>
        <a href="report_admin.php"><i class="fa-solid fa-chart-pie"></i> System Report</a>
    </nav>
    
    <a href="logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>

<div class="main-content">
    <div class="topbar">
        <button id="sidebar-toggle-btn"><i class="fa fa-bars"></i></button>
        <h3 class="mb-0">My Profile</h3>
        <div class="ms-auto">
            <i class="fa-solid fa-circle-user fa-2x" style="color: var(--primary-color);"></i>
        </div>
    </div>

    <div class="container-fluid py-4">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card p-4 text-center h-100">
                    <div class="avatar">
                        <?= htmlspecialchars(strtoupper(substr($admin['name'], 0, 1))) ?>
                    </div>
                    <h4 class="fw-bold mb-1"><?= htmlspecialchars($admin['name']) ?></h4>
                    <p class="text-muted small"><?= htmlspecialchars($admin['email']) ?></p>
                    <div class="mt-3 p-2 bg-light rounded-3">
                        <small class="text-uppercase fw-bold text-muted" style="font-size: 10px;">Account Role</small>
                        <div class="text-dark fw-bold">System Administrator</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card p-4">
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show">
                            <?= $_SESSION['message']; unset($_SESSION['message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div id="viewMode">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0 fw-bold"><i class="fa-solid fa-id-card me-2 text-info"></i> Personal Info</h5>
                            <button id="editBtn" class="btn btn-primary px-4 rounded-pill"><i class="fa-solid fa-pen-to-square me-2"></i>Edit</button>
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="small text-muted">Full Name</label>
                                <div class="fw-bold"><?= htmlspecialchars($admin['name']) ?></div>
                            </div>
                            <div class="col-sm-6">
                                <label class="small text-muted">Phone Number</label>
                                <div class="fw-bold"><?= htmlspecialchars($admin['phoneNum']) ?></div>
                            </div>
                            <div class="col-12">
                                <label class="small text-muted">Email Address</label>
                                <div class="fw-bold"><?= htmlspecialchars($admin['email']) ?></div>
                            </div>
                        </div>
                    </div>

                    <div id="editMode" style="display: none;">
                        <h5 class="mb-4 fw-bold">Update Profile</h5>
                        <form action="update_profile_admin.php" method="POST">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Full Name</label>
                                <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($admin['name']) ?>" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-bold">Email</label>
                                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($admin['email']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-bold">Phone</label>
                                    <input type="text" class="form-control" name="phoneNum" value="<?= htmlspecialchars($admin['phoneNum']) ?>" required>
                                </div>
                            </div>
                            <hr class="my-4">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-bold">New Password (Optional)</label>
                                    <input type="password" class="form-control" name="new_password">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-bold">Confirm Password</label>
                                    <input type="password" class="form-control" name="confirm_password">
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                                <button type="button" id="cancelBtn" class="btn btn-light px-4 ms-2">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('admin-sidebar');
        const toggleBtn = document.getElementById('sidebar-toggle-btn');
        const overlay = document.getElementById('sidebar-overlay');
        
        // Toggle Sidebar Function
        function toggleSidebar() {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        }

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleSidebar();
            });
        }

        overlay.addEventListener('click', toggleSidebar);

        // Edit/View Toggle
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
    });
</script>
</body>
</html>