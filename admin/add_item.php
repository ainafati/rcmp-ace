<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['category_name'] ?? '');

    if ($name == '') {
        echo 'empty';
        exit;
    }

    $check = $conn->prepare("SELECT * FROM categories WHERE category_name = ?");
    $check->bind_param("s", $name);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows > 0) {
        echo 'exists';
    } else {
        $insert = $conn->prepare("INSERT INTO categories (category_name) VALUES (?)");
        $insert->bind_param("s", $name);
        echo $insert->execute() ? 'success' : 'fail';
    }
}
?>