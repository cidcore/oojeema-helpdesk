<?php
// Email config
$hostname = '{mail.oojeema.com:993/imap/ssl}INBOX';
$username = 'support@oojeema.com';
$password = '4678@cid';

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

// Connect to mailbox
$inbox = @imap_open($hostname, $username, $password);
if (!$inbox) {
    die('IMAP connection failed: ' . imap_last_error());
}

// Get the latest email (as before)
$emails = imap_search($inbox, 'ALL', SE_UID, 'UTF-8');
if ($emails) {
    rsort($emails);
    $latest_uid = $emails[0];
    $msgno = imap_msgno($inbox, $latest_uid);

    $header = imap_headerinfo($inbox, $msgno);
    $subject = isset($header->subject) ? $header->subject : '(No Subject)';
    $from = isset($header->fromaddress) ? $header->fromaddress : '(No From)';
    $from_email = (isset($header->from[0]->mailbox) && isset($header->from[0]->host))
        ? $header->from[0]->mailbox . '@' . $header->from[0]->host
        : '';
    $from_name = isset($header->from[0]->personal) ? $header->from[0]->personal : '';
    $body = imap_body($inbox, $msgno);
    $date = isset($header->date) ? date('Y-m-d H:i:s', strtotime($header->date)) : date('Y-m-d H:i:s');
    $email_uid = $latest_uid;

    // Insert email into DB (ignore if already saved by unique uid)
    $stmt = $pdo->prepare("INSERT IGNORE INTO emails (email_uid, sender_email, sender_name, subject, body, received_at, is_support) VALUES (?, ?, ?, ?, ?, ?, 1)");
    $stmt->execute([
        $email_uid,
        $from_email,
        $from_name,
        $subject,
        $body,
        $date
    ]);

    echo "Saved latest email to database!";
} else {
    echo "No emails found!";
}

imap_close($inbox);
?>
