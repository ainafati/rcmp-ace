<?php
session_start();
include 'config.php';

// Check database connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// If form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $ic_num = $_POST['ic_num'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password']; 
    $phoneNum = $_POST['phoneNum'];
    $status = 'active';

    // 1. EMAIL DOMAIN VALIDATION
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

    // 2. PASSWORD VALIDATION
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

    // 3. DUPLICATION CHECK (EMAIL OR IC NUMBER)
    $stmt = $conn->prepare("SELECT email, ic_num FROM user WHERE email = ? OR ic_num = ?");
    
    if (!$stmt) {
        $_SESSION['error'] = "Database error (Prepare failed: " . $conn->error . ")";
        header("Location: signUp.php");
        exit();
    }
    
    $stmt->bind_param("ss", $email, $ic_num); 
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['email'] === $email) {
            $_SESSION['error'] = "Email is already registered!";
        } else if ($row['ic_num'] === $ic_num) {
            $_SESSION['error'] = "IC Number is already registered!";
        }
        $stmt->close();
        header("Location: signUp.php");
        exit();
    }
    $stmt->close();

    // 4. INSERT NEW USER INTO DATABASE
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
    <title>Sign Up - UniKL A.C.E.</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* -------------------------------------------------------------------------- */
        /* GLOBAL & STRUCTURE - ALIGNED WITH LOGIN PAGE */
        /* -------------------------------------------------------------------------- */
        :root {
            --primary-color: #00285a; /* Dark Navy Blue (UniKL) */
            --secondary-color: #005a9c; /* Lighter Blue */
            --light-bg: #f5f8ff; /* Very light blue/gray background */
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
        }
        .container { 
            display: flex; 
            height: 100%; 
            width: 100%; 
        }
        
        /* INFO PANEL (LEFT) */
        .info-panel { 
            flex: 1; 
            background: var(--primary-color); 
            color: white; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center; 
            padding: 40px; 
            text-align: center; 
            box-shadow: inset -5px 0 10px rgba(0, 0, 0, 0.2); 
        }
        .info-panel img { 
             /* Guna imej logo UniKL di sini jika ada */
            width: 150px; 
            margin-bottom: 20px; 
            filter: drop-shadow(0 0 5px rgba(255, 255, 255, 0.5));
        }
        .info-panel h1 { font-size: 30px; font-weight: 800; margin: 0; letter-spacing: 0.5px; }
        .info-panel p { font-size: 16px; opacity: 0.9; max-width: 350px; line-height: 1.6; margin-top: 15px;}

        /* FORM PANEL (RIGHT) */
        .form-panel { 
            flex: 1; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            background: #fff; 
            padding: 40px; 
            overflow-y: auto; /* Membenarkan tatal jika form terlalu panjang */
        }
        .form-container { 
            width: 100%; 
            max-width: 450px; /* Aligned with login max-width */
        }
        h2 { 
            font-size: 28px; 
            font-weight: 700; 
            color: var(--primary-color); 
            margin-bottom: 25px; 
            text-align: center; 
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
            transition: border-color 0.3s;
        }
        .input-group input:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 2px rgba(0, 90, 156, 0.2);
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
        .form-footer { margin-top: 25px; font-size: 15px; text-align: center; }
        .form-footer a { color: var(--secondary-color); text-decoration: none; font-weight: 600; }
        .form-footer a:hover { text-decoration: underline; }
        .alert-danger { 
            background-color: #f8d7da; 
            color: #842029; 
            padding: 12px; 
            border: 1px solid #f5c2c7; 
            border-radius: 8px; 
            margin-bottom: 20px;
            font-size: 15px;
            font-weight: 500;
        }
        
        /* PASSWORD REQUIREMENTS LIST */
        #password-requirements { 
            list-style-type: none; 
            padding: 0; 
            font-size: 13px; 
            color: #64748b; 
            margin-top: -10px; /* Naikkan sedikit dari input atas */
            margin-bottom: 25px; 
        }
        #password-requirements li { 
            margin-bottom: 5px; 
            transition: color 0.3s;
        }
        #password-requirements li.valid { 
            color: var(--success-color); 
            font-weight: 500;
        }
        #password-requirements li i { 
            width: 20px; 
            margin-right: 5px;
            color: var(--error-color); /* Ikon silang merah lalai */
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
        @media (max-width: 850px) {
            .container {
                flex-direction: column; 
                height: auto; 
                min-height: 100vh; 
            }

            /* Panel Maklumat (atas) */
            .info-panel {
                flex: none; 
                padding: 30px 20px;
                height: 150px; /* Lebih kecil daripada login page kerana tiada logo besar */
                justify-content: center;
            }
            .info-panel h1 { 
                font-size: 24px; 
            }
            .info-panel p { 
                font-size: 14px;
            }

            /* Panel Borang (bawah) */
            .form-panel {
                flex: none; 
                padding: 30px 20px; 
                min-height: calc(100vh - 150px); 
            }
            
            .form-container {
                max-width: 100%; 
            }
            h2 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="info-panel">
        <h1>Create Your UniKL A.C.E. Account</h1>
        <p>Join the UniKL Equipment Loaning System to easily borrow and manage technical assets.</p>
    </div>

    <div class="form-panel">
        <div class="form-container">
            <h2>Sign Up</h2>
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="signup.php" id="signupForm">
                <div class="input-group">
                    <label for="name">Full Name</label>
                    <input type="text" name="name" id="name" required>
                </div>
                
                <div class="input-group">
                    <label for="ic_num">IC Number (12 Digits)</label>
                    <input type="text" name="ic_num" id="ic_num" required 
                            pattern="[0-9]{12}" 
                            title="IC Number must be 12 digits (e.g., 900101015001)"> 
                </div>

                <div class="input-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" required placeholder="username@unikl.edu.my">
                </div>
                <div class="input-group">
                    <label for="phoneNum">Phone Number</label>
                    <input type="text" name="phoneNum" id="phoneNum" required placeholder="01X-XXXXXXX">
                </div>
                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" required>
                </div>
                <ul id="password-requirements">
                    <li id="length"><i class="fa-solid fa-times"></i> At least 8 characters</li>
                    <li id="lowercase"><i class="fa-solid fa-times"></i> A lowercase letter</li>
                    <li id="uppercase"><i class="fa-solid fa-times"></i> An uppercase letter</li>
                    <li id="number"><i class="fa-solid fa-times"></i> A number</li>
                    <li id="special"><i class="fa-solid fa-times"></i> A special character</li>
                </ul>
                <div class="input-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" required>
                    <div id="password-match-error" class="password-match-error">Passwords do not match.</div>
                </div>
                <button type="submit" class="submit-btn" id="submitBtn" disabled>Create Account</button>
            </form>

            <div class="form-footer">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
</div>

<script>
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const submitBtn = document.getElementById('submitBtn');
    const matchError = document.getElementById('password-match-error');
    
    // Requirements object (Sama seperti kod asal anda)
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
        const passwordsMatch = passwordInput.value === confirmPasswordInput.value && passwordInput.value !== '';
        if (passwordsMatch) {
            matchError.style.display = 'none';
        } else if (confirmPasswordInput.value !== '' || passwordInput.value !== '') {
            // Tunjukkan ralat hanya jika salah satu input telah diisi
            matchError.style.display = 'block';
        } else {
            matchError.style.display = 'none';
        }
        return passwordsMatch;
    }

    function checkFormValidity() {
        const isPasswordStrong = validatePassword();
        const doPasswordsMatch = validateConfirmPassword();
        
        // Menggunakan checkValidity() untuk mengesahkan semua input yang diperlukan
        const form = document.getElementById('signupForm');
        const isFormFilled = form.checkValidity();

        // IC Number must also be 12 digits
        const icInput = document.getElementById('ic_num');
        const isICValid = icInput.checkValidity();
        
        if (isPasswordStrong && doPasswordsMatch && isFormFilled && isICValid) {
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

</script>

</body>
</html>