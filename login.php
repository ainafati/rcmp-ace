<?php
session_start();
include 'config.php';

// 1. Logic switch role - Jika sudah login, terus ke dashboard
if (isset($_SESSION['person_id']) && isset($_SESSION['logged_in_role'])) {
    $role = $_SESSION['logged_in_role'];
    switch ($role) {
        case 'Admin': header("Location: admin/manageItem_admin.php"); exit();
        case 'Technician': header("Location: technician/dashboard_tech.php"); exit();
        case 'User': header("Location: user/dashboard_user.php"); exit();
    }
}

// 2. Ambil data dari session jika ada error login sebelum ni
$login_attempt_role = $_SESSION['login_attempt_role'] ?? '';
$login_attempt_email = $_SESSION['login_attempt_email'] ?? '';
unset($_SESSION['login_attempt_role'], $_SESSION['login_attempt_email'], $_SESSION['registered_email']);

$errorMessage = $_SESSION['error'] ?? '';
$successMessage = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

// 3. Remember Me Cookie
$remembered_email = $_COOKIE['remember_email'] ?? '';
if (empty($login_attempt_email) && !empty($remembered_email)) {
    $login_attempt_email = $remembered_email;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | NexCheck RCMP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --primary: #002147;
        --accent: #00A3C9;
        --bg-body: #f8fafc;
        --text-main: #1e293b;
        --text-muted: #64748b;
        --white: #ffffff;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    html, body {
        height: 100%;
        width: 100%;
        overflow: hidden; 
        font-family: 'Poppins', sans-serif;
    }

    body {
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: var(--primary);
        position: relative;
    }

    body::before {
        content: "";
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background-image: url('img/view.png'); 
        background-size: cover;
        background-position: center;
        filter: blur(8px) brightness(0.4);
        z-index: -1;
    }

    .main-container {
        display: flex;
        width: 1000px;
        max-width: 95%;
        height: 600px; 
        background: var(--white);
        border-radius: 40px;
        overflow: hidden;
        box-shadow: 0 40px 100px rgba(0,0,0,0.5);
        position: relative;
        z-index: 10;
    }

    .side-visual {
        flex: 1;
        background: var(--primary);
        padding: 40px;
        color: white;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .side-visual img { height: 40px; width: auto; margin-bottom: 20px; }
    .side-visual h1 { font-size: 2rem; font-weight: 800; line-height: 1.1; }
    .side-visual h1 span { color: var(--accent); }
    .side-visual p { font-size: 0.85rem; opacity: 0.8; margin-top: 15px; font-weight: 300; }

    .side-form {
        flex: 1.2;
        padding: 40px;
        background: var(--white);
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    h2 { font-weight: 800; color: var(--primary); font-size: 1.6rem; margin-bottom: 5px; }
    .sub-text { color: var(--text-muted); font-size: 0.8rem; margin-bottom: 25px; }

    .roles-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        margin-bottom: 20px;
    }

    .role-box {
        cursor: pointer;
        padding: 20px 10px;
        border-radius: 20px;
        border: 2px solid #f1f5f9;
        transition: 0.3s;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 130px; 
        text-align: center;
    }

    .role-box i { font-size: 1.8rem; color: var(--accent); margin-bottom: 10px; }
    .role-box span { font-size: 0.75rem; font-weight: 700; color: var(--text-main); }
    .role-box:hover { border-color: var(--accent); transform: translateY(-5px); background: #f0f9ff; }

    #login-section { display: none; animation: fadeIn 0.4s ease; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    .input-group { 
        margin-bottom: 15px; 
        position: relative; 
        width: 100%;
    }

    .input-group label { 
        font-size: 0.7rem; 
        font-weight: 700; 
        color: var(--primary); 
        text-transform: uppercase; 
        margin-bottom: 5px; 
        display: block; 
    }

    /* CONTAINER PASSWORD */
    .password-container {
        position: relative;
        width: 100%;
    }

    .input-group input {
        width: 100%;
        padding: 12px 45px 12px 15px; /* Tambah padding kanan supaya tak bertindih dengan mata */
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        font-size: 0.85rem;
        background: #f8fafc;
        display: block;
        outline: none;
    }

    /* FIX: BUANG MATA DEFAULT DARI BROWSER (EDGE/CHROME) */
    input::-ms-reveal,
    input::-ms-clear {
        display: none;
    }

    /* IKON MATA ANDA */
    .toggle-password {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%); 
        color: var(--text-muted);
        cursor: pointer;
        z-index: 10;
        padding: 5px;
    }

    .btn-login {
        background: var(--primary);
        color: white;
        border: none;
        padding: 14px;
        border-radius: 50px;
        width: 100%;
        font-weight: 700;
        cursor: pointer;
        transition: 0.3s;
    }
    .btn-login:hover { background: var(--accent); }

    .back-btn { background: none; border: none; color: var(--accent); font-weight: 700; font-size: 0.7rem; cursor: pointer; margin-bottom: 15px; }

    .alert { padding: 10px; border-radius: 10px; font-size: 0.75rem; margin-bottom: 15px; background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

    @media (max-width: 768px) {
        .main-container { flex-direction: column; height: auto; max-height: 95vh; overflow-y: auto; }
        .side-visual { display: none; }
        .side-form { padding: 30px 20px; }
    }
</style>
</head>
<body>

<div class="main-container">
    <div class="side-visual">
        <div>
            <img src="img/Logo-UniKL-PCM.jpg" alt="Logo">
            <h1>NexCheck <span>Portal.</span></h1>
            <p>High-precision IT asset reservation and inventory management ecosystem for UniKL RCMP.</p>
        </div>
        <div style="font-size: 0.75rem; opacity: 0.6;"><i class="fas fa-shield-alt"></i> Secure Protocol Active</div>
    </div>

    <div class="side-form">
        <div id="role-section">
            <h2>Welcome Back</h2>
            <p class="sub-text">Identify your access level to continue to the dashboard.</p>
            <div class="roles-grid">
                <div class="role-card" onclick="showLogin('admin', 'Administrator')">
                    <div class="role-box"><i class="fas fa-user-shield"></i><span>Admin</span></div>
                </div>
                <div class="role-card" onclick="showLogin('tech', 'Technician')">
                    <div class="role-box"><i class="fas fa-screwdriver-wrench"></i><span>Technician</span></div>
                </div>
                <div class="role-card" onclick="showLogin('user', 'Staff / Student')">
                    <div class="role-box"><i class="fas fa-user"></i><span>Staff/Student</span></div>
                </div>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="index.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.75rem; font-weight: 600;">
                    <i class="fas fa-arrow-left"></i> Back to Homepage
                </a>
            </div>
        </div>

        <div id="login-section">
            <button class="back-btn" onclick="goBack()"><i class="fas fa-chevron-left"></i> Change Role</button>
            <h2>Login as <span id="role-display" style="color: var(--accent);"></span></h2>
            <p class="sub-text">Enter your UniKL credentials below.</p>

            <?php if ($errorMessage): ?>
                <div class="alert"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($errorMessage) ?></div>
            <?php endif; ?>

            <form action="login_process.php" method="POST">
                <input type="hidden" name="role" id="role-input">

                <div class="input-group">
                    <label>UniKL Email</label>
                    <input type="email" name="email" id="email" value="<?= htmlspecialchars($login_attempt_email) ?>" placeholder="e.g. name@unikl.edu.my" required>
                </div>

                <div class="input-group">
                    <label>Password</label>
                    <div class="password-container">
                        <input type="password" name="password" id="password" placeholder="••••••••" required>
                        <i class="fas fa-eye-slash toggle-password" id="togglePassword"></i>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; font-size: 0.75rem;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-muted);">
                        <input type="checkbox" name="remember_me" value="1" <?= (!empty($remembered_email)) ? 'checked' : '' ?>> Remember Me
                    </label>
                    <a href="javascript:void(0)" onclick="goToForgot()" style="color: var(--accent); text-decoration: none; font-weight: 700;">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-login">SIGN IN</button>

                <div id="signUpLink" style="text-align: center; margin-top: 20px; font-size: 0.8rem; display: none;">
                    Need an account? <a href="signUp.php" style="color: var(--accent); text-decoration: none; font-weight: 700;">Register Now</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function showLogin(val, title) {
        document.getElementById('role-input').value = val;
        document.getElementById('role-display').innerText = title;
        document.getElementById('signUpLink').style.display = (val === 'user') ? 'block' : 'none';
        document.getElementById('role-section').style.display = 'none';
        document.getElementById('login-section').style.display = 'block';
    }

    function goBack() {
        document.getElementById('role-section').style.display = 'block';
        document.getElementById('login-section').style.display = 'none';
    }

    function goToForgot() {
        const role = document.getElementById('role-input').value;
        window.location.href = `forgot_password.php?role=${role}`;
    }

    // TOGGLE PASSWORD - Kod yang lebih bersih
    const togglePassword = document.querySelector("#togglePassword");
    const password = document.querySelector("#password");

    togglePassword.addEventListener("click", function () {
        const type = password.getAttribute("type") === "password" ? "text" : "password";
        password.setAttribute("type", type);
        
        // Tukar ikon
        this.classList.toggle("fa-eye");
        this.classList.toggle("fa-eye-slash");
    });

    window.addEventListener('load', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const roleParam = urlParams.get('role');
        const lastRole = '<?= $login_attempt_role ?>';

        if (roleParam || lastRole) {
            let activeRole = roleParam || lastRole;
            let title = "Staff / Student";
            if (activeRole === 'Admin') title = "Administrator";
            if (activeRole === 'tech') title = "Technician";
            showLogin(activeRole, title);
        }
    });
</script>
</body>
</html>