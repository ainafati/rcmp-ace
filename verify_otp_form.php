<?php
$email = '';
if (isset($_GET['email'])) {
    $email = htmlspecialchars(urldecode($_GET['email']));
}

// 1. TANGKAP ROLE DARI URL (Dihantar dari forgot_password.php)
$role = isset($_GET['role']) ? htmlspecialchars($_GET['role']) : 'user';

// 2. TENTUKAN LINK BACK TO LOGIN
$back_url = ($role === 'technician') ? 'login_technician.php' : 'login.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Set New Password | NexCheck Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        :root {
            --primary: #002147; 
            --accent: #00A3C9;
            --bg-body: #f8fafc;
            --white: #ffffff;
            --text-muted: #64748b;
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

        body::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image: url('img/view.png'); 
            background-size: cover;
            background-position: center;
            filter: blur(10px) brightness(0.4);
            z-index: -1;
        }

        .main-container {
            display: flex;
            width: 950px;
            max-width: 95%;
            height: 650px;
            background: var(--white);
            border-radius: 40px; 
            overflow: hidden;
            box-shadow: 0 40px 100px -20px rgba(0, 33, 71, 0.4);
        }

        .side-visual {
            flex: 0.8;
            background: var(--primary);
            padding: 50px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .side-visual img { width: 120px; border-radius: 8px; margin-bottom: 25px; }
        .side-visual h1 { font-size: 2.2rem; font-weight: 800; line-height: 1.1; }
        .side-visual h1 span { color: var(--accent); }
        .side-visual p { font-size: 0.9rem; opacity: 0.8; margin-top: 15px; font-weight: 300; }

        .side-form {
            flex: 1.2;
            padding: 40px 60px;
            background: var(--white);
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow-y: auto;
        }

        h2 { font-weight: 800; color: var(--primary); font-size: 1.8rem; margin-bottom: 5px; }
        .sub-text { color: var(--text-muted); font-size: 0.8rem; margin-bottom: 25px; }

        .input-group { margin-bottom: 15px; position: relative; }
        .input-group label { font-size: 0.7rem; font-weight: 700; color: var(--primary); display: block; margin-bottom: 5px; text-transform: uppercase; }
        .input-group input {
            width: 100%;
            padding: 12px 15px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            font-size: 0.9rem;
            background: #f8fafc;
            transition: 0.3s;
        }
        .input-group input:focus { 
            outline: none; border-color: var(--accent); background: white;
            box-shadow: 0 0 0 4px rgba(0, 163, 201, 0.1);
        }

        #otp {
            text-align: center;
            font-size: 1.4rem;
            letter-spacing: 0.5rem;
            font-weight: 800;
            color: var(--primary);
            border: 2px solid #e2e8f0;
        }

        .btn-submit {
            background: var(--primary);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 50px;
            width: 100%;
            font-weight: 700;
            margin-top: 10px;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 10px 20px -5px rgba(0, 33, 71, 0.3);
        }
        .btn-submit:hover:not(:disabled) { background: var(--accent); transform: translateY(-2px); }
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 35px;
            color: var(--text-muted);
            cursor: pointer;
        }

        .resend-box { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        #resendOtpButton { font-size: 0.75rem; font-weight: 700; color: var(--accent); cursor: pointer; border: none; background: none; }
        #resendOtpButton:disabled { color: #cbd5e1; cursor: not-allowed; }

        .alert { padding: 12px; border-radius: 12px; font-size: 0.8rem; margin-top: 20px; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        input[type="password"]::-webkit-reveal,
        input[type="password"]::-ms-reveal { display: none !important; }

        @media (max-width: 768px) {
            .side-visual { display: none; }
            .main-container { height: auto; border-radius: 30px; }
            .side-form { padding: 40px 30px; }
        }
    </style>
</head>
<body>

<div class="main-container">
    <div class="side-visual">
        <img src="img/Logo-UniKL-PCM.jpg" alt="Logo">
        <h1>Secure <span>Reset.</span></h1>
        <p>Complete the verification process to regain access to your NexCheck account.</p>
        <div style="margin-top: 30px; font-size: 0.7rem; opacity: 0.6;">
            <i class="fas fa-shield-alt"></i> Two-Factor Authentication Active
        </div>
    </div>

    <div class="side-form">
        <h2>Verification</h2>
        <p class="sub-text" id="instructionText">We've sent a 6-digit code to your email.</p>

        <form id="resetForm">
            <input type="hidden" name="email" id="hiddenEmail" value="<?= $email ?>">
            <input type="hidden" name="role" value="<?= $role ?>">

            <div class="input-group">
                <label>Verification Code</label>
                <input type="text" name="token" id="otp" placeholder="000000" required maxlength="6">
            </div>

            <div class="resend-box">
                <span id="otpTimerText" class="text-[10px] text-gray-400 font-bold hidden">
                    EXPIRES IN <span id="cooldownDisplay">60s</span>
                </span>
                <button type="button" id="resendOtpButton">RESEND CODE</button>
            </div>

            <div class="input-group">
                <label>New Password</label>
                <input type="password" name="new_password" id="new_password" required minlength="8" placeholder="••••••••">
                <i class="fas fa-eye-slash password-toggle" id="toggleNewPassword"></i>
            </div>

            <div class="input-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" required minlength="8" placeholder="••••••••">
                <i class="fas fa-eye-slash password-toggle" id="toggleConfirmPassword"></i>
            </div>

            <button type="submit" id="resetButton" class="btn-submit">SET NEW PASSWORD</button>
        </form>

        <div id="messageContainer" class="alert hidden">
            <span id="resultText"></span>
        </div>

        <div style="text-align: center; margin-top: 20px;">
            <a href="<?= $back_url ?>" style="font-size: 0.8rem; color: var(--accent); font-weight: 700; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>
</div>

<script>
    // Inisialisasi Elements
    const form = document.getElementById("resetForm");
    const resetButton = document.getElementById("resetButton");
    const messageContainer = document.getElementById('messageContainer');
    const resultText = document.getElementById('resultText');
    const hiddenEmailInput = document.getElementById('hiddenEmail');
    const otpInput = document.getElementById('otp'); 
    const resendOtpButton = document.getElementById('resendOtpButton');
    const cooldownDisplay = document.getElementById('cooldownDisplay');
    const otpTimerText = document.getElementById('otpTimerText');
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const toggleNewPasswordButton = document.getElementById('toggleNewPassword');
    const toggleConfirmPasswordButton = document.getElementById('toggleConfirmPassword');

    const RESEND_API_URL = 'forgot_password_api.php'; 
    const VERIFY_API_URL = 'verify_otp_api.php'; 

    let cooldownSeconds = 0; 
    let isRedirecting = false;
    const role = "<?= $role ?>"; // Role dari PHP ke JS
    const backUrl = "<?= $back_url ?>"; // Back URL dari PHP ke JS

    // Helper: Tunjuk Message
    function displayMessage(isSuccess, message) {
        resultText.textContent = message;
        messageContainer.className = 'alert mt-6'; 
        messageContainer.classList.add(isSuccess ? 'alert-success' : 'alert-error');
        messageContainer.classList.remove('hidden');
    }

    // Timer Cooldown
    function startResendCooldown() {
        cooldownSeconds = 60; 
        resendOtpButton.disabled = true;
        otpTimerText.classList.remove('hidden');
        const timer = setInterval(() => {
            cooldownSeconds--;
            cooldownDisplay.textContent = `${cooldownSeconds}s`;
            if (cooldownSeconds <= 0) {
                clearInterval(timer);
                resendOtpButton.disabled = false;
                otpTimerText.classList.add('hidden');
            }
        }, 1000);
    }

    // Toggle Password
    function setupToggle(btn, input) {
        btn.addEventListener('click', () => {
            const isPass = input.type === 'password';
            input.type = isPass ? 'text' : 'password';
            btn.classList.toggle('fa-eye', isPass);
            btn.classList.toggle('fa-eye-slash', !isPass);
        });
    }
    setupToggle(toggleNewPasswordButton, newPasswordInput);
    setupToggle(toggleConfirmPasswordButton, confirmPasswordInput);

    // Resend OTP logic
    resendOtpButton.addEventListener('click', async () => {
        startResendCooldown();
        const formData = new FormData();
        formData.append('email', hiddenEmailInput.value);
        try {
            const res = await fetch(RESEND_API_URL, { method: 'POST', body: formData });
            const data = await res.json();
            displayMessage(data.success, data.message);
        } catch (e) { displayMessage(false, "Connection error"); }
    });

    // Form Submit
    form.addEventListener("submit", async function(e) {
        e.preventDefault();
        if (newPasswordInput.value !== confirmPasswordInput.value) {
            displayMessage(false, "Passwords do not match.");
            return;
        }

        resetButton.disabled = true;
        resetButton.innerHTML = `<i class="fas fa-circle-notch fa-spin mr-2"></i> UPDATING...`;

        try {
            const response = await fetch(VERIFY_API_URL, { method: 'POST', body: new FormData(form) });
            const data = await response.json();

            if (data.success) {
                isRedirecting = true;
                displayMessage(true, data.message + " Redirecting...");
                setTimeout(() => {
                    // REDIRECT KE LOGIN YANG BETUL BERDASARKAN ROLE
                    window.location.href = backUrl; 
                }, 2000);
            } else {
                displayMessage(false, data.message);
                resetButton.disabled = false;
                resetButton.textContent = "SET NEW PASSWORD";
            }
        } catch (error) {
            displayMessage(false, "System error occurred.");
            resetButton.disabled = false;
            resetButton.textContent = "SET NEW PASSWORD";
        }
    });

    // Start cooldown on load
    document.addEventListener('DOMContentLoaded', () => {
        if (hiddenEmailInput.value) startResendCooldown();
    });
</script>
</body>
</html>