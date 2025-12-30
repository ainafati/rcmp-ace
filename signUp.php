<?php
session_start();
include 'config.php'; 

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Simpan dalam database sebagai HURUF BESAR
    $name = strtoupper(trim($_POST['name'])); 
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password']; 
    $phoneNum = trim($_POST['phoneNum']); // Simpan dengan sengkang dari input
    
    // Simpan ID sebagai HURUF BESAR
    $personId = empty(trim($_POST['person_id'])) ? NULL : strtoupper(trim($_POST['person_id']));
    
    $user_role_id = 1; 
    $status = 'Active'; 

    if (empty($personId)) {
        $_SESSION['error'] = "Student/Staff ID is required.";
        header("Location: signUp.php");
        exit();
    }
    
    // Email Domain Validation
    $allowed_domains = ['@unikl.edu.my', '@t.unikl.edu.my', '@s.unikl.edu.my'];
    $lower_email = strtolower($email);
    $is_valid_domain = false;
    foreach ($allowed_domains as $domain) {
        if (str_ends_with($lower_email, $domain)) {
             $is_valid_domain = true;
             break;
        }
    }

    if (!$is_valid_domain) { 
        $_SESSION['error'] = "Use UniKL official email only.";
        header("Location: signUp.php");
        exit();
    }

    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match!";
        header("Location: signUp.php");
        exit();
    }

    // Database Check
    $sql_check = "SELECT id FROM person WHERE email = ? OR id = ? LIMIT 1";
    $stmt = $conn->prepare($sql_check);
    $stmt->bind_param("ss", $email, $personId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $_SESSION['error'] = "Email or ID already registered.";
        header("Location: signUp.php");
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $conn->begin_transaction(); 

   try {
        $sql_person = "INSERT INTO person (name, email, password, phoneNum, status, id) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_person = $conn->prepare($sql_person);
        $stmt_person->bind_param("ssssss", $name, $email, $hashed_password, $phoneNum, $status, $personId);
        $stmt_person->execute();
        
        $new_person_id = $conn->insert_id;
        $sql_role = "INSERT INTO person_roles (person_id, role_id) VALUES (?, ?)";
        $stmt_role = $conn->prepare($sql_role);
        $stmt_role->bind_param("ii", $new_person_id, $user_role_id);
        $stmt_role->execute();

        $conn->commit();

        // SIMPAN EMAIL DALAM SESSION SEBELUM REDIRECT
        $_SESSION['success'] = "Account created. Please log in.";
        $_SESSION['registered_email'] = $email; 
        
        // Terus ke page login yang ada gambar "Staff / Student" tu
        header("Location: login.php?role=user"); 
        exit();

    } catch (Exception $e) {
        $conn->rollback();  
        $_SESSION['error'] = "Registration failed.";
        header("Location: signUp.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up | NexCheck Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #002147; --accent: #00A3C9; --bg-body: #f8fafc;
            --text-muted: #64748b; --white: #ffffff; --success: #22c55e; --error: #ef4444;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            height: 100vh; width: 100vw;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden; background: var(--bg-body);
        }

        body::before {
            content: ""; position: absolute; inset: 0;
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('img/view.png') center/cover;
            filter: blur(5px); z-index: -1;
        }

        .main-container {
            display: flex; width: 1000px; max-width: 95%; height: 90vh; max-height: 650px;
            background: var(--white); border-radius: 30px; overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }

        .side-visual {
            flex: 0.8; background: var(--primary); padding: 40px; color: white;
            display: flex; flex-direction: column; justify-content: center;
        }

        .side-form {
            flex: 1.2; padding: 30px 50px; display: flex; flex-direction: column;
            justify-content: center; overflow: hidden; /* HILANGKAN SCROLL */
        }

        /* Auto Uppercase Visual */
        #name, #person_id { text-transform: uppercase; }

        h2 { font-weight: 800; color: var(--primary); margin-bottom: 5px; }
        .sub-text { color: var(--text-muted); font-size: 0.8rem; margin-bottom: 20px; }

        .form-row { display: flex; gap: 15px; }
        .input-group { margin-bottom: 12px; flex: 1; }
        .input-group label { font-size: 0.7rem; font-weight: 700; color: var(--primary); display: block; margin-bottom: 4px; text-transform: uppercase; }
        .input-group input { width: 100%; padding: 10px 15px; border-radius: 8px; border: 1px solid #ddd; font-size: 0.85rem; }

        #password-requirements {
            display: grid; grid-template-columns: 1fr 1fr; gap: 5px;
            list-style: none; margin: 10px 0; padding: 10px;
            background: #f8f9fa; border-radius: 8px;
        }

        #password-requirements li { font-size: 0.65rem; color: var(--text-muted); display: flex; align-items: center; gap: 5px; }
        #password-requirements li.valid { color: var(--success); font-weight: bold; }

        .btn-register {
            background: var(--primary); color: white; border: none; padding: 14px;
            border-radius: 8px; width: 100%; font-weight: 700; cursor: pointer; transition: 0.3s;
        }

        .btn-register:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-register:hover:not(:disabled) { background: var(--accent); }

        .auth-footer { text-align: center; margin-top: 15px; font-size: 0.8rem; }
        .auth-footer a { color: var(--accent); text-decoration: none; font-weight: bold; }

        .alert-error { color: var(--error); font-size: 0.75rem; margin-bottom: 10px; }
    </style>
</head>
<body>

<div class="main-container">
    <div class="side-visual">
        <img src="img/Logo-UniKL-PCM.jpg" alt="Logo" style="width:120px; margin-bottom:20px; border-radius:5px;">
        <h1>Join the <span>Nexcheck.</span></h1>
        <p style="opacity:0.8; margin-top:10px;">UniKL RCMP IT Asset Management Portal.</p>
    </div>

    <div class="side-form">
        <h2>Create Account</h2>
        <p class="sub-text">Register with your official UniKL details.</p>

        <?php if(isset($_SESSION['error'])): ?>
            <p class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?></p>
        <?php endif; ?>

        <form method="POST" id="signupForm" novalidate>
            <div class="input-group">
                <label>Full Name</label>
                <input type="text" name="name" id="name" required placeholder="ALI BIN ABU">
            </div>

            <div class="input-group">
                <label>Student / Staff ID</label>
                <input type="text" name="person_id" id="person_id" required placeholder="6221XX">
            </div>

            <div class="form-row">
                <div class="input-group">
                    <label>UniKL Email</label>
                    <input type="email" name="email" id="email" required placeholder="name@unikl.edu.my">
                </div>
                <div class="input-group">
                    <label>Phone No</label>
                    <input type="text" name="phoneNum" id="phoneNum" required placeholder="01X-XXXXXXX" maxlength="12">
                </div>
            </div>

            <div class="form-row">
                <div class="input-group">
                    <label>Password</label>
                    <input type="password" name="password" id="password" required>
                </div>
                <div class="input-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" required>
                </div>
            </div>

            <ul id="password-requirements">
                <li id="length"><i class="fas fa-circle"></i> 8+ Characters</li>
                <li id="uppercase"><i class="fas fa-circle"></i> Uppercase</li>
                <li id="number"><i class="fas fa-circle"></i> Number</li>
                <li id="special"><i class="fas fa-circle"></i> Special Character</li>
                <li id="match" style="grid-column: span 2;"><i class="fas fa-circle"></i> Passwords Match</li>
            </ul>

            <button type="submit" class="btn-register" id="submitBtn" disabled>CREATE ACCOUNT</button>
        </form>

        <div class="auth-footer">
            Already have an account? <a href="login.php?role=user">Login Now</a>
        </div>
    </div>
</div>

<script>
    const nameInput = document.getElementById('name');
    const personIdInput = document.getElementById('person_id');
    const phoneInput = document.getElementById('phoneNum');
    const passInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');
    const submitBtn = document.getElementById('submitBtn');

    // 1. Auto-Uppercase (Name & ID)
    [nameInput, personIdInput].forEach(el => {
        el.addEventListener('input', () => {
            el.value = el.value.toUpperCase();
            validate();
        });
    });

    // 2. Auto-Dash Phone Number (01X-XXXXXXX)
    phoneInput.addEventListener('input', (e) => {
        let val = phoneInput.value.replace(/\D/g, ''); // Ambil nombor sahaja
        if (val.length > 3) {
            val = val.substring(0, 3) + '-' + val.substring(3, 11);
        }
        phoneInput.value = val;
        validate();
    });

    // 3. Password Validation Logic
    const reqs = {
        length: /.{8,}/,
        uppercase: /[A-Z]/,
        number: /[0-9]/,
        special: /[\W_]/
    };

    function validate() {
        const pass = passInput.value;
        const confirm = confirmInput.value;
        
        let isPassValid = true;
        for (const [key, regex] of Object.entries(reqs)) {
            const isValid = regex.test(pass);
            document.getElementById(key).classList.toggle('valid', isValid);
            if (!isValid) isPassValid = false;
        }

        const isMatch = pass.length > 0 && pass === confirm;
        document.getElementById('match').classList.toggle('valid', isMatch);

        const formValid = document.getElementById('signupForm').checkValidity();
        submitBtn.disabled = !(isPassValid && isMatch && formValid);
    }

    [passInput, confirmInput, document.getElementById('email')].forEach(el => {
        el.addEventListener('input', validate);
    });
</script>

</body>
</html>