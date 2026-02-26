<?php
session_start();
include '../config.php';

header('Content-Type: application/json');

// 1. Check Auth (Kekalkan logic asal)
if (!isset($_SESSION['person_id']) || $_SESSION['logged_in_role'] !== 'Admin') {
    echo json_encode(["success" => false, "message" => "Akses disekat!"]);
    exit();
}

// 2. Ambil data
$person_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$roles_input = isset($_POST['roles']) ? $_POST['roles'] : [];

// --- PENAMBAHBAIKAN DI SINI ---
// Jika roles datang sebagai JSON string (dari fetch JS), kita decode dulu
if (is_string($roles_input)) {
    $decoded = json_decode($roles_input, true);
    if (is_array($decoded)) {
        $roles_selected = $decoded;
    } else {
        $roles_selected = [$roles_input]; // Kalau satu string saja
    }
} else {
    $roles_selected = $roles_input; // Kalau dah memang array (dari form submit biasa)
}
// ------------------------------

if ($person_id <= 0) {
    echo json_encode(["success" => false, "message" => "Missing data: ID is empty."]);
    exit();
}

try {
    $conn->begin_transaction();

    // 3. Buang role lama
    $stmt_del = $conn->prepare("DELETE FROM person_roles WHERE person_id = ?");
    $stmt_del->bind_param("i", $person_id);
    $stmt_del->execute();

    // 4. Pastikan 'User' sentiasa ada (Logic hang dah betul)
    if (!in_array('User', $roles_selected)) {
        $roles_selected[] = 'User';
    }

    // 5. Masukkan role baru
    // Kita prepare satu kali saja kat luar loop untuk performance (Best practice!)
    $stmt_ins = $conn->prepare("INSERT INTO person_roles (person_id, role_id) 
                                VALUES (?, (SELECT role_id FROM roles WHERE role_name = ? LIMIT 1))");

    foreach ($roles_selected as $role_name) {
        if (!empty($role_name)) {
            $stmt_ins->bind_param("is", $person_id, $role_name);
            $stmt_ins->execute();
        }
    }

    $conn->commit();
    echo json_encode(["success" => true, "message" => "Role successfully updated!"]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
exit();