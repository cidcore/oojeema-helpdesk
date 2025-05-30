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
        $sender_email = 'support@oojeema.com'; // or the email you send from
        $sender_name = 'Oojeema Support';      // or your agent's name
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
        // Redirect to self to avoid form resubmission
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
        body { font-family: Arial, sans-serif; }
        .message { border-bottom: 1px solid #ccc; margin: 20px 0; padding: 10px; }
        .msg-in { background: #f8f9fa; }
        .msg-out { background: #e7f5ff; }
        .from { font-weight: bold; }
        .date { color: #666; font-size: 12px; }
        .attachments { margin-top: 5px; }
    </style>
</head>
<body>
    <div style="max-width: 700px; margin: 40px auto;">
        <h2>Thread: <?=htmlspecialchars($thread_id)?></h2>
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
                    // Show as HTML if body looks like HTML, else plain text
                    if (
                        stripos($msg['body'], '<html') !== false ||
                        stripos($msg['body'], '<body') !== false ||
                        stripos($msg['body'], '<div') !== false ||
                        stripos($msg['body'], '<p') !== false ||
                        stripos($msg['body'], '<br') !== false
                    ) {
                        echo $msg['body'];
                    } else {
                        echo nl2br(htmlspecialchars($msg['body']));
                    }
                    ?>
                </div>
                <?php if (!empty($attachments[$msg['id']])): ?>
                    <div class="attachments">
                        Attachments:
                        <?php foreach($attachments[$msg['id']] as $a): ?>
                            <a href="<?=htmlspecialchars($a['file_path'])?>" target="_blank"><?=htmlspecialchars($a['file_name'])?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <hr>
        <h3>Reply to this thread</h3>
        <form method="post">
            <textarea name="reply_body" rows="6" style="width:100%" required></textarea><br>
            <button type="submit">Send Reply</button>
        </form>
    </div>
</body>
</html>
