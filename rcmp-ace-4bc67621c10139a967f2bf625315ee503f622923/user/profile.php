<?php
session_start();
// Pastikan path ke config.php adalah betul
include '../config.php'; // Path disesuaikan

// Pastikan pengguna log masuk
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Ambil maklumat pengguna terkini dari pangkalan data
$stmt = $conn->prepare("SELECT name, email, phoneNum FROM user WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
// Jika $conn ditutup di sini, pastikan ia dibuka semula jika fail lain memerlukannya.

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}
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
        /* 1. FONT & BODY BACKGROUND */
        body { 
            font-family: 'Inter', 'Segoe UI', sans-serif; 
            background-color: #f8fafc;
            color: #334155;
            min-height: 100vh; 
        }

        /* 2. SIDEBAR (Warna dikemas kini ke TEAL CERAH: #00BCD4) */
        .sidebar { 
            width: 250px; position: fixed; top: 0; bottom: 0; left: 0; 
            background: #ffffff; 
            padding: 20px; 
            border-right: 1px solid #e5e7eb;
            z-index: 1000;
            display: flex; flex-direction: column; justify-content: space-between;
        }
        .sidebar-header { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
        .logo-icon { 
            width: 40px; height: 40px; 
            background-color: #00BCD4; /* TEAL CERAH */ 
            color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; 
        }
        .logo-text strong { display: block; font-size: 16px; color: #1e293b; }
        .logo-text span { font-size: 12px; color: #94a3b8; }
        
        .sidebar a { display: flex; align-items: center; gap: 12px; color: #64748b; text-decoration: none; padding: 12px 15px; margin-bottom: 8px; border-radius: 8px; font-weight: 500; font-size: 15px; transition: all 0.2s ease-in-out; }
        .sidebar a:not(.logout-link):hover { 
            background: #00BCD4; /* TEAL CERAH */ 
            color: #fff; 
        }
        .sidebar a.active { 
            background: #00BCD4; /* TEAL CERAH */ 
            color: #fff; 
        }
        .sidebar a.logout-link { color: #ef4444; font-weight: 600; margin-top: auto; }
        .sidebar a.logout-link:hover { color: #fff; background: #ef4444; }

        /* 3. MAIN LAYOUT & TOPBAR */
        .main-content { margin-left: 250px; }
        .topbar { background: #ffffff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; }
        .topbar h3 { font-weight: 600; margin: 0; color: #1e293b; font-size: 22px; }
        .container-fluid { padding: 30px; }

        /* 4. CARDS & PROFILE SPECIFIC STYLES (Warna dikemas kini ke TEAL CERAH) */
        .card { border-radius: 16px; padding: 25px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); background: #fff; margin-bottom: 25px; border: 1px solid #e2e8f0; }
        .card h5 { font-weight: 600; color: #1e293b; }
        .profile-header-card { text-align: center; }
        .avatar {
            width: 100px; height: 100px; border-radius: 50%;
            background: #00BCD4; /* TEAL CERAH */ 
            color: white;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 15px auto;
            font-size: 48px; font-weight: 600;
        }
        .btn { border-radius: 8px; padding: 10px 20px; font-weight: 500; }
        .btn-primary { 
            background-color: #00BCD4; /* TEAL CERAH */ 
            border: none; 
        }
        .btn-primary:hover { 
            background-color: #00A7BB; /* TEAL CERAH GELAP SEDIKIT (Hover) */ 
        }
        
        /* 5. MOBILE OPTIMIZATIONS (Dikekalkan) */
        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease-in-out; z-index: 1050; position: fixed; }
            .offcanvas-open .sidebar { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .topbar { padding: 15px 15px; }
            .topbar .d-lg-none { display: block !important; }
            .container-fluid { padding: 15px; }
            .profile-header-card { margin-bottom: 20px; }
        }
        
        /* Backdrop for Off-Canvas effect (Dikekalkan) */
        .offcanvas-backdrop {
            position: fixed; top: 0; left: 0; z-index: 1040; width: 100vw; height: 100vh;
            background-color: #000; opacity: 0.5; transition: opacity 0.3s ease-in-out;
        }

        /* 6. CSS Tambahan untuk Senarai Semak Kata Laluan */
        .password-checklist {
            display: grid;
            grid-template-columns: 1fr 1fr; /* 2 kolum */
            gap: 5px 20px;
            list-style-type: none; 
            padding-left: 0;
            margin-top: 10px;
            font-size: 0.9em;
        }
        .password-checklist li {
            white-space: nowrap; 
        }

        /* 7. SHOW/HIDE PASSWORD ICON (BARU) */
        .password-input-group {
            position: relative;
        }
        .toggle-password {
            position: absolute;
            top: 50%;
            right: 15px; /* Sesuaikan jarak dari tepi */
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8; /* Warna ikon (kelabu lembut) */
            z-index: 10; /* Pastikan ikon di atas input */
        }
        .toggle-password:hover {
            color: #00BCD4; /* TEAL CERAH */ 
        }
    </style>
</head>
<body>

<div class="offcanvas-backdrop fade" id="sidebar-backdrop" style="display: none;"></div>

<div class="sidebar" id="offcanvasSidebar">
    <div>
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fa-solid fa-cube"></i></div>
            <div class="logo-text">
                <strong>UniKL User</strong>
                <span>Equipment System</span>
            </div>
        </div>
        <a href="dashboard_user.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        <a href="item_user.php"><i class="fa-solid fa-box"></i> Item Availability</a>
        <a href="history.php"><i class="fa-solid fa-clock-rotate-left"></i> History</a>
    </div>
    <a href="logout.php" class="logout-link">
        <i class="fa-solid fa-right-from-bracket"></i> Logout
    </a>
</div>

<div class="main-content">
    <div class="topbar">
        <button class="btn btn-sm btn-outline-primary d-lg-none" type="button" id="sidebarToggle" aria-controls="offcanvasSidebar">
            <i class="fa-solid fa-bars"></i>
        </button>

        <h3>My Profile</h3>
        <a href="profile.php" title="My Profile" style="color: inherit; text-decoration: none;">
            <i class="fa-solid fa-user-circle fa-2x" style="color: #00BCD4;"></i>
        </a>
    </div>

    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-4 col-12">
                <div class="card profile-header-card h-100">
                    <div class="avatar">
                        <?= htmlspecialchars(strtoupper(substr($user['name'], 0, 1))) ?>
                    </div>
                    <h4 class="fw-bold"><?= htmlspecialchars($user['name']) ?></h4>
                    <p class="text-muted mb-0"><?= htmlspecialchars($user['email']) ?></p>
                </div>
            </div>

            <div class="col-lg-8 col-12">
                <div class="card">
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-success"><?= $_SESSION['message']; unset($_SESSION['message']); ?></div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                    <?php endif; ?>

                    <div id="viewMode">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5><i class="fa-solid fa-id-card me-2" style="color: #00BCD4;"></i> Personal Information</h5>
                            <button id="editBtn" class="btn btn-primary"><i class="fa-solid fa-pen me-2"></i> Edit Profile</button>
                        </div>
                        <hr>
                        <p><strong>Full Name:</strong> <?= htmlspecialchars($user['name']) ?></p>
                        <p><strong>Email Address:</strong> <?= htmlspecialchars($user['email']) ?></p>
                        <p class="mb-0"><strong>Phone Number:</strong> <?= htmlspecialchars($user['phoneNum']) ?></p>
                    </div>

                    <div id="editMode" style="display: none;">
                        <h5><i class="fa-solid fa-pen-to-square me-2" style="color: #00BCD4;"></i> Edit Your Information</h5>
                        <hr>
                        <form action="update_profile.php" method="POST" id="profileUpdateForm">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="name" id="name" value="<?= htmlspecialchars($user['name']) ?>" readonly>
                                <small class="form-text text-muted">Full name cannot be changed.</small>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="phoneNum" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" name="phoneNum" value="<?= htmlspecialchars($user['phoneNum']) ?>" required>
                            </div>
                            <hr>
                            <p class="text-muted">Update your password (optional)</p>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    
                                    <div class="password-input-group">
                                        <input type="password" class="form-control" name="new_password" id="new_password" placeholder="Leave blank to keep current">
                                        <i class="fa-solid fa-eye-slash toggle-password" id="togglePassword"></i>
                                    </div>
                                    
                                    <div id="password-requirements" style="margin-top: 5px;">
                                        <ul class="password-checklist">
                                            <li id="req-length" style="color: red;">❌ 8+ characters</li>
                                            <li id="req-lowercase" style="color: red;">❌ Lowercase letter</li>
                                            <li id="req-uppercase" style="color: red;">❌ Uppercase letter</li>
                                            <li id="req-number" style="color: red;">❌ A number</li>
                                            <li id="req-special" style="color: red;">❌ Special character</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" name="confirm_password" id="confirm_password">
                                    <p id="match-status" class="mt-2" style="font-size: 0.9em;"></p>
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
document.addEventListener('DOMContentLoaded', function() {
    
    // ==========================================================
    // --- PENGISYTIHARAN PEMBOLEHUBAH GLOBAL UTAMA ---
    // ==========================================================
    const passwordInput = document.getElementById('new_password');
    const confirmInput = document.getElementById('confirm_password');
    const matchStatus = document.getElementById('match-status');
    const form = document.getElementById('profileUpdateForm');


    // --- Logik Sidebar (Dikekalkan) ---
    const sidebar = document.getElementById('offcanvasSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const backdrop = document.getElementById('sidebar-backdrop');
    const body = document.body;

    // --- Logik Show/Hide Password ---
    const togglePassword = document.getElementById('togglePassword');
    
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function () {
            // Tukar jenis input: password <-> text
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Tukar ikon: mata tertutup <-> mata terbuka
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    }
    
    function toggleSidebar() {
        const isOpen = sidebar.style.transform === 'translateX(0px)';
        if (isOpen) {
            sidebar.style.transform = 'translateX(-100%)';
            backdrop.style.display = 'none';
            body.classList.remove('offcanvas-open');
        } else {
            sidebar.style.transform = 'translateX(0px)';
            backdrop.style.display = 'block';
            body.classList.add('offcanvas-open');
        }
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', toggleSidebar);
    }
    if (backdrop) {
        backdrop.addEventListener('click', toggleSidebar);
    }


    const viewMode = document.getElementById('viewMode');
    const editMode = document.getElementById('editMode');
    const editBtn = document.getElementById('editBtn');
    const cancelBtn = document.getElementById('cancelBtn');

    if (editBtn && cancelBtn && viewMode && editMode) {
        editBtn.addEventListener('click', () => {
            viewMode.style.display = 'none';
            editMode.style.display = 'block';
            
            // Panggil checkRequirements semasa Edit Mode dibuka untuk set status awal (semua ❌)
            checkPasswordRequirements(document.getElementById('new_password').value);
        });

        cancelBtn.addEventListener('click', () => {
            editMode.style.display = 'none';
            viewMode.style.display = 'block';
            document.getElementById('profileUpdateForm').reset();
            
            // Reset status match dan status syarat kata laluan
            matchStatus.textContent = '';
            checkPasswordRequirements(''); 
        });
    }
    
    // ==========================================================
    // --- LOGIK PENGESAHAN KATA LALUAN (CHECKLIST) ---
    // ==========================================================
    
    const requirements = {
        length: document.getElementById('req-length'),
        uppercase: document.getElementById('req-uppercase'),
        lowercase: document.getElementById('req-lowercase'),
        number: document.getElementById('req-number'),
        special: document.getElementById('req-special'),
    };

    function checkPasswordRequirements(password) {
        // Regexes (Sama seperti yang digunakan dalam PHP)
        const checks = {
            length: password.length >= 8,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[\W_]/.test(password), 
        };

        let allValid = true;

        for (const key in checks) {
            const isValid = checks[key];
            const element = requirements[key];
            
            // Kemas kini paparan (❌ <-> ✅)
            if (isValid) {
                element.style.color = 'green';
                element.innerHTML = element.innerHTML.replace('❌', '✅'); 
            } else {
                element.style.color = 'red';
                element.innerHTML = element.innerHTML.replace('✅', '❌');
                allValid = false;
            }
        }
        return allValid;
    }

    function checkPasswordMatch() {
        if (passwordInput.value.length === 0) {
            matchStatus.textContent = '';
            return true;
        }
        
        if (passwordInput.value === confirmInput.value) {
            matchStatus.textContent = 'Passwords match!';
            matchStatus.style.color = 'green';
            return true;
        } else {
            matchStatus.textContent = 'Passwords do not match!';
            matchStatus.style.color = 'red';
            return false;
        }
    }

    // --- Event Listeners Kata Laluan ---
    
    passwordInput.addEventListener('keyup', function() {
        checkPasswordRequirements(this.value); 
        checkPasswordMatch();
    });

    confirmInput.addEventListener('keyup', checkPasswordMatch);

    // Halang penghantaran borang jika kata laluan diisi tetapi tidak sah
    form.addEventListener('submit', function(e) {
        if (passwordInput.value.length > 0) {
            const isValid = checkPasswordRequirements(passwordInput.value);
            const isMatch = checkPasswordMatch();
            
            if (!isValid) {
                e.preventDefault();
                alert("Please meet all the new password requirements before submitting.");
            } else if (!isMatch) {
                e.preventDefault();
                alert("New password and confirmation do not match.");
            }
        }
    });
});
</script>
</body>
</html>