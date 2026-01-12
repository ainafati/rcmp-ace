<?php
include 'config.php';

$response = ['success' => false, 'message' => 'Failed to update item'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $id = (int)$_POST['edit_id'];
    $name = $_POST['edit_name'];
    $category = $_POST['edit_category'];
    $desc = $_POST['edit_desc'];

    // 1. Ambil imej lama dari database terlebih dahulu (Langkah paling selamat)
    $query = $conn->prepare("SELECT image_url FROM item WHERE item_id = ?");
    $query->bind_param("i", $id);
    $query->execute();
    $result = $query->get_result();
    $item = $result->fetch_assoc();
    $image_url = $item['image_url']; // Default gunakan imej lama

    // 2. Jika ada fail baru diupload
    if (isset($_FILES['edit_image_url']) && $_FILES['edit_image_url']['error'] === 0) {
        $image = $_FILES['edit_image_url'];
        $image_ext = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($image_ext, $allowed_exts)) {
            // Pastikan folder wujud
            if (!file_exists('uploads')) {
                mkdir('uploads', 0777, true);
            }

            $image_new_name = uniqid('item_', true) . '.' . $image_ext;
            
            // Simpan secara konsisten. Jika fail ini di luar folder admin, guna 'uploads/'
            // Jika fail ini di dalam folder admin, guna '../uploads/'
            $image_upload_path = 'uploads/' . $image_new_name; 
            
            if (move_uploaded_file($image['tmp_name'], $image_upload_path)) {
                // Padam imej lama dari server untuk jimat ruang (opsional)
                if (!empty($image_url) && file_exists($image_url)) {
                    unlink($image_url);
                }
                $image_url = $image_upload_path;
            }
        }
    }

    $stmt = $conn->prepare("UPDATE item SET item_name=?, category_id=?, description=?, image_url=? WHERE item_id=?");
    $stmt->bind_param("sissi", $name, $category, $desc, $image_url, $id);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Item updated successfully';
    }
    $stmt->close();
}
echo json_encode($response);
?>