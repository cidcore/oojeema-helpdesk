<?php
// MySQL config
$db_host = 'localhost';
$db_user = 'dev6ourwebprojec_oojeema_support';
$db_pass = 'xVu08~b=oBCP';
$db_name = 'dev6ourwebprojec_oojeema_support';


$upload_dir = __DIR__ . '/uploads/';
$upload_url = '/oojeema/uploads/'; // adjust if your uploads are at a different URL

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

$thread_id = isset($_GET['thread_id']) ? $_GET['thread_id'] : '';
if (!$thread_id) die('No thread specified.');

// Fetch thread
$stmt = $pdo->prepare("SELECT * FROM emails WHERE thread_id = ? ORDER BY received_at ASC, id ASC");
$stmt->execute([$thread_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch attachments for each message
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

// Handle reply form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reply_body = $_POST['reply_body'] ?? '';
    $files = $_FILES['attachments'] ?? null;
    if ($reply_body) {
        // Save outgoing email
        $stmt = $pdo->prepare("INSERT INTO emails
            (email_uid, sender_email, sender_name, subject, body, received_at, is_support, message_id, thread_id, direction)
            VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, 'out')");
        $reply_uid = uniqid('reply_');
        $sender_email = 'support@oojeema.com';
        $sender_name = 'Oojeema Support';
        $subject = '[Reply] Oojeema Support';
        $date = date('Y-m-d H:i:s');
        $message_id = '<' . uniqid() . '@oojeema.com>';

        $stmt->execute([
            $reply_uid,
            $sender_email,
            $sender_name,
            $subject,
            $reply_body,
            $date,
            $message_id,
            $thread_id
        ]);
        $reply_id = $pdo->lastInsertId();

        // Handle file uploads (save to /uploads/ and record in attachments)
        if ($files && isset($files['tmp_name']) && is_array($files['tmp_name'])) {
            foreach ($files['tmp_name'] as $idx => $tmp_name) {
                if ($tmp_name && is_uploaded_file($tmp_name)) {
                    $orig_name = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $files['name'][$idx]);
                    $target_name = uniqid('reply_') . '_' . $orig_name;
                    $target_path = $upload_dir . $target_name;
                    if (move_uploaded_file($tmp_name, $target_path)) {
                        $file_url = $upload_url . $target_name;
                        $a_stmt = $pdo->prepare("INSERT INTO attachments (email_id, file_name, file_path) VALUES (?, ?, ?)");
                        $a_stmt->execute([$reply_id, $orig_name, $file_url]);
                    }
                }
            }
        }
        // Redirect to self
        header("Location: helpdesk_view.php?thread_id=" . urlencode($thread_id));
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Conversation: <?=htmlspecialchars($thread_id)?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6fa; margin:0; }
        .container { display: flex; flex-direction: row; max-width: 1200px; margin: 32px auto 0 auto; background:#fff; border-radius: 12px; box-shadow:0 3px 16px rgba(0,0,0,0.08);}
        .col-left { width: 58%; border-right:1px solid #e5e5e5; padding:36px 28px 28px 36px; }
        .col-right { width: 42%; padding:36px 36px 28px 28px; background:#fafbfc;}
        .message { border-bottom: 1px solid #eee; margin-bottom: 22px; padding-bottom: 18px; }
        .msg-in { background: #f8f9fa; }
        .msg-out { background: #e7f5ff; }
        .from { font-weight: bold; margin-bottom:3px;}
        .date { color: #666; font-size: 12px; }
        .attachments { margin-top: 6px; }
        .attachments a { margin-right: 10px; }
        .tinymce-area { width:100%; margin-bottom:16px; }
        .attach-label {font-size:13px;}
        @media (max-width: 900px) {
            .container { flex-direction: column; }
            .col-left, .col-right { width: 100%; border:none; padding:24px 12px; }
        }
    </style>
   <script src="https://cdn.tiny.cloud/1/bmc7riaoamwj7tzvfvsdoc9cp045w413zi4g6qan57z98bqd/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

    <script>
    tinymce.init({
        selector: '#reply_body',
        plugins: 'lists link image paste',
        toolbar: 'undo redo | bold italic underline | bullist numlist | link image',
        height: 220,
        menubar: false,
        branding: false,
        images_upload_url: 'upload_image_inline.php',
        automatic_uploads: true,
        images_upload_handler: function (blobInfo, success, failure) {
            var xhr, formData;
            xhr = new XMLHttpRequest();
            xhr.withCredentials = false;
            xhr.open('POST', 'upload_image_inline.php');
            xhr.onload = function() {
                var json;
                if (xhr.status != 200) {
                    failure('HTTP Error: ' + xhr.status);
                    return;
                }
                json = JSON.parse(xhr.responseText);
                if (!json || typeof json.location != 'string') {
                    failure('Invalid JSON: ' + xhr.responseText);
                    return;
                }
                success(json.location);
            };
            formData = new FormData();
            formData.append('file', blobInfo.blob(), blobInfo.filename());
            xhr.send(formData);
        }
    });
    </script>
</head>
<body>
    <div class="container">
        <!-- Conversation on Left -->
        <div class="col-left">
            <a href="helpdesk.php">&lt; Back to conversation list</a>
            <?php foreach($messages as $msg): ?>
                <div class="message <?=($msg['direction'] === 'out' ? 'msg-out' : 'msg-in')?>">
                    <div class="from">
                        <?=htmlspecialchars($msg['sender_name'] ?: $msg['sender_email'])?>
                        (<?=($msg['direction'] === 'out' ? 'You' : 'Client')?>)
                    </div>
                    <div class="date"><?=htmlspecialchars($msg['received_at'])?></div>
                    <div class="body">
<?php
// Start with the raw body
$body = $msg['body'];

// If there are attachments with content_id, replace src="cid:..." with the file path
if (!empty($attachments[$msg['id']])) {
    foreach ($attachments[$msg['id']] as $a) {
        if (!empty($a['content_id'])) {
            $cid = preg_quote($a['content_id'], '/');
            $body = preg_replace(
                '/src=(["\'])cid:' . $cid . '\1/i',
                'src="' . htmlspecialchars($a['file_path']) . '"',
                $body
            );
        }
    }
}

// Now render as HTML or plain text as appropriate
if (
    stripos($body, '<html') !== false ||
    stripos($body, '<body') !== false ||
    stripos($body, '<div') !== false ||
    stripos($body, '<p') !== false ||
    stripos($body, '<br') !== false
) {
    echo $body;
} else {
    echo nl2br(htmlspecialchars($body));
}
?>
</div>

                    <?php if (!empty($attachments[$msg['id']])): ?>
                        <div class="attachments">
                            <span class="attach-label">Attachments:</span>
                            <?php foreach($attachments[$msg['id']] as $a): ?>
                                <a href="<?=htmlspecialchars($a['file_path'])?>" target="_blank"><?=htmlspecialchars($a['file_name'])?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <!-- Reply on Right -->
        <div class="col-right">
            <h3>Reply to this thread</h3>
            <form method="post" enctype="multipart/form-data">
                <textarea id="reply_body" name="reply_body" class="tinymce-area" required></textarea>
                <label class="attach-label">Attach files (PDF, image, etc):</label><br>
                <input type="file" name="attachments[]" multiple style="margin-bottom:18px;"><br>
                <button type="submit" style="padding:10px 28px; font-size:1em; background:#007bff; color:#fff; border:none; border-radius:6px;">Send Reply</button>
            </form>
        </div>
    </div>
</body>
</html>
