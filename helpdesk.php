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
        COUNT(*) AS message_count,
        MIN(id) AS first_id,
        MAX(id) AS last_id
    FROM emails
    GROUP BY thread_id
    ORDER BY last_update DESC
    LIMIT 50
");

$threads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch the first message of each thread for preview info
$first_ids = array_column($threads, 'first_id');
$thread_previews = [];
if ($first_ids) {
    $in = str_repeat('?,', count($first_ids) - 1) . '?';
    $preview_stmt = $pdo->prepare("SELECT id, subject, sender_email, sender_name, body, received_at FROM emails WHERE id IN ($in)");
    $preview_stmt->execute($first_ids);
    while ($row = $preview_stmt->fetch(PDO::FETCH_ASSOC)) {
        $thread_previews[$row['id']] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Oojeema Support Conversations</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6fa; }
        .thread-list { max-width: 800px; margin: 40px auto; }
        .thread-row {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            margin-bottom: 24px;
            padding: 20px 28px;
            display: flex;
            flex-direction: column;
            transition: box-shadow 0.2s;
        }
        .thread-row:hover {
            box-shadow: 0 4px 14px rgba(0,123,255,0.11);
        }
        .thread-link {
            text-decoration: none;
            color: #212529;
        }
        .subject {
            font-size: 1.18em;
            font-weight: 600;
            color: #007bff;
            margin-bottom: 6px;
        }
        .meta {
            color: #6c757d;
            font-size: 13px;
            margin-bottom: 7px;
        }
        .snippet {
            color: #333;
            margin-bottom: 3px;
        }
        .counter {
            background: #007bff;
            color: #fff;
            font-size: 12px;
            padding: 2px 10px;
            border-radius: 20px;
            margin-left: 5px;
        }
        h2 { text-align: center; margin-bottom: 40px; }
    </style>
</head>
<body>
    <div class="thread-list">
        <h2>Support Conversations</h2>
        <?php foreach($threads as $t): 
            $preview = $thread_previews[$t['first_id']] ?? null;
            $subject = $preview ? $preview['subject'] : '(No Subject)';
            $from = $preview ? ($preview['sender_name'] ?: $preview['sender_email']) : '';
            $body_snippet = $preview ? mb_substr(strip_tags($preview['body']), 0, 110) . (mb_strlen($preview['body']) > 110 ? '...' : '') : '';
            $started = $preview ? date('M d, Y H:i', strtotime($preview['received_at'])) : $t['first_msg'];
        ?>
            <a class="thread-link" href="helpdesk_view.php?thread_id=<?=urlencode($t['thread_id'])?>">
                <div class="thread-row">
                    <span class="subject"><?=htmlspecialchars($subject)?></span>
                    <span class="meta">
                        From: <?=htmlspecialchars($from)?>
                        &nbsp;|&nbsp; Started: <?=htmlspecialchars($started)?>
                        <span class="counter"><?=intval($t['message_count'])?> <?=($t['message_count'] > 1 ? 'messages' : 'message')?></span>
                    </span>
                    <span class="snippet"><?=htmlspecialchars($body_snippet)?></span>
                    <span class="meta" style="margin-top:4px;">Last update: <?=htmlspecialchars(date('M d, Y H:i', strtotime($t['last_update'])))?></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</body>
</html>
