<?php
session_start();
include 'config.php';

// Logic switch role - Jika sudah login, terus ke dashboard
if (isset($_SESSION['person_id']) && isset($_SESSION['logged_in_role'])) {
    $role = $_SESSION['logged_in_role'];
    switch ($role) {
        case 'Admin': header("Location: admin/manageItem_admin.php"); exit();
        case 'Technician': header("Location: technician/dashboard_tech.php"); exit();
        case 'User': header("Location: user/dashboard_user.php"); exit();
    }
}

$login_attempt_role = $_SESSION['login_attempt_role'] ?? '';
$login_attempt_email = $_SESSION['login_attempt_email'] ?? '';
unset($_SESSION['login_attempt_role'], $_SESSION['login_attempt_email']);

$errorMessage = $_SESSION['error'] ?? '';
$successMessage = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

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
        --primary: #002147;      /* Navy RCMP */
        --accent: #00A3C9;       /* Cyan RCMP */
        --bg-body: #f8fafc;
        --text-main: #1e293b;
        --text-muted: #64748b;
        --white: #ffffff;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: 'Poppins', sans-serif;
        height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: var(--bg-body);
        overflow: hidden;
        position: relative;
    }

    /* Background Image dengan Blur (Sebahagian dari Branding) */
    body::before {
        content: "";
        position: absolute;
        inset: 0;
        background-image: url('img/view.png'); /* Pastikan path fail betul */
        background-size: cover;
        background-position: center;
        filter: blur(10px) brightness(0.4);
        transform: scale(1.1);
        z-index: -1;
    }

    .main-container {
        display: flex;
        width: 900px;
        max-width: 95%;
        height: 550px;
        background: var(--white);
        border-radius: 40px; /* Rounding besar macam card landing page */
        overflow: hidden;
        box-shadow: 0 40px 100px -20px rgba(0, 33, 71, 0.3);
        border: 1px solid rgba(255,255,255,0.3);
    }

    /* KIRI: Visual Branding */
    .side-visual {
        flex: 1;
        background: var(--primary);
        padding: 50px;
        color: white;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
    }

    /* Efek hiasan sikit kat tepi */
    .side-visual::after {
        content: "";
        position: absolute;
        bottom: -50px;
        right: -50px;
        width: 200px;
        height: 200px;
        background: var(--accent);
        border-radius: 50%;
        opacity: 0.2;
        filter: blur(40px);
    }

    .side-visual img { height: 45px; width: auto; border-radius: 8px; margin-bottom: 20px;}
    .side-visual h1 { font-size: 2rem; font-weight: 800; line-height: 1.1; letter-spacing: -1px; }
    .side-visual h1 span { color: var(--accent); }
    .side-visual p { font-size: 0.9rem; opacity: 0.8; margin-top: 20px; font-weight: 300; }

    /* KANAN: Form Section */
    .side-form {
        flex: 1.2;
        padding: 50px;
        background: var(--white);
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    h2 { font-weight: 800; color: var(--primary); font-size: 1.8rem; letter-spacing: -1px; margin-bottom: 10px; }
    .sub-text { color: var(--text-muted); font-size: 0.85rem; margin-bottom: 30px; }

    /* Role Cards */
    .roles-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        margin-bottom: 20px;
    }

    .role-card { cursor: pointer; text-align: center; }
    .role-box {
        padding: 20px 10px;
        border-radius: 20px;
        border: 2px solid #f1f5f9;
        transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .role-box i { font-size: 1.8rem; color: var(--accent); margin-bottom: 10px; display: block; }
    .role-box span { font-size: 0.75rem; font-weight: 700; color: var(--text-main); }

    .role-box:hover { 
        transform: translateY(-5px);
        border-color: var(--accent);
        background: rgba(0, 163, 201, 0.05);
    }

    /* Login Form (Step 2) */
    #login-section { display: none; animation: fadeIn 0.5s ease; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    .input-group { margin-bottom: 20px; position: relative; }
    .input-group label { font-size: 0.75rem; font-weight: 700; color: var(--primary); display: block; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
    .input-group input {
        width: 100%;
        padding: 14px 16px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        font-family: inherit;
        font-size: 0.9rem;
        transition: 0.3s;
        background: #f8fafc;
    }
    .input-group input:focus { 
        outline: none; 
        border-color: var(--accent); 
        background: white;
        box-shadow: 0 0 0 4px rgba(0, 163, 201, 0.1);
    }

    .toggle-password { position: absolute; right: 16px; bottom: 14px; color: var(--text-muted); cursor: pointer; }

    .btn-login {
        background: var(--primary);
        color: white;
        border: none;
        padding: 16px;
        border-radius: 50px;
        width: 100%;
        font-weight: 700;
        font-size: 1rem;
        cursor: pointer;
        transition: 0.3s;
        margin-top: 10px;
        box-shadow: 0 10px 20px -5px rgba(0, 33, 71, 0.3);
    }
    .btn-login:hover { background: var(--accent); transform: translateY(-3px); box-shadow: 0 15px 25px -5px rgba(0, 163, 201, 0.4); }

    .back-btn {
        background: none; border: none; color: var(--accent); font-weight: 700; font-size: 0.75rem; cursor: pointer; display: flex; align-items: center; gap: 5px; margin-bottom: 20px;
    }

    /* Alerts */
    .alert { padding: 12px; border-radius: 12px; font-size: 0.8rem; margin-bottom: 20px; font-weight: 500; }
    .alert-error { background: #fee2e2; color: #991b1b; }

    @media (max-width: 768px) {
        .side-visual { display: none; }
        .main-container { width: 100%; height: auto; border-radius: 30px; }
        .side-form { padding: 40px 30px; }
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
        
        <div style="font-size: 0.75rem; opacity: 0.6;">
            <i class="fas fa-shield-alt"></i> Secure Protocol Active
        </div>
    </div>

    <div class="side-form">
        
        <div id="role-section">
            <h2>Welcome Back</h2>
            <p class="sub-text">Identify your access level to continue to the dashboard.</p>
            
            <div class="roles-grid">
                <div class="role-card" onclick="showLogin('Admin', 'Administrator')">
                    <div class="role-box">
                        <i class="fas fa-user-shield"></i>
                        <span>Admin</span>
                    </div>
                </div>
                <div class="role-card" onclick="showLogin('tech', 'Technician')">
                    <div class="role-box">
                        <i class="fas fa-screwdriver-wrench"></i>
                        <span>Technician</span>
                    </div>
                </div>
                <div class="role-card" onclick="showLogin('user', 'Staff / Student')">
                    <div class="role-box">
                        <i class="fas fa-user"></i>
                        <span>Staff/Student</span>
                    </div>
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
                <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($errorMessage) ?></div>
            <?php endif; ?>

            <form action="login_process.php" method="POST">
                <input type="hidden" name="role" id="role-input">

                <div class="input-group">
                    <label>UniKL Email</label>
                    <input type="email" name="email" id="email" value="<?= htmlspecialchars($login_attempt_email) ?>" placeholder="e.g. name@unikl.edu.my" required>
                </div>

                <div class="input-group">
                    <label>Password</label>
                    <input type="password" name="password" id="password" placeholder="••••••••" required>
                    <i class="fas fa-eye-slash toggle-password" id="togglePassword"></i>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; font-size: 0.75rem;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-muted);">
                        <input type="checkbox" name="remember_me" value="1" <?= (!empty($remembered_email)) ? 'checked' : '' ?>> Remember Me
                    </label>
                    <a href="forgot_password.php" style="color: var(--accent); text-decoration: none; font-weight: 700;">Forgot Password?</a>
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

        const emailField = document.getElementById('email');
        if(emailField.value === "") emailField.focus();
        else document.getElementById('password').focus();
    }

    function goBack() {
        document.getElementById('role-section').style.display = 'block';
        document.getElementById('login-section').style.display = 'none';
    }

    // Toggle Password Visibility
    const toggleBtn = document.querySelector("#togglePassword");
    const passField = document.querySelector("#password");

    toggleBtn.addEventListener("click", function() {
        const type = passField.type === "password" ? "text" : "password";
        passField.type = type;
        this.classList.toggle("fa-eye");
        this.classList.toggle("fa-eye-slash");
    });

    // Auto-open if redirected back from error
    const lastRole = '<?= $login_attempt_role ?>';
    if(lastRole) {
        let t = "Administrator";
        if(lastRole === 'tech') t = "Technician";
        if(lastRole === 'user') t = "Staff / Student";
        showLogin(lastRole, t);
    }
</script>

</body>
</html>