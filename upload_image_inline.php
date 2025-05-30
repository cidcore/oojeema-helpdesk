<?php
// Save TinyMCE inline image uploads to /uploads/
$upload_dir = __DIR__ . '/uploads/';
$upload_url = '/oojeema/uploads/';

if (!empty($_FILES['file']['name'])) {
    $file = $_FILES['file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    if (in_array($ext, $allowed)) {
        $target_name = uniqid('inline_') . '.' . $ext;
        $target_path = $upload_dir . $target_name;
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            echo json_encode(['location' => $upload_url . $target_name]);
            exit;
        }
    }
}
http_response_code(400);
echo json_encode(['error' => 'Failed']);
?>
