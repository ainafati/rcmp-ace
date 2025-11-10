<?php
session_start();

include 'config.php'; 


if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $ic_num = $_POST['ic_num'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password']; 
    $phoneNum = $_POST['phoneNum'];
    $status = 'active';

    
    $allowed_domains = ['@unikl.edu.my', '@t.unikl.edu.my'];
    $is_valid_domain = false;
    $lower_email = strtolower($email);

    foreach ($allowed_domains as $domain) {
        
        if (substr($lower_email, -strlen($domain)) === $domain) {
             $is_valid_domain = true;
             break;
        }
    }

    if (!$is_valid_domain) { 
        $_SESSION['error'] = "Only **UniKL official email** (@unikl.edu.my or @t.unikl.edu.my) addresses are allowed for sign up.";
        header("Location: signUp.php");
        exit();
    }

    
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match!";
        header("Location: signUp.php");
        exit();
    }

    $uppercase = preg_match('@[A-Z]@', $password);
    $lowercase = preg_match('@[a-z]@', $password);
    $number    = preg_match('@[0-9]@', $password);
    $specialChars = preg_match('@[\W_]@', $password);

    if (!$uppercase || !$lowercase || !$number || !$specialChars || strlen($password) < 8) {
        $_SESSION['error'] = 'Password does not meet the requirements. Please ensure it has 8+ characters, uppercase, lowercase, number, and a special character.';
        header("Location: signUp.php");
        exit();
    }

    
    
    
    $sql_check = "
        (SELECT 'user' AS role, email, ic_num FROM user WHERE email = ? OR ic_num = ?)
        UNION ALL
        (SELECT 'admin' AS role, email, ic_num FROM admin WHERE email = ? OR ic_num = ?)
        UNION ALL
        (SELECT 'technician' AS role, email, ic_num FROM technician WHERE email = ? OR ic_num = ?)
    ";

    $stmt = $conn->prepare($sql_check);
    
    if (!$stmt) {
        $_SESSION['error'] = "Database error (Prepare failed: " . $conn->error . ")";
        header("Location: signUp.php");
        exit();
    }
    
    
    $stmt->bind_param("ssssss", $email, $ic_num, $email, $ic_num, $email, $ic_num);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        
        $role_found = strtoupper($row['role']);
        
        if ($row['email'] === $email) {
            $_SESSION['error'] = "Email is already registered as **" . $role_found . "**.";
        } else if ($row['ic_num'] === $ic_num) {
            $_SESSION['error'] = "IC Number is already registered as **" . $role_found . "**.";
        }
        
        $stmt->close();
        header("Location: signUp.php");
        exit();
    }
    $stmt->close();

    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO user (name, email, ic_num, password, phoneNum, status) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $_SESSION['error'] = "Database error (Prepare failed: " . $conn->error . ")";
        header("Location: signUp.php");
        exit();
    }
    
    $stmt->bind_param("ssssss", $name, $email, $ic_num, $hashed_password, $phoneNum, $status);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Account created successfully. Please log in.";
        
        $_SESSION['login_attempt_role'] = 'user'; 
        $_SESSION['login_attempt_email'] = $email; 
        header("Location: login.php");
        exit();
    } else {
        $_SESSION['error'] = "Something went wrong. Please try again. Database error: " . $conn->error;
        header("Location: signUp.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up - R-ILMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    
    <link rel="stylesheet" href="https:
    <link href="https:
    <style>
        /* -------------------------------------------------------------------------- */
        /* GLOBAL & STRUCTURE - Fokus pada reka bentuk pusat (Single Panel) */
        /* -------------------------------------------------------------------------- */
        :root {
            --primary-color: #00285a; /* Dark Navy Blue (UniKL) */
            --secondary-color: #005a9c; /* Lighter Blue */
            --light-bg: #f0f4f8; /* Very light blue/gray background */
            --border-color: #cbd5e1;
            --success-color: #22c55e;
            --error-color: #ef4444;
        }

        body, html { 
            margin: 0; 
            padding: 0; 
            font-family: 'Inter', sans-serif; 
            height: 100%; 
            background-color: var(--light-bg); 
            display: flex;
            align-items: center; /* Pusat menegak */
            justify-content: center; /* Pusat mendatar */
            min-height: 100vh; /* Pastikan ia meliputi seluruh viewport */
        }
        
        /* CONTAINER UTAMA (MODEN, SATU PANEL) - MENGGANTIKAN .container DAN .form-panel */
        .auth-container { 
            width: 100%; 
            max-width: 480px; /* Lebar maksimum untuk borang */
            padding: 40px;
            margin: 20px auto;
            background: #fff; 
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 40, 90, 0.15); /* Soft, professional shadow */
        }

        /* HEADER */
        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .auth-header h2 { 
            font-size: 32px; 
            font-weight: 800; 
            color: var(--primary-color); 
            margin-bottom: 5px; 
            letter-spacing: 0.5px;
        }
        .auth-header p {
            font-size: 15px;
            color: #64748b;
        }
        
        /* FORM ELEMENTS */
        .input-group { margin-bottom: 20px; text-align: left; position: relative; }
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
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(0, 90, 156, 0.15);
            outline: none;
        }
        
        /* BUTTON */
        .submit-btn { 
            background: var(--primary-color); 
            color: white; 
            border: none; 
            width: 100%; 
            padding: 15px; 
            border-radius: 8px; 
            font-weight: 700; 
            cursor: pointer; 
            font-size: 17px;
            transition: background-color 0.3s;
        }
        .submit-btn:hover:not(:disabled) {
            background-color: #003a73;
        }
        .submit-btn:disabled { 
            background: #cbd5e1; 
            cursor: not-allowed; 
        }
        
        /* FOOTER & ALERTS */
        .auth-footer { margin-top: 25px; font-size: 15px; text-align: center; }
        .auth-footer a { color: var(--secondary-color); text-decoration: none; font-weight: 600; }
        .auth-footer a:hover { text-decoration: underline; }
        .alert-danger { 
            background-color: #fef2f2; /* Light Red */
            color: var(--error-color); 
            padding: 12px; 
            border: 1px solid var(--error-color); 
            border-radius: 8px; 
            margin-bottom: 20px;
            font-size: 15px;
            font-weight: 500;
        }
        
.form-row {
    display: flex; /* Aktifkan Flexbox */
    gap: 20px; /* Jarak antara kolum */
    margin-bottom: 0; /* Alihkan margin-bottom ke .input-group di dalam .form-row */
}

.form-row .input-group {
    flex: 1; /* Pastikan setiap input-group mengambil ruang yang sama */
    margin-bottom: 20px; /* Kembalikan margin-bottom di sini */
}

/* Ubah susunan Password Requirements (agar ia menjadi satu kolum di bawah) */
#password-requirements { 
            list-style-type: none; 
            padding: 0; 
            font-size: 13px; 
            color: #64748b; 
            margin-top: -10px; 
            margin-bottom: 25px; 
    display: grid;
    grid-template-columns: 1fr 1fr; /* TETAPKAN KEPADA DUA KOLUM UNTUK DESKTOP */
    gap: 5px 15px; /* Tambah jarak mendatar */
}
        #password-requirements li { 
            margin-bottom: 0; 
            transition: color 0.3s;
        }
        #password-requirements li.valid { 
            color: var(--success-color); 
            font-weight: 500;
        }
        #password-requirements li i { 
            width: 18px; 
            margin-right: 3px;
            color: var(--error-color); 
        }
        #password-requirements li.valid i { 
            color: var(--success-color); 
        }
        .password-match-error { 
            color: var(--error-color); 
            font-size: 13px; 
            margin-top: 5px; 
            display: none; 
            font-weight: 500;
        }

        /* -------------------------------------------------------------------------- */
        /* RESPONSIVE DESIGN (MOBILE) */
        /* -------------------------------------------------------------------------- */
        @media (max-width: 600px) {
            body {
                align-items: flex-start; /* Alihkan ke atas pada mobile */
                padding: 20px;
            }
            .auth-container {
                box-shadow: none; /* Buang shadow pada mobile */
                border-radius: 0;
                padding: 0;
                margin: 0 auto;
                background: var(--light-bg); /* Jadikan background borang sama dengan body pada mobile */
            }
            .auth-header h2 {
                font-size: 28px;
            }
            #password-requirements {
                grid-template-columns: 1fr; /* 1 kolum pada mobile */
            }
        }
    </style>
</head>
<body>

<div class="auth-container">
    <div class="auth-header">
        <h2><i class="fa-solid fa-user-plus me-2"></i> Create Account</h2>
        <p>RCMP Inventory Reservation Check System</p>
    </div>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['success']) && basename($_SERVER['PHP_SELF']) == 'signUp.php'): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>


