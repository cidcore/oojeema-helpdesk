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

// --- Connect to DB ---
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

// --- Connect to IMAP ---
$inbox = @imap_open($hostname, $username, $password);
if (!$inbox) die('IMAP connection failed: ' . imap_last_error());

// --- Clean body function (latest version) ---
function get_mail_body($inbox, $msgno, $structure = false, $partNum = '') {
    if (!$structure) $structure = imap_fetchstructure($inbox, $msgno);

    if (!isset($structure->parts)) {
        $body = imap_fetchbody($inbox, $msgno, $partNum ? $partNum : 1);
        if ($structure->encoding == 3) $body = base64_decode($body);
        elseif ($structure->encoding == 4) $body = quoted_printable_decode($body);
        return $body;
    }
    $result = '';
    foreach ($structure->parts as $i => $part) {
        $subPartNum = $partNum ? $partNum . '.' . ($i + 1) : (string)($i + 1);
        if ($part->type == 0) {
            $body = imap_fetchbody($inbox, $msgno, $subPartNum);
            if ($part->encoding == 3) $body = base64_decode($body);
            elseif ($part->encoding == 4) $body = quoted_printable_decode($body);
            if (strtolower($part->subtype) == 'html') return $body;
            if (strtolower($part->subtype) == 'plain' && !$result) $result = $body;
        } elseif (isset($part->parts)) {
            $body = get_mail_body($inbox, $msgno, $part, $subPartNum);
            if ($body) return $body;
        }
    }
    return $result;
}

// --- Get emails to update ---
$stmt = $pdo->query("SELECT id, email_uid FROM emails");
$emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updated = 0;
foreach ($emails as $email) {
    $uid = $email['email_uid'];
    $msgno = imap_msgno($inbox, $uid);
    if (!$msgno) {
        echo "Email UID {$uid} not found on server. Skipping.<br>";
        continue;
    }
    $structure = imap_fetchstructure($inbox, $msgno);
    $clean_body = get_mail_body($inbox, $msgno, $structure);

    // Optionally, skip if blank or suspiciously short
    if (!$clean_body || strlen(trim($clean_body)) < 10) {
        echo "No/empty body for UID {$uid}. Skipping.<br>";
        continue;
    }

    // Update in DB
    $u = $pdo->prepare("UPDATE emails SET body = ? WHERE id = ?");
    $u->execute([$clean_body, $email['id']]);
    $updated++;
    echo "Updated email ID {$email['id']} (UID {$uid}).<br>";
}
imap_close($inbox);

echo "<hr>Done! Updated {$updated} emails.";
?>
