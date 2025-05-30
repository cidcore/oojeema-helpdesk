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



$upload_dir = __DIR__ . '/uploads/';      // Make sure this folder exists and is writable (755 or 775)
// ===================
//  CONFIGURATION END
// ===================

// ==========
// CONNECT DB
// ==========
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

// ================
// CONNECT TO IMAP
// ================
$inbox = @imap_open($hostname, $username, $password);
if (!$inbox) {
    die('IMAP connection failed: ' . imap_last_error());
}

// ============
// SEARCH EMAILS
// ============
$emails = imap_search($inbox, 'UNSEEN', SE_UID, 'UTF-8');

// ==============
// BODY EXTRACTOR
// ==============
function get_mail_body($inbox, $msgno, $structure = false, $partNum = '') {
    if (!$structure) $structure = imap_fetchstructure($inbox, $msgno);

    if (!isset($structure->parts)) {
        // Single-part: just get body
        $body = imap_fetchbody($inbox, $msgno, $partNum ? $partNum : 1);
        // Decode as needed
        if ($structure->encoding == 3) { // BASE64
            $body = base64_decode($body);
        } elseif ($structure->encoding == 4) { // QUOTED-PRINTABLE
            $body = quoted_printable_decode($body);
        }
        return $body;
    }

    // Multipart: look for HTML first, else plain
    $result = '';
    foreach ($structure->parts as $i => $part) {
        $subPartNum = $partNum ? $partNum . '.' . ($i + 1) : (string)($i + 1);
        if ($part->type == 0) { // text: 0=plain/html
            $body = imap_fetchbody($inbox, $msgno, $subPartNum);
            if ($part->encoding == 3) $body = base64_decode($body);
            elseif ($part->encoding == 4) $body = quoted_printable_decode($body);
            if (strtolower($part->subtype) == 'html') {
                return $body; // Prefer HTML
            }
            if (strtolower($part->subtype) == 'plain' && !$result) {
                $result = $body; // Save plain if no HTML found yet
            }
        } elseif (isset($part->parts)) {
            // Recurse for nested multiparts (e.g. "alternative", "related", "mixed")
            $body = get_mail_body($inbox, $msgno, $part, $subPartNum);
            if ($body) return $body;
        }
    }
    return $result;
}


// ===============
// ATTACHMENT SAVE
// ===============
function save_attachments_recursive($parts, $inbox, $msgno, $email_id, $upload_dir, $pdo, $prefix = '') {
    if (!is_array($parts)) return;
    foreach ($parts as $i => $part) {
        $partnum = $prefix ? $prefix . '.' . ($i+1) : (string)($i+1);

        // Save all images and attachments with a filename
            if (isset($part->type) && ($part->type == 5 || $part->type == 3 || $part->type == 4)) {
                $ext = isset($part->subtype) ? strtolower($part->subtype) : 'dat';
                $filename = 'attachment_' . uniqid() . '.' . $ext;
                $filename = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $filename);

                // Path to save on the server
                $server_file_path = $upload_dir . $filename;

                // Path to use in the browser (web-accessible!)
                $web_path = '/oojeema/uploads/' . $filename;

                $attachment = imap_fetchbody($inbox, $msgno, $partnum);
                if ($part->encoding == 3) { // BASE64
                    $attachment = base64_decode($attachment);
                } elseif ($part->encoding == 4) { // QUOTED-PRINTABLE
                    $attachment = quoted_printable_decode($attachment);
                }
                if ($attachment && strlen($attachment) > 100) {
                    file_put_contents($server_file_path, $attachment);

                    // Get Content-ID (for inline images)
                    $content_id = '';
                    if (isset($part->id)) {
                        $content_id = trim($part->id, "<>");
                    }

                    // Save to attachments table (now with content_id column!)
                    $a_stmt = $pdo->prepare("INSERT INTO attachments (email_id, file_name, file_path, content_id) VALUES (?, ?, ?, ?)");
                    $a_stmt->execute([
                        $email_id,
                        $filename,
                        $web_path,     // <- web-accessible path
                        $content_id
                    ]);
                }
            }



            }
        }
        // Recurse for sub-parts
        if (isset($part->parts)) {
            save_attachments_recursive($part->parts, $inbox, $msgno, $email_id, $upload_dir, $pdo, $partnum);
        }
    //}
//}

// ==========
// MAIN LOGIC
// ==========
if ($emails) {
    $saved = 0;
    foreach ($emails as $uid) {
        $msgno = imap_msgno($inbox, $uid);
        $header = imap_headerinfo($inbox, $msgno);
        $structure = imap_fetchstructure($inbox, $msgno);

        $subject = isset($header->subject) ? $header->subject : '(No Subject)';
        $from_email = (isset($header->from[0]->mailbox) && isset($header->from[0]->host))
            ? $header->from[0]->mailbox . '@' . $header->from[0]->host
            : '';
        $from_name = isset($header->from[0]->personal) ? $header->from[0]->personal : '';
        $body = get_mail_body($inbox, $msgno, $structure);  // <--- Use the clean body function!
        $date = isset($header->date) ? date('Y-m-d H:i:s', strtotime($header->date)) : date('Y-m-d H:i:s');
        $email_uid = $uid;

        // --- Threading Logic (leave as is) ---
        $message_id = isset($header->message_id) ? trim($header->message_id) : null;
        $in_reply_to = isset($header->in_reply_to) ? trim($header->in_reply_to) : null;

        $thread_id = $message_id ?: uniqid('thread_');
        if ($in_reply_to) {
            $parent_stmt = $pdo->prepare("SELECT thread_id FROM emails WHERE message_id = ?");
            $parent_stmt->execute([$in_reply_to]);
            $parent = $parent_stmt->fetch(PDO::FETCH_ASSOC);
            if ($parent && $parent['thread_id']) {
                $thread_id = $parent['thread_id'];
            }
        }

        // --- Save Email to Database ---
        $stmt = $pdo->prepare("
            INSERT INTO emails (
                email_uid, sender_email, sender_name, subject, body, received_at, is_support, message_id, thread_id
            ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)
            ON DUPLICATE KEY UPDATE thread_id = VALUES(thread_id)
        ");
        $stmt->execute([
            $email_uid,
            $from_email,
            $from_name,
            $subject,
            $body,
            $date,
            $message_id,
            $thread_id
        ]);

        // --- Get email_id for attachments ---
        $email_id = $pdo->lastInsertId();
        if ($email_id == 0) {
            $id_stmt = $pdo->prepare("SELECT id FROM emails WHERE email_uid = ?");
            $id_stmt->execute([$email_uid]);
            $row = $id_stmt->fetch(PDO::FETCH_ASSOC);
            $email_id = $row ? $row['id'] : null;
        }

        // --- Save attachments and images
        if (isset($structure->parts) && count($structure->parts)) {
            save_attachments_recursive($structure->parts, $inbox, $msgno, $email_id, $upload_dir, $pdo, '');
        }
        $saved++;
    }
    echo "Processed $saved email(s).";
} else {
    echo "No unread emails found!";
}

imap_close($inbox);
?>
