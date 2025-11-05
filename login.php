<?php
session_start();
include 'config.php'; 

// Redirect if already logged in
if (isset($_SESSION['user_id'])) { header("Location: user/dashboard_user.php"); exit(); }
if (isset($_SESSION['tech_id'])) { header("Location: technician/dashboard_tech.php"); exit(); }
if (isset($_SESSION['admin_id'])) { header("Location: admin/manageItem_admin.php"); exit(); }

// Check for a failed login attempt OR a successful signup
$login_attempt_role = isset($_SESSION['login_attempt_role']) ? $_SESSION['login_attempt_role'] : '';
$login_attempt_email = isset($_SESSION['login_attempt_email']) ? $_SESSION['login_attempt_email'] : '';

// Clear session variables after reading
unset($_SESSION['login_attempt_role'], $_SESSION['login_attempt_email']);

// Retrieve and clear flash messages
$errorMessage = isset($_SESSION['error']) ? $_SESSION['error'] : '';
$successMessage = isset($_SESSION['success']) ? $_SESSION['success'] : '';
unset($_SESSION['error'], $_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - R-ILMS</title>
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
<style>
    /* -------------------------------------------------------------------------- */
    /* GLOBAL & STRUCTURE (Using NEW Color Scheme) */
    /* -------------------------------------------------------------------------- */
    :root {
        --primary-color: #002147;      /* Dark Blue (Text, Main Background) */
        --accent-cyan: #00A3C9;       /* Light Blue/Cyan (Main Accent) */
        --accent-green: #A7D737;      /* Lime Green (Secondary Accent) */
        --light-bg: #f5f8ff;    /* Light background */
        --border-color: #e2e8f0;
        --shadow-light: 0 4px 15px rgba(0, 0, 0, 0.08); /* Adjusted shadow */
    }

    body, html { 
        margin: 0; 
        padding: 0; 
        font-family: 'Inter', sans-serif; 
        height: 100%; 
        background-color: var(--light-bg); 
        overflow-x: hidden; 
    }
    .container { 
        display: flex; 
        height: 100vh;
        min-height: 700px;
    }
    
    /* -------------------------------------------------------------------------- */
    /* INFO PANEL (LEFT) - The main blue panel */
    /* -------------------------------------------------------------------------- */
    .info-panel { 
        flex: 1; 
        background: var(--primary-color); /* Dark Blue Background */
        color: white; 
        display: flex; 
        flex-direction: column; 
        align-items: center; 
        justify-content: center; 
        padding: 40px; 
        text-align: center; 
        box-shadow: 2px 0 20px rgba(0, 0, 0, 0.3); /* Stronger shadow */
        position: relative;
        z-index: 10;
    }
    .info-panel img { 
        width: 200px; 
        margin-bottom: 30px; 
    }
    .info-panel h1 { 
        font-size: 38px; 
        font-weight: 800; 
        margin: 0; 
        letter-spacing: 1px;
    }
    .info-panel p { 
        font-size: 16px; 
        opacity: 0.9; 
        max-width: 400px; 
        line-height: 1.6; 
        margin-top: 15px; 
    }
    /* Logo Link Color Adjustment */
    .form-footer a[href="index.php"] {
        color: var(--accent-cyan) !important; 
    }

    /* -------------------------------------------------------------------------- */
    /* FORM PANEL (RIGHT) */
    /* -------------------------------------------------------------------------- */
    .form-panel { 
        flex: 1; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        background: #fff; 
        padding: 40px; 
    }
    .form-container { 
        width: 100%; 
        max-width: 420px; 
        transition: height 0.3s ease;
    }
    h2 { 
        font-size: 30px; 
        font-weight: 700; 
        color: var(--primary-color); 
        margin-bottom: 30px; 
        text-align: center; 
    }
    
    /* -------------------------------------------------------------------------- */
    /* ROLE SELECTION */
    /* -------------------------------------------------------------------------- */
    #role-selection-step p.instruction {
        font-weight: 500; 
        color: #475569; 
        margin-bottom: 25px; 
        text-align: center;
    }
    .roles { 
        display: grid; 
        grid-template-columns: repeat(3, 1fr); 
        gap: 15px; 
    }
    .role-card input { display: none; }
    .role-content { 
        border: 2px solid var(--border-color); 
        border-radius: 10px; 
        padding: 20px 10px; 
        text-align: center; 
        cursor: pointer; 
        transition: all 0.3s ease-in-out;
        background-color: #ffffff;
        box-shadow: var(--shadow-light); 
    }
    .role-content i { 
        font-size: 30px; 
        margin-bottom: 10px; 
        color: var(--accent-cyan); /* Icon Color: Cyan */
    }
    .role-content span { 
        display: block; 
        font-weight: 600; 
        font-size: 13px;
        color: #1e293b; 
    }
    .role-card:hover .role-content { 
        border-color: var(--accent-cyan); /* Hover Border: Cyan */
        transform: translateY(-3px);
    }
    .role-card input:checked + .role-content { 
        border-color: var(--primary-color); /* Checked Border: Dark Blue */
        background-color: #f0f4f9; 
        transform: translateY(-2px); 
    }
    
    /* -------------------------------------------------------------------------- */
    /* LOGIN FORM ELEMENTS */
    /* -------------------------------------------------------------------------- */
    #login-details-step { 
        opacity: 0; 
        height: 0;
        overflow: hidden;
        transition: opacity 0.4s ease-in-out, height 0.4s ease-in-out, margin-top 0.4s ease-in-out; 
        margin-top: 0px;
    }
    #login-details-step.visible { 
        opacity: 1; 
        height: auto;
        margin-top: 30px;
    }
    .input-group { 
        margin-bottom: 20px; 
        text-align: left; 
        position: relative; 
    }
    .input-group label { 
        font-weight: 600; 
        font-size: 14px; 
        display: block; 
        margin-bottom: 6px; 
        color: #1e293b; 
    }
    .input-group input { 
        width: 100%; 
        padding: 14px 12px; 
        border: 1px solid var(--border-color); 
        border-radius: 8px; 
        box-sizing: border-box; 
        font-size: 16px;
        transition: border-color 0.3s, box-shadow 0.3s;
    }
    .input-group input:focus {
        border-color: var(--accent-green); /* Input Focus: Lime Green */
        box-shadow: 0 0 0 2px rgba(167, 215, 55, 0.3); 
        outline: none;
    }
    .toggle-password { 
        position: absolute; 
        right: 15px; 
        top: 45px; 
        cursor: pointer; 
        color: #94a3b8; 
        transition: color 0.2s;
        font-size: 16px;
    }
    .toggle-password:hover {
        color: var(--accent-cyan); /* Toggle Hover: Cyan */
    }
    .login-btn { 
        background: var(--primary-color); /* Button: Dark Blue */
        color: white; 
        border: none; 
        width: 100%; 
        padding: 15px; 
        border-radius: 8px; 
        font-weight: 700; 
        cursor: pointer; 
        font-size: 17px;
        transition: background-color 0.3s, transform 0.1s;
    }
    .login-btn:hover {
        background-color: var(--accent-cyan); /* Button Hover: Cyan */
        transform: translateY(-1px);
    }
    .login-btn:active {
        transform: translateY(0);
        background-color: #0087a3; /* Darker Cyan on press */
    }
    
    /* Footer & Alerts */
    .form-footer { margin-top: 25px; font-size: 14px; text-align: center; }
    .form-footer a { 
        color: var(--accent-cyan); /* Footer Links: Cyan */
        text-decoration: none; 
        font-weight: 600; 
    }
    .form-footer a:hover { 
        text-decoration: underline; 
        color: var(--primary-color); /* Footer Hover: Dark Blue */
    }
    .alert-message { 
        border-radius: 8px; 
        padding: 12px; 
        margin-bottom: 20px; 
        text-align: center; 
        font-size: 15px; 
        font-weight: 600; 
        transition: opacity 0.5s ease;
        opacity: 1;
    }
    .error-message { color: #842029; background-color: #f8d7da; border: 1px solid #f5c2c7; }
    .success-message { color: #0f5132; background-color: #d1e7dd; border: 1px solid #badbcc; }
    .instruction #role-title {
        color: var(--accent-cyan) !important; /* Ensure role title is Cyan */
    }

    /* -------------------------------------------------------------------------- */
    /* RESPONSIVE DESIGN (MOBILE) */
    /* -------------------------------------------------------------------------- */
    @media (max-width: 850px) {
        .container {
            flex-direction: column; 
            height: auto; 
            min-height: 100vh; 
        }

        /* Info Panel (top) */
        .info-panel {
            flex: none; 
            padding: 30px 20px;
            height: 180px; 
            justify-content: center;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.2); 
        }
        .info-panel img { 
            width: 140px; 
            margin-bottom: 5px;
        }
        .info-panel h1 { 
            font-size: 28px; 
        }
        .info-panel p { 
            display: none; 
        }

        /* Form Panel (bottom) */
        .form-panel {
            flex: none; 
            padding: 30px 20px; 
            min-height: calc(100vh - 180px); 
        }
        
        .roles {
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .role-content {
            padding: 15px 10px; 
        }
        
        /* Step 2 spacing adjustment */
        #login-details-step.visible {
            margin-top: 20px;
        }
        
    }
    /* For very small phones */
    @media (max-width: 450px) {
        .roles {
            grid-template-columns: 1fr;
        }
    }
</style>
</head>
<body>

<div class="container">
    <div class="info-panel">
        <img src="assets/unikl-logo.png" alt="UniKL Logo">
        <h1>UniKL R-ILMS</h1>
        <p>Asset Check Effective. IT Department Asset Management Portal. Please select your role to proceed.</p>
    </div>

    <div class="form-panel">
        <div class="form-container">
            <h2>UniKL R-ILMS Login</h2>
            
            <div id="role-selection-step">
                <p class="instruction">Who are you logging in as?</p>
                <div class="roles">
                    <label class="role-card"><input type="radio" name="role" value="admin" data-title="Admin"><div class="role-content"><i class="fa-solid fa-user-shield"></i><span>Admin</span></div></label>
                    <label class="role-card"><input type="radio" name="role" value="tech" data-title="Technician"><div class="role-content"><i class="fa-solid fa-screwdriver-wrench"></i><span>Technician</span></div></label>
                    <label class="role-card"><input type="radio" name="role" value="user" data-title="User"><div class="role-content"><i class="fa-solid fa-user"></i><span>User/Staff</span></div></label>
                </div>
            </div>

            <div id="login-details-step">
                <p class="instruction" style="font-weight: 700;">Log In as <span id="role-title" style="color: var(--accent-cyan);"></span></p>
                
                <?php if (!empty($errorMessage)): ?>
                    <div class="alert-message error-message"><?= htmlspecialchars($errorMessage); ?></div>
                <?php endif; ?>
                
                <?php if (!empty($successMessage)): ?>
                    <div class="alert-message success-message"><?= htmlspecialchars($successMessage); ?></div>
                <?php endif; ?>

                <form method="POST" action="login_process.php">
                    <input type="hidden" name="role" id="hidden-role-input">
                    
                    <div class="input-group">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" value="<?= htmlspecialchars($login_attempt_email) ?>" required>
                    </div>
                    
                    <div class="input-group">
                        <label for="password">Password</label>
                        <input type="password" name="password" id="password" required>
                        <i class="fa-solid toggle-password" id="togglePassword"></i> </div>
                    
                    <button type="submit" class="login-btn">Log In</button>
                </form>

                <div class="form-footer">
                    <p>Forgot your password? <a href="forgot_password.php">Reset it here</a></p>
                    <p id="signUpLink" style="display: none;">Don't have an account? <a href="signUp.php">Sign Up</a></p>
                </div>
            </div>
            
             <div class="form-footer" id="main-footer" style="margin-top: 15px;">
                 <p><a href="index.php"><i class="fa-solid fa-home me-1"></i> Back to Homepage</a></p>
             </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const alertMessages = document.querySelectorAll('.alert-message');
        const roleRadios = document.querySelectorAll('input[name="role"]');
        const loginDetailsStep = document.getElementById('login-details-step');
        const hiddenRoleInput = document.getElementById('hidden-role-input');
        const roleTitle = document.getElementById('role-title');
        const signUpLink = document.getElementById('signUpLink');
        const mainFooter = document.getElementById('main-footer');
        
        const togglePassword = document.querySelector("#togglePassword");
        const passwordField = document.querySelector("#password");
        
        // --- 1. Alert Message Timeout ---
        if (alertMessages) {
            alertMessages.forEach(function(message) {
                setTimeout(() => {
                    message.style.opacity = '0';
                    setTimeout(() => {
                        message.style.display = 'none';
                    }, 500);
                }, 5000); 
            });
        }
        
        // --- 2. Role Selection Logic ---
        function showLoginDetails(role, title) {
            roleTitle.textContent = title;
            hiddenRoleInput.value = role;
            
            // Show/Hide Sign Up Link
            signUpLink.style.display = (role === 'user') ? 'block' : 'none';
            
            // Show the Login Details Form (with smooth transition)
            loginDetailsStep.classList.add('visible');
            mainFooter.style.display = 'none'; // Hide main footer to avoid clutter
        }

        roleRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                showLoginDetails(this.value, this.dataset.title);
            });
        });
        
        // --- 3. Password Toggle ---
        togglePassword.addEventListener("click", function () {
            // 1. Tukar jenis input
            const isPassword = passwordField.type === "password";
            passwordField.type = isPassword ? "text" : "password";
            
            // 2. Tukar ikon
            this.classList.toggle("fa-eye-slash");
            this.classList.toggle("fa-eye");
        });
        
        // --- 4. Failed Attempt / Pre-fill Logic ---
        const attemptedRole = '<?= $login_attempt_role ?>';
        if (attemptedRole) {
            const radio = document.querySelector(`input[name="role"][value="${attemptedRole}"]`);
            if (radio) {
                // Check the radio button
                radio.checked = true;
                
                // Programmatically trigger the next step
                showLoginDetails(attemptedRole, radio.dataset.title);
            }
        }
    });
</script>

</body>
</html>