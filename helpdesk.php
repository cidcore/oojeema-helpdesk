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

// Get all threads (latest activity first)
$stmt = $pdo->query("
    SELECT thread_id, MAX(received_at) AS last_update, MIN(received_at) AS first_msg,
        COUNT(*) AS message_count
    FROM emails
    GROUP BY thread_id
    ORDER BY last_update DESC
    LIMIT 50
");

$threads = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Oojeema Support Helpdesk</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .thread-list { max-width: 600px; margin: 40px auto; }
        .thread-row { border-bottom: 1px solid #ccc; padding: 16px; }
        .thread-row a { text-decoration: none; color: #007bff; }
        .thread-row a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="thread-list">
        <h2>Support Conversations</h2>
        <?php foreach($threads as $t): ?>
            <div class="thread-row">
                <a href="helpdesk_view.php?thread_id=<?=urlencode($t['thread_id'])?>">
                    Thread: <?=htmlspecialchars($t['thread_id'])?><br>
                    Started: <?=htmlspecialchars($t['first_msg'])?><br>
                    Last update: <?=htmlspecialchars($t['last_update'])?><br>
                    Messages: <?=$t['message_count']?>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