<form method="POST" action="signUp.php" id="signupForm">
    <div class="form-row">
        <div class="input-group">
            <label for="name">Full Name</label>
            <input type="text" name="name" id="name" required 
                   value="<?= htmlspecialchars(isset($_POST['name']) ? $_POST['name'] : '') ?>">
        </div>
        
        <div class="input-group">
            <label for="ic_num">IC Number (12 Digits)</label>
            <input type="text" name="ic_num" id="ic_num" required 
                    pattern="[0-9]{12}" 
                    title="IC Number must be 12 digits (e.g., 900101015001)"
                    value="<?= htmlspecialchars(isset($_POST['ic_num']) ? $_POST['ic_num'] : '') ?>"> 
        </div>
    </div>
    <div class="form-row">
        <div class="input-group">
            <label for="email">UniKL Email</label>
            <input type="email" name="email" id="email" required placeholder="username@unikl.edu.my"
                    value="<?= htmlspecialchars(isset($_POST['email']) ? $_POST['email'] : '') ?>">
        </div>
        <div class="input-group">
            <label for="phoneNum">Phone Number</label>
            <input type="text" name="phoneNum" id="phoneNum" required placeholder="01X-XXXXXXX"
                    value="<?= htmlspecialchars(isset($_POST['phoneNum']) ? $_POST['phoneNum'] : '') ?>">
        </div>
    </div>
    <div class="input-group">
        <label for="password">Password</label>
        <input type="password" name="password" id="password" required>
    </div>
    
    <ul id="password-requirements">
        <li id="length"><i class="fa-solid fa-times"></i> 8+ characters</li>
        <li id="lowercase"><i class="fa-solid fa-times"></i> Lowercase letter</li>
        <li id="uppercase"><i class="fa-solid fa-times"></i> Uppercase letter</li>
        <li id="number"><i class="fa-solid fa-times"></i> A number</li>
        <li id="special"><i class="fa-solid fa-times"></i> Special character</li>
    </ul>

    <div class="input-group">
        <label for="confirm_password">Confirm Password</label>
        <input type="password" name="confirm_password" id="confirm_password" required>
        <div id="password-match-error" class="password-match-error">Passwords do not match.</div>
    </div>

    <button type="submit" class="submit-btn" id="submitBtn" disabled>Create Account</button>
</form>

    <div class="auth-footer">
        <p>Already have an account? <a href="login.php">Login here</a></p>
    </div>
</div>

<script>
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const submitBtn = document.getElementById('submitBtn');
    const matchError = document.getElementById('password-match-error');
    
    
    const reqs = {
        length: { el: document.getElementById('length'), valid: false, regex: /.{8,}/ },
        lowercase: { el: document.getElementById('lowercase'), valid: false, regex: /[a-z]/ },
        uppercase: { el: document.getElementById('uppercase'), valid: false, regex: /[A-Z]/ },
        number: { el: document.getElementById('number'), valid: false, regex: /[0-9]/ },
        special: { el: document.getElementById('special'), valid: false, regex: /[\W_]/ }
    };

    function validatePassword() {
        const pass = passwordInput.value;
        let allValid = true;

        for (const key in reqs) {
            const req = reqs[key];
            if (req.regex.test(pass)) {
                req.el.classList.add('valid');
                req.el.querySelector('i').classList.replace('fa-times', 'fa-check');
                req.valid = true;
            } else {
                req.el.classList.remove('valid');
                req.el.querySelector('i').classList.replace('fa-check', 'fa-times');
                req.valid = false;
                allValid = false;
            }
        }
        return allValid;
    }

    function validateConfirmPassword() {
        const isPasswordSet = passwordInput.value.length > 0;
        const passwordsMatch = isPasswordSet && (passwordInput.value === confirmPasswordInput.value);
        
        if (confirmPasswordInput.value === '') {
            matchError.style.display = 'none';
        } else if (!passwordsMatch) {
             matchError.style.display = 'block';
        } else {
            matchError.style.display = 'none';
        }
        return passwordsMatch;
    }

    function checkFormValidity() {
        const isPasswordStrong = validatePassword();
        const doPasswordsMatch = validateConfirmPassword();
        
        
        const form = document.getElementById('signupForm');
        
        
        const isFormFilled = form.checkValidity();

        if (isPasswordStrong && doPasswordsMatch && isFormFilled) {
            submitBtn.disabled = false;
        } else {
            submitBtn.disabled = true;
        }
    }

    
    passwordInput.addEventListener('input', checkFormValidity);
    confirmPasswordInput.addEventListener('input', checkFormValidity);
    document.getElementById('name').addEventListener('input', checkFormValidity);
    document.getElementById('ic_num').addEventListener('input', checkFormValidity);
    document.getElementById('email').addEventListener('input', checkFormValidity);
    document.getElementById('phoneNum').addEventListener('input', checkFormValidity);
    
    
    document.addEventListener('DOMContentLoaded', checkFormValidity);

</script>

</body>
</html>