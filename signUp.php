<?php
session_start();

include 'config.php'; 

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    
    
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password']; 
    $phoneNum = trim($_POST['phoneNum']);
    
    
    $personId = empty(trim($_POST['person_id'])) ? NULL : trim($_POST['person_id']);
    
    $user_role_id = 1; 
    $status = 'Active'; 

    
    
    
    if (empty($personId)) {
        $_SESSION['error'] = "You must provide a <strong>Student ID</strong> or <strong>Staff ID</strong> to register.";
        header("Location: signUp.php");
        exit();
    }
    
    
    
    
	
	
    $allowed_domains = ['@unikl.edu.my', '@t.unikl.edu.my', '@s.unikl.edu.my'];
    $lower_email = strtolower($email);
    $is_valid_domain = false;
	

    foreach ($allowed_domains as $domain) {
        
        
        $domain_length = strlen($domain);
        if (substr($lower_email, -$domain_length) === $domain) {
             $is_valid_domain = true;
             break;
        }
    }
    if (!$is_valid_domain) { 
        $_SESSION['error'] = "Only <strong>UniKL official email</strong> addresses are allowed for sign up.";
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
        $_SESSION['error'] = 'Password does not meet the requirements.';
        header("Location: signUp.php");
        exit();
    }

    
    
    
    $sql_check = "
        SELECT p.id, p.email, r.role_name
        FROM person p
        LEFT JOIN person_roles pr ON p.person_id = pr.person_id
        LEFT JOIN roles r ON pr.role_id = r.role_id
        WHERE p.email = ? OR p.id = ?
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($sql_check);
    
    if (!$stmt) {
        $_SESSION['error'] = "Database error (Prepare check failed: " . $conn->error . ")";
        header("Location: signUp.php");
        exit();
    }
    
    
    $stmt->bind_param("ss", $email, $personId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        $error_msg = "";
        if (strtolower($row['email']) === $lower_email) {
            $role_found = $row['role_name'] ? strtoupper($row['role_name']) : 'User';
            $error_msg = "Email is already registered as " . $role_found . ".";
        } elseif ($row['id'] === $personId) {
            $role_found = $row['role_name'] ? strtoupper($row['role_name']) : 'User';
            $error_msg = "ID is already registered as " . $role_found . ".";
        }
        
        $_SESSION['error'] = $error_msg;
        $stmt->close();
        header("Location: signUp.php");
        exit();
    }
    $stmt->close();
    
    
    
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $conn->begin_transaction(); 

    try {
        
        $sql_person = "INSERT INTO person (name, email, password, phoneNum, status, id) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_person = $conn->prepare($sql_person);
        
        if (!$stmt_person) {
            throw new Exception("Prepare INSERT Person failed: " . $conn->error);
        }
        
        
        $stmt_person->bind_param("ssssss", $name, $email, $hashed_password, $phoneNum, $status, $personId);

        if (!$stmt_person->execute()) {
            throw new Exception("Execute INSERT Person failed: " . $stmt_person->error);
        }
        
        $new_person_id = $conn->insert_id;
        $stmt_person->close();
        
        
        $sql_role = "INSERT INTO person_roles (person_id, role_id) VALUES (?, ?)";
        $stmt_role = $conn->prepare($sql_role);
        
        if (!$stmt_role) {
            throw new Exception("Prepare INSERT Roles failed: " . $conn->error);
        }
        
        $stmt_role->bind_param("ii", $new_person_id, $user_role_id); 

        if (!$stmt_role->execute()) {
            throw new Exception("Execute INSERT Roles failed: " . $stmt_role->error);
        }
        $stmt_role->close();

        
        $conn->commit();

        $_SESSION['success'] = "Account created successfully. Please log in.";
        $_SESSION['login_attempt_email'] = $email; 
        header("Location: login.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback(); 
        $_SESSION['error'] = "Something went wrong. Please try again. Database error: " . $e->getMessage();
        header("Location: signUp.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* (CSS dikekalkan) */
        :root {
            --primary-color: #00285a; 
            --secondary-color: #005a9c; 
            --light-bg: #f0f4f8; 
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
            align-items: center; 
            justify-content: center; 
            min-height: 100vh; 
        }
        
        .auth-container { 
            width: 100%; 
            max-width: 480px; 
            padding: 40px;
            margin: 20px auto;
            background: #fff; 
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 40, 90, 0.15); 
        }

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
        
        .auth-footer { margin-top: 25px; font-size: 15px; text-align: center; }
        .auth-footer a { color: var(--secondary-color); text-decoration: none; font-weight: 600; }
        .auth-footer a:hover { text-decoration: underline; }
        .alert-danger { 
            background-color: #fef2f2; 
            color: var(--error-color); 
            padding: 12px; 
            border: 1px solid var(--error-color); 
            border-radius: 8px; 
            margin-bottom: 20px;
            font-size: 15px;
            font-weight: 500;
        }
        .alert-success { 
            background-color: #f0fdf4; 
            color: var(--success-color); 
            padding: 12px; 
            border: 1px solid var(--success-color); 
            border-radius: 8px; 
            margin-bottom: 20px;
            font-size: 15px;
            font-weight: 500;
        }

        .form-row {
            display: flex; 
            gap: 20px; 
            margin-bottom: 0; 
        }

        .form-row .input-group {
            flex: 1; 
            margin-bottom: 20px; 
        }

        #password-requirements { 
            list-style-type: none; 
            padding: 0; 
            font-size: 13px; 
            color: #64748b; 
            margin-top: -10px; 
            margin-bottom: 25px; 
            display: grid;
            grid-template-columns: 1fr 1fr; 
            gap: 5px 15px; 
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

        @media (max-width: 600px) {
            body {
                align-items: flex-start; 
                padding: 20px;
            }
            .auth-container {
                box-shadow: none; 
                border-radius: 0;
                padding: 0;
                margin: 0 auto;
                background: var(--light-bg); 
            }
            .auth-header h2 {
                font-size: 28px;
            }
            .form-row {
                flex-direction: column; 
                gap: 0;
            }
            #password-requirements {
                grid-template-columns: 1fr; 
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
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>


<form method="POST" action="signUp.php" id="signupForm" novalidate>
    <div class="input-group">
        <label for="name">Full Name</label>
        <input type="text" name="name" id="name" required 
                value="<?= htmlspecialchars(isset($_POST['name']) ? $_POST['name'] : '') ?>">
    </div>
    
    <div class="input-group">
        <label for="person_id">Student ID / Staff ID</label>
        <input type="text" name="person_id" id="person_id" required
               value="<?= htmlspecialchars(isset($_POST['person_id']) ? $_POST['person_id'] : '') ?>"
               placeholder="Enter Student ID (e.g., 6221XXXXX) or Staff ID (e.g., U0001)">
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
    
    const personIdInput = document.getElementById('person_id');
    
    
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
    document.getElementById('email').addEventListener('input', checkFormValidity);
    document.getElementById('phoneNum').addEventListener('input', checkFormValidity);
    personIdInput.addEventListener('input', checkFormValidity); 
    
    
    document.addEventListener('DOMContentLoaded', checkFormValidity);

</script>

</body>
</html>