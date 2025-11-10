<?php
include 'config.php';

$response = ['success' => false, 'message' => 'Failed to update item'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $id = (int)$_POST['edit_id'];
    $name = $_POST['edit_name'];
    $category = $_POST['edit_category'];
    $desc = $_POST['edit_desc'];

    // Check if a new image is uploaded
    $image_url = $_POST['edit_image_url'] ?? ''; // If no new image, use the old one
    
    // If a new image is uploaded, handle the file upload
    if (isset($_FILES['edit_image_url']) && $_FILES['edit_image_url']['error'] === 0) {
        $image = $_FILES['edit_image_url'];
        $image_name = $image['name'];
        $image_tmp_name = $image['tmp_name'];
        $image_ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($image_ext, $allowed_exts)) {
            $image_new_name = uniqid('item_', true) . '.' . $image_ext;
            $image_upload_path = 'uploads/' . $image_new_name;
            if (move_uploaded_file($image_tmp_name, $image_upload_path)) {
                $image_url = $image_upload_path;
            } else {
                $response['message'] = 'Error uploading the image';
                echo json_encode($response);
                exit();
            }
        }
    }

    // Update the item record
    $stmt = $conn->prepare("UPDATE item SET item_name=?, category_id=?, description=?, image_url=? WHERE item_id=?");
    $stmt->bind_param("sissi", $name, $category, $desc, $image_url, $id);
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Item updated successfully';
    } else {
        $response['message'] = 'Error updating item';
    }
    $stmt->close();
}

echo json_encode($response);
?>
