<?php
session_start();

include '../config.php';

if (!$conn) {
    $_SESSION['error'] = "Database connection error.";
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['person_id'])) {
    header("Location: ../login.php");
    exit();
}

$person_id = (int) $_SESSION['person_id'];

$user = null;

$stmt_user = $conn->prepare("SELECT id, name, email, phoneNum FROM person WHERE person_id = ?");

if ($stmt_user) {
    $stmt_user->bind_param("i", $person_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    $user = $result_user->fetch_assoc();
    $stmt_user->close();
} else {
    error_log("Failed to prepare user statement: " . $conn->error);
}

if (!$user) {
    error_log("SECURITY ALERT: User with person_id " . $person_id . " was not found.");
    session_destroy();
    $_SESSION['error'] = "User data not found or session invalid.";
    header("Location: ../login.php");
    exit();
}

$user['person_id'] = $person_id;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — UniKL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    /* --- CSS STYLES --- */
    :root {
        --primary-color: #06b6d4; 
        --primary-light: #f0f9ff; 
        --primary-hover: #0891b2; 
        --bg-light-gray: #f4f7f9; 
        --card-bg: #ffffff; 
        --text-dark: #1e293b;
        --text-muted: #64748b;
        --shadow-light: 0 4px 12px rgba(0, 0, 0, 0.05);
        --danger-color: #ef4444; 
    }
    
    body {
        font-family: 'Inter', sans-serif;
        background-color: var(--bg-light-gray);
        color: var(--text-dark);
        min-height: 100vh;
    }
    
    .sidebar {
        width: 280px; position: fixed; top: 0; bottom: 0; left: 0;
        background: var(--card-bg); padding: 20px;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05); z-index: 1050; 
        display: flex; flex-direction: column; justify-content: space-between;
        /* Default pada desktop, tiada transform. Transform hanya diletakkan di media query */
        transition: transform 0.3s ease-in-out;
    }

    /* KAWALAN DESKTOP (Lebar > 992px) */
    .main-content { 
        margin-left: 280px; /* Kuncinya di sini: margin wajib untuk desktop */
    }
    
    /* KOD BARU/DIUBAHSUAI UNTUK KAWALAN SIDEBAR PADA MOBILE */
    .sidebar.active {
        transform: translateX(0px) !important;
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

    .topbar { 
        background: var(--card-bg); 
        padding: 18px 30px; 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        border-bottom: 1px solid #eef1f4; 
        z-index: 1040; 
        position: sticky; 
        top: 0; 
    }
    .topbar h3 { font-weight: 700; margin: 0; color: var(--text-dark); font-size: 24px; }
    .container-fluid { padding: 30px; }
    
    .user-profile { display: flex; align-items: center; justify-content: center; }
    .user-profile .user-name { font-weight: 700 !important; color: var(--text-dark); margin-right: 10px; }
    
    .card {
        border-radius: 12px; box-shadow: var(--shadow-light);
        background: var(--card-bg); margin-bottom: 25px;
        border: none; padding: 25px;
    }
    .card h5 { font-weight: 600; color: var(--text-dark); }
    
    .profile-header-card { text-align: center; padding: 40px 25px; }
    .avatar {
        width: 120px; height: 120px; border-radius: 50%;
        background: var(--primary-light);
        color: var(--primary-color);
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 20px auto;
        font-size: 55px; font-weight: 600;
        border: 4px solid var(--primary-color);
    }

    .basic-info strong { font-size: 1.5rem; font-weight: 700; display: block; margin-bottom: 5px; }
    .basic-info span { color: var(--text-muted); font-size: 0.95rem; }

    .info-card-container { margin-top: 20px; display: flex; flex-direction: column; gap: 15px; }
    .info-card {
        padding: 15px 20px;
        background-color: var(--bg-light-gray);
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 15px;
        border: 1px solid #eef1f4;
    }
    .info-icon-wrapper {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: var(--primary-color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    .info-icon-wrapper.id-wrapper {
        background-color: var(--text-muted);
    }

    .info-details strong {
        display: block;
        font-size: 13px;
        color: var(--text-muted);
        text-transform: uppercase;
        margin-bottom: 2px;
    }
    .info-details span {
        font-weight: 600;
        color: var(--text-dark);
        font-size: 16px;
    }

    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(6, 182, 212, 0.25);
    }
    .btn { border-radius: 8px; padding: 10px 20px; font-weight: 600; }
    .btn-primary {
        background-color: var(--primary-color);
        border: none;
        transition: background-color 0.2s;
    }
    .btn-primary:hover { background-color: var(--primary-hover); }

    /* MEDIA QUERIES UNTUK RESPONSIVITI MOBILE (< 992px) */
    @media (max-width: 992px) {
        .sidebar { transform: translateX(-280px); left: 0; width: 280px; } 
        .main-content { margin-left: 0; width: 100%; }
        .topbar { padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .topbar h3 { font-size: 20px; }
        .container-fluid { padding: 15px; }
    }
    
    /* KAWALAN BACKDROP */
    #sidebar-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        opacity: 0;
        visibility: hidden;
        z-index: 1045; 
        transition: opacity 0.3s ease-in-out;
        display: none; 
    }

    #sidebar-backdrop.active {
        opacity: 1;
        visibility: visible;
    }
</style>
</head>
<body>

<div class="offcanvas-backdrop fade" id="sidebar-backdrop"></div>

<div class="sidebar" id="offcanvasSidebar">
    <div>
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fa-solid fa-cube"></i></div>
            <div class="logo-text"><strong>UniKL User</strong><span>Equipment System</span></div>
        </div>
        <a href="dashboard_user.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        <a href="item_user.php"><i class="fa-solid fa-box"></i> Item Availability</a>
        <a href="history.php"><i class="fa-solid fa-clock-rotate-left"></i> History</a>
    </div>
    <a href="logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>

<div class="main-content">
    <div class="topbar">
        <button class="btn btn-sm btn-outline-primary d-lg-none" type="button" id="sidebarToggle">
            <i class="fa-solid fa-bars"></i>
        </button>

        <h3>My Profile</h3>
        <div class="user-profile">
            <span class="user-name d-none d-md-inline"><?= htmlspecialchars($user['name']) ?></span>
            <a href="profile.php" title="My Profile" style="color: inherit; text-decoration: none;">
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
                                <?= htmlspecialchars(strtoupper(substr($user['name'], 0, 1))) ?>
                            </div>
                            <div class="basic-info">
                                <strong><?= htmlspecialchars($user['name']) ?></strong>
                                <span><?= htmlspecialchars($user['email']) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="card">
                            <?php if (isset($_SESSION['message'])): ?>
                                <div class="alert alert-success"><i class="fa-solid fa-check-circle me-2"></i><?= $_SESSION['message']; unset($_SESSION['message']); ?></div>
                            <?php endif; ?>
                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                            <?php endif; ?>

                            <div id="viewMode">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5><i class="fa-solid fa-id-card me-2 text-primary"></i> Contact Information</h5>
                                    <button id="editBtn" class="btn btn-primary"><i class="fa-solid fa-pen me-2"></i> Edit Profile</button>
                                </div>
                                
                                <div class="info-card-container">
                                    <div class="info-card">
                                        <div class="info-icon-wrapper id-wrapper"><i class="fa-solid fa-hashtag"></i></div>
                                        <div class="info-details">
                                            <strong>ID</strong>
                                            <span><?= htmlspecialchars($user['id']) ?></span>
                                        </div>
                                    </div>
                                    <div class="info-card">
                                        <div class="info-icon-wrapper"><i class="fa-solid fa-envelope"></i></div>
                                        <div class="info-details">
                                            <strong>Email Address</strong>
                                            <span><?= htmlspecialchars($user['email']) ?></span>
                                        </div>
                                    </div>
                                    <div class="info-card">
                                        <div class="info-icon-wrapper"><i class="fa-solid fa-phone"></i></div>
                                        <div class="info-details">
                                            <strong>Phone Number</strong>
                                            <span><?= htmlspecialchars($user['phoneNum']) ?></span>
                                        </div>
                                    </div>
                                    <div class="info-card">
                                        <div class="info-icon-wrapper" style="background-color: var(--text-muted);"><i class="fa-solid fa-user-tag"></i></div>
                                        <div class="info-details">
                                            <strong>Full Name</strong>
                                            <span><?= htmlspecialchars($user['name']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="editMode" style="display: none;">
                                <h5><i class="fa-solid fa-pen-to-square me-2 text-primary"></i> Update Your Details</h5>
                                <hr>
                                <form action="update_profile.php" method="POST">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="id" class="form-label">ID</label>
                                            <input type="text" class="form-control" name="id" value="<?= htmlspecialchars($user['id']) ?>" readonly>
                                            <small class="form-text text-muted"><i class="fa-solid fa-lock me-1"></i> ID (Staff/Student ID) cannot be changed.</small>
                                        </div>
                                        
                                        <input type="hidden" name="person_id" value="<?= htmlspecialchars($user['person_id']) ?>">

                                        <div class="col-md-6 mb-3">
                                            <label for="name" class="form-label">Full Name</label>
                                            <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($user['name']) ?>" readonly>
                                            <small class="form-text text-muted"><i class="fa-solid fa-lock me-1"></i> Full name cannot be changed.</small>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">Email Address</label>
                                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="phoneNum" class="form-label">Phone Number</label>
                                            <input type="text" class="form-control" name="phoneNum" value="<?= htmlspecialchars($user['phoneNum']) ?>" required>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // 1. LOGIK PERTUKARAN MOD VIEW/EDIT
    const viewMode = document.getElementById('viewMode');
    const editMode = document.getElementById('editMode');
    const editBtn = document.getElementById('editBtn');
    const cancelBtn = document.getElementById('cancelBtn');

    const hasSessionAlert = <?php echo (isset($_SESSION['error']) || isset($_SESSION['message'])) ? 'true' : 'false'; ?>;

    if (hasSessionAlert) {
        viewMode.style.display = 'none';
        editMode.style.display = 'block';
        window.scrollTo(0, 0); 
    }

    if (editBtn && cancelBtn) {
        editBtn.addEventListener('click', () => {
            viewMode.style.display = 'none';
            editMode.style.display = 'block';
        });

        cancelBtn.addEventListener('click', () => {
            editMode.style.display = 'none';
            viewMode.style.display = 'block';
        });
    }

    
    // 2. LOGIK KAWALAN SIDEBAR (OFF-CANVAS) - DIBETULKAN UNTUK MENGHORMATI DESKTOP CSS
    const sidebar = document.getElementById('offcanvasSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const backdrop = document.getElementById('sidebar-backdrop');
    
    // Fungsi untuk mengawal sidebar HANYA pada mobile
    function toggleSidebar() {
        if (window.innerWidth <= 992) {
            const isActive = sidebar.classList.contains('active');

            sidebar.classList.toggle('active');

            if (!isActive) {
                backdrop.classList.add('active');
                backdrop.style.display = 'block'; 
            } else {
                backdrop.classList.remove('active');
                setTimeout(() => { 
                    backdrop.style.display = 'none'; 
                }, 300); 
            }
        }
    }
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', toggleSidebar);
    }

    if (backdrop) {
        backdrop.addEventListener('click', toggleSidebar); 
    }

    // Fungsi run-once pada muatan (LOAD)
    function initializeSidebar() {
        if (window.innerWidth <= 992) {
             // Sembunyikan sidebar pada muatan jika mobile
             sidebar.style.transform = 'translateX(-280px)'; 
        } else {
             // Pastikan sidebar berada di tempat yang betul pada desktop
             sidebar.style.transform = 'translateX(0px)';
             // Hapus kelas aktif jika ada (untuk mencegah isu selepas resize)
             sidebar.classList.remove('active');
             backdrop.classList.remove('active');
             backdrop.style.display = 'none';
        }
    }

    // PENTING: Panggil fungsi pada muatan
    initializeSidebar(); 
    
    // Mengendalikan perubahan saiz skrin
    window.addEventListener('resize', initializeSidebar);
    
});
</script>

</body>
</html>