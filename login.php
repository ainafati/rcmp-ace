<?php
session_start();
include 'config.php';


if (isset($_SESSION['user_id'])) { header("Location: user/dashboard_user.php"); exit(); }
if (isset($_SESSION['tech_id'])) { header("Location: technician/dashboard_tech.php"); exit(); }
if (isset($_SESSION['admin_id'])) { header("Location: admin/manageItem_admin.php"); exit(); }


$login_attempt_role = isset($_SESSION['login_attempt_role']) ? $_SESSION['login_attempt_role'] : '';
$login_attempt_email = isset($_SESSION['login_attempt_email']) ? $_SESSION['login_attempt_email'] : '';


unset($_SESSION['login_attempt_role'], $_SESSION['login_attempt_email']);


$errorMessage = isset($_SESSION['error']) ? $_SESSION['error'] : '';
$successMessage = isset($_SESSION['success']) ? $_SESSION['success'] : '';
unset($_SESSION['error'], $_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - NexCheck</title>
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
<style>
    /* -------------------------------------------------------------------------- */
    /* GLOBAL & STRUCTURE (Centered Design) */
    /* -------------------------------------------------------------------------- */
    :root {
        --primary-color: #002147;      /* Dark Blue (Main Button, Text) */
        --accent-cyan: #00A3C9;        /* Light Blue/Cyan (Main Accent) */
        --accent-green: #A7D737;       /* Lime Green (Input Focus) */
        --light-bg: #f5f8ff;           /* Light background */
        --border-color: #e2e8f0;
        --shadow-strong: 0 10px 30px rgba(0, 0, 0, 0.15); /* Stronger shadow for card */
    }

    body, html { 
        margin: 0; 
        padding: 0; 
        font-family: 'Inter', sans-serif; 
        height: 100%; 
        background-color: var(--light-bg); 
        display: flex; /* Use flex to center content */
        align-items: center;
        justify-content: center;
        min-height: 100vh;
    }
    .form-wrapper { 
        max-width: 450px; /* Max width of login card */
        width: 90%; 
        background: #fff; 
        padding: 40px; 
        border-radius: 12px; 
        box-shadow: var(--shadow-strong);
        transition: transform 0.3s ease-in-out;
    }
    
    /* Header Branding */
    .header-branding {
        text-align: center;
        margin-bottom: 30px;
    }
    .header-branding img {
        width: 120px; 
        margin-bottom: 10px;
    }
    .header-branding h1 {
        font-size: 26px;
        font-weight: 800;
        color: var(--primary-color);
        margin: 0;
        letter-spacing: 0.5px;
    }
    .header-branding p {
        font-size: 14px;
        color: #64748b;
        margin-top: 5px;
    }

    /* Form Title */
    h2 { 
        font-size: 24px; 
        font-weight: 700; 
        color: var(--primary-color); 
        margin-bottom: 25px; 
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
        padding: 15px 10px; 
        text-align: center; 
        cursor: pointer; 
        transition: all 0.3s ease-in-out;
        background-color: #ffffff;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05); /* Lighter shadow */
    }
    .role-content i { 
        font-size: 24px; 
        margin-bottom: 8px; 
        color: var(--accent-cyan); /* Icon Color: Cyan */
    }
    .role-content span { 
        display: block; 
        font-weight: 600; 
        font-size: 13px;
        color: #1e293b; 
    }
    .role-card:hover .role-content { 
        border-color: var(--accent-cyan); 
        transform: translateY(-2px);
    }
    .role-card input:checked + .role-content { 
        border-color: var(--primary-color); 
        background-color: #f0f4f9; 
        transform: translateY(-1px); 
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
        margin-top: 25px; /* Reduce top margin */
    }
    .input-group { 
        margin-bottom: 18px; 
        text-align: left; 
        position: relative; 
        z-index: 10; 
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
        padding: 12px; 
        border: 1px solid var(--border-color); 
        border-radius: 8px; 
        box-sizing: border-box; 
        font-size: 16px;
        transition: border-color 0.3s, box-shadow 0.3s;
        /* Padding lebih besar untuk ikon toggle */
        padding-right: 50px; 
    }
    
    /* ********** PEMBETULAN UTAMA: HILANGKAN IKON LALAI PELAYAR ********** */
    /* Sembunyikan ikon Reveal Password lalai dari Chrome/Edge/Safari/IE/Edge */
    input[type=password]::-ms-reveal,
    input[type=password]::-ms-clear {
        display: none !important;
        width: 0 !important;
        height: 0 !important;
    }
    input[type=password]::-webkit-contacts-auto-fill-button,
    input[type=password]::-webkit-credentials-auto-fill-button {
        visibility: hidden;
        pointer-events: none;
        position: absolute;
        right: 0;
    }
    /* ********************************************************** */

    .input-group input:focus {
        border-color: var(--accent-green);
        box-shadow: 0 0 0 2px rgba(167, 215, 55, 0.3); 
        outline: none;
    }
    .toggle-password { 
        position: absolute; 
        right: 8px; 
        top: 50%;
        transform: translateY(-50%);
        margin-top: 14px; /* Approx. half the label height + margin */
        cursor: pointer; 
        color: #94a3b8; 
        font-size: 16px; 
        /* Pastikan ikon anda berada di atas segalanya */
        z-index: 100; 
    }
    /* Fix 'top' position if no label */
    .input-group input:placeholder-shown + .toggle-password {
        top: 50%;
        margin-top: 0;
    }
    .toggle-password:hover { color: var(--accent-cyan); }
    
    .login-btn { 
        background: var(--primary-color); 
        color: white; border: none; width: 100%; padding: 13px; 
        border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 16px;
        transition: background-color 0.3s, transform 0.1s;
        margin-top: 5px;
    }
    .login-btn:hover { background-color: var(--accent-cyan); transform: translateY(-1px); }
    .login-btn:active { transform: translateY(0); background-color: #0087a3; }
    
    /* Footer & Alerts */
    .form-footer { margin-top: 20px; font-size: 14px; text-align: center; }
    .form-footer a { color: var(--accent-cyan); text-decoration: none; font-weight: 600; }
    .form-footer a:hover { text-decoration: underline; color: var(--primary-color); }
    
    /* -------------------------------------------------------------------------- */
    /* ALERT MESSAGES (IMPROVEMENT) */
    /* -------------------------------------------------------------------------- */
    .alert-message {
        border-radius: 8px;
        padding: 12px 15px;
        margin-bottom: 20px;
        font-size: 14px;
        font-weight: 500;
        border: 1px solid transparent;
        opacity: 1;
        transition: opacity 0.5s ease-out;
    }

    .error-message {
        color: #721c24; 
        background-color: #f8d7da; 
        border-color: #f5c6cb; 
    }

    .success-message {
        color: #155724; 
        background-color: #d4edda; 
        border-color: #c3e6cb; 
    }
    
    .instruction #role-title { color: var(--accent-cyan) !important; }

    /* -------------------------------------------------------------------------- */
    /* RESPONSIVE DESIGN (MOBILE) */
    /* -------------------------------------------------------------------------- */
    @media (max-width: 550px) {
        .form-wrapper {
            width: 100%;
            max-width: none;
            padding: 30px 20px;
            box-shadow: none; 
            border-radius: 0;
            min-height: 100vh;
        }
        .roles {
            grid-template-columns: 1fr; 
            gap: 10px;
        }
        .header-branding {
            margin-top: 15px;
        }
        /* Adjust toggle icon position on mobile if needed */
        .toggle-password {
            margin-top: 13px;
        }
    }
</style></head>
<body>

<div class="form-wrapper">
    <div class="header-branding">
        <img src="assets/unikl-logo.png" alt="UniKL Logo">
        <h1>RCMP NexCheck</h1>
        <p>IT Department Inventory Management Portal.</p>
    </div>

    <div class="form-container">
        <h2>System Login</h2>
        
        <div id="role-selection-step">
            <p class="instruction">Please select your role to proceed:</p>
            <div class="roles">
                <label class="role-card"><input type="radio" name="role" value="admin" data-title="Admin"><div class="role-content"><i class="fa-solid fa-user-shield"></i><span>Admin</span></div></label>
                <label class="role-card"><input type="radio" name="role" value="tech" data-title="Technician"><div class="role-content"><i class="fa-solid fa-screwdriver-wrench"></i><span>Technician</span></div></label>
                <label class="role-card"><input type="radio" name="role" value="user" data-title="User"><div class="role-content"><i class="fa-solid fa-user"></i><span>User/Staff</span></div></label>
            </div>
        </div>

        <div id="login-details-step">
            <p class="instruction" style="font-weight: 700;">Log In as <span id="role-title"></span></p>
            
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
                    <i class="fa-solid toggle-password" id="togglePassword"></i>
                </div>
                
                <button type="submit" class="login-btn">Log In</button>
            </form>

            <div class="form-footer">
                <p>Forgot your password? <a href="forgot_password.php">Reset it here</a></p>
                <p id="signUpLink" style="display: none;">Don't have an account? <a href="signUp.php">Sign Up</a></p>
            </div>
        </div>
        
        <div class="form-footer" style="margin-top: 30px;">
            <p><a href="index.php"><i class="fa-solid fa-home me-1"></i> Back to Homepage</a></p>
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
        const togglePassword = document.querySelector("#togglePassword");
        const passwordField = document.querySelector("#password");
        
        
        
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
        
        
        
        function showLoginDetails(role, title) {
            roleTitle.textContent = title;
            hiddenRoleInput.value = role;
            
            
            signUpLink.style.display = (role === 'user') ? 'block' : 'none';
            
            
            document.getElementById('role-selection-step').style.display = 'none'; 
            
            
            loginDetailsStep.classList.add('visible');
            
            
            const emailInput = document.getElementById('email');
            if (emailInput.value === '') {
                emailInput.focus();
            } else {
                
                passwordField.focus();
            }
        }

        roleRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                showLoginDetails(this.value, this.dataset.title);
            });
        });
        
        
        
        
        
        if (togglePassword && passwordField) {
            
            
            
            togglePassword.classList.add("fa-eye-slash"); 
            
            togglePassword.addEventListener("click", function () {
                
                const isPassword = passwordField.type === "password";
                
                if (isPassword) {
                    
                    passwordField.type = "text";
                    
                    this.classList.remove("fa-eye-slash");
                    this.classList.add("fa-eye");
                } else {
                    
                    passwordField.type = "password";
                    
                    this.classList.remove("fa-eye");
                    this.classList.add("fa-eye-slash");
                }
            });
        }
        
        
        
        
        const attemptedRole = '<?= $login_attempt_role ?>';
        if (attemptedRole) {
            const radio = document.querySelector(`input[name="role"][value="${attemptedRole}"]`);
            if (radio) {
                
                radio.checked = true;
                
                
                setTimeout(() => {
                    showLoginDetails(attemptedRole, radio.dataset.title);
                }, 100); 
            }
        }
    });
</script>

</body>
</html>