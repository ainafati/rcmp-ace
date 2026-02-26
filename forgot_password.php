<?php
// 1. Kesan role dari URL (contoh: forgot_password.php?role=technician)
// Jika tiada role dalam URL, kita anggap dia 'user' biasa.
$role = isset($_GET['role']) ? $_GET['role'] : 'user';

// 2. Tentukan link "Back" berdasarkan role
$back_url = ($role === 'technician') ? 'login_technician.php' : 'login.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password | NexCheck RCMP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>

<style>
    :root {
        --primary: #002147;
        --accent: #00A3C9;
        --bg-body: #f8fafc;
        --text-muted: #64748b;
        --white: #ffffff;
    }

    /* Reset & Base */
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: 'Poppins', sans-serif;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: var(--primary);
        position: relative;
        padding: 20px;
    }

    body::before {
        content: "";
        position: fixed; /* Guna fixed supaya background penuh walau scroll */
        inset: 0;
        background-image: url('img/view.png'); 
        background-size: cover;
        background-position: center;
        filter: blur(8px) brightness(0.4);
        z-index: -1;
    }

    .main-container {
        display: flex;
        width: 900px;
        max-width: 100%;
        min-height: 550px;
        background: var(--white);
        border-radius: 40px; 
        overflow: hidden;
        box-shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.4);
        z-index: 10;
    }

    .side-visual {
        flex: 1;
        background: var(--primary);
        padding: 50px;
        color: white;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .side-visual img { height: 45px; width: auto; margin-bottom: 20px;}
    .side-visual h1 { font-size: 2rem; font-weight: 800; line-height: 1.1; }
    .side-visual h1 span { color: var(--accent); }
    .side-visual p { font-size: 0.9rem; opacity: 0.8; margin-top: 10px; }

    .side-form {
        flex: 1.2;
        padding: 50px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        background: white;
    }

    h2 { font-weight: 800; color: var(--primary); font-size: 1.8rem; margin-bottom: 10px; }
    .sub-text { color: var(--text-muted); font-size: 0.85rem; margin-bottom: 30px; }

    .input-group { margin-bottom: 20px; }
    .input-group label { font-size: 0.75rem; font-weight: 700; color: var(--primary); display: block; margin-bottom: 8px; text-transform: uppercase; }
    .input-group input {
        width: 100%;
        padding: 14px 16px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        transition: 0.3s;
        font-size: 0.9rem;
    }
    .input-group input:focus { 
        outline: none; border-color: var(--accent); background: white;
        box-shadow: 0 0 0 4px rgba(0, 163, 201, 0.1);
    }

    .btn-submit {
        background: var(--primary);
        color: white;
        padding: 16px;
        border-radius: 50px;
        width: 100%;
        font-weight: 700;
        cursor: pointer;
        transition: 0.3s;
        border: none;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .btn-submit:hover { background: var(--accent); transform: translateY(-2px); }

    .back-btn {
        color: var(--accent);
        text-decoration: none;
        font-weight: 700;
        font-size: 0.8rem;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        margin-top: 25px;
    }

    /* MOBILE VIEW - FIX UNTUK RESET ACCESS */
    @media (max-width: 768px) {
        body {
            overflow-y: auto;
            align-items: flex-start; /* Supaya tak terpotong kalau skrin pendek */
        }

        .main-container {
            flex-direction: column; /* Susun menegak */
            height: auto;
            border-radius: 30px;
            margin: auto;
        }

        .side-visual {
            display: flex; /* AKTIFKAN BALIK */
            padding: 40px 30px;
            text-align: center;
            order: 1; /* Biru kat atas */
        }

        .side-visual img { margin: 0 auto 15px auto; height: 35px; }
        .side-visual h1 { font-size: 1.6rem; }
        .side-visual p { font-size: 0.8rem; }
        .side-visual div:last-child { display: none; } /* Sorok 'Encryption Active' kat mobile supaya jimat ruang */

        .side-form {
            padding: 40px 25px;
            order: 2; /* Form kat bawah */
            text-align: center;
        }

        h2 { font-size: 1.4rem; }
        .sub-text { font-size: 0.8rem; margin-bottom: 20px; }
        
        .input-group label { text-align: left; }
    }
</style>
</head>
<body>

<div class="main-container">
    <div class="side-visual">
        <div>
            <img src="img/Logo-UniKL-PCM.jpg" alt="Logo">
            <h1>Reset <span>Access.</span></h1>
            <p>Secure protocol to recover your RCMP NexCheck credentials.</p>
        </div>
        <div style="font-size: 0.75rem; opacity: 0.6;"><i class="fas fa-lock"></i> Encryption Active</div>
    </div>

    <div class="side-form">
        <h2>Forgot Password?</h2>
        <p class="sub-text">Enter your email to receive a secure OTP code.</p>

        <div id="messageContainer" class="alert hidden">
            <span id="resultText"></span>
        </div>

        <form id="forgotForm">
            <div class="input-group">
                <label>UniKL Email Address</label>
                <input type="email" name="email" id="email" placeholder="name@unikl.edu.my" required>
            </div>

            <button type="submit" id="submitButton" class="btn-submit">SEND VERIFICATION CODE</button>
        </form>

        <div style="text-align: center;">
            <a href="<?php echo $back_url; ?>" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>
</div>

<script>
    const form = document.getElementById("forgotForm");
    const submitButton = document.getElementById("submitButton");
    const messageContainer = document.getElementById('messageContainer');
    const resultText = document.getElementById('resultText');

    form.addEventListener("submit", async function(e) {
        e.preventDefault();
        
        submitButton.disabled = true;
        submitButton.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i> SENDING...`;
        
        const formData = new FormData(this);
        const email = formData.get('email');
        const role = "<?php echo $role; ?>"; // Ambil role dari PHP ke JS

        try {
            const res = await fetch("forgot_password_api.php", {
                method: "POST",
                body: new URLSearchParams(formData)
            });

            const data = await res.json();
            
            resultText.textContent = data.message;
            messageContainer.className = `alert ${data.success ? 'alert-success' : 'alert-error'}`;
            messageContainer.classList.remove('hidden');

            if (data.success) {
                setTimeout(() => {
                    // SINI PENTING: Kita bawa email DAN role ke page Verify OTP
                    window.location.href = `verify_otp_form.php?email=${encodeURIComponent(email)}&role=${role}`;
                }, 2000);
            } else {
                submitButton.disabled = false;
                submitButton.innerHTML = "SEND VERIFICATION CODE";
            }
        } catch (error) {
            submitButton.disabled = false;
            submitButton.innerHTML = "SEND VERIFICATION CODE";
        }
    });
</script>
</body>
</html>