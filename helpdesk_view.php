<?php
// MySQL config
$db_host = 'localhost';
$db_user = 'dev6ourwebprojec_oojeema_support';
$db_pass = 'xVu08~b=oBCP';
$db_name = 'dev6ourwebprojec_oojeema_support';


try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

// Get thread_id from URL
$thread_id = isset($_GET['thread_id']) ? $_GET['thread_id'] : '';
if (!$thread_id) die('No thread specified.');

// Fetch all emails in thread
$stmt = $pdo->prepare("SELECT * FROM emails WHERE thread_id = ? ORDER BY received_at ASC, id ASC");
$stmt->execute([$thread_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch attachments for each message (indexed by email_id)
$email_ids = array_column($messages, 'id');
$attachments = [];
if ($email_ids) {
    $in  = str_repeat('?,', count($email_ids) - 1) . '?';
    $a_stmt = $pdo->prepare("SELECT * FROM attachments WHERE email_id IN ($in)");
    $a_stmt->execute($email_ids);
    while ($row = $a_stmt->fetch(PDO::FETCH_ASSOC)) {
        $attachments[$row['email_id']][] = $row;
    }
}

// Handle reply form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_body'])) {
    $reply_body = trim($_POST['reply_body']);
    if ($reply_body) {
        // Compose a simple outgoing message (not actually sending email yet)
        $stmt = $pdo->prepare("INSERT INTO emails
            (email_uid, sender_email, sender_name, subject, body, received_at, is_support, message_id, thread_id, direction)
            VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, 'out')");
        $reply_uid = uniqid('reply_');
        $sender_email = 'support@oojeema.com'; // or the em_
