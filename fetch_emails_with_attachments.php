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



$upload_dir = __DIR__ . '/uploads/'; // Must exist and be writable

// Helper for filename decoding (RFC2231 and MIME encoded)
function decode_filename($value) {
    // Decode RFC 2231 (e.g., filename*=UTF-8''encoded)
    if (preg_match("/^UTF-8''(.+)/", $value, $matches)) {
        $value = rawurldecode($matches[1]);
    }
    // Decode MIME encoded-words (e.g., =?UTF-8?Q?...?=)
    if (preg_match('/=\?(.+?)\?(Q|B)\?(.+)\?=/i', $value)) {
        $value = mb_decode_mimeheader($value);
    }
    return $value;
}

// Recursive attachment/inline image extractor with correct part numbering
function save_attachments_recursive($parts, $inbox, $msgno, $email_id, $upload_dir, $pdo, $prefix = '', &$cid_map = []) {
    if (!is_array($parts)) return;
    foreach ($parts as $i => $part) {
        $partnum = $prefix ? $prefix . '.' . ($i+1) : (string)($i+1);

        // Get filename (check dparameters first, then parameters)
        $filename = '';
        if (isset($part->dparameters) && is_array($part->dparameters)) {
            foreach ($part->dparameters as $param) {
                if (strtolower($param->attribute) === 'filename*' || strtolower($param->attribute) === 'filename') {
                    $filename = decode_filename($param->value);
                    break;
                }
            }
        }
        if (!$filename && isset($part->parameters) && is_array($part->parameters)) {
            foreach ($part->parameters as $param) {
                if (strtolower($param->attribute) === 'name' || strtolower($param->attribute) === 'filename') {
                    $filename = decode_filename($param->value);
                    break;
                }
            }
        }
        // If no filename but this is an image, generate a name
        if (!$filename && isset($part->type) && $part->type == 5) {
            $ext = isset($part->subtype) ? strtolower($part->subtype) : 'img';
            if (isset($part->id)) {
                $cid = trim($part->id, "<>");
                $filename = 'cid_' . $cid . '.' . $ext;
            } else {
                $filename = 'inline_img_' . uniqid() . '.' . $ext;
            }
        }

        // Save any image or part with a filename
        if ($filename && (isset($part->type) && $part->type == 5) || ($filename && $filename !== '')) {
            $filename = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $filename);
            $file_path = $upload_dir . uniqid() . '_' . $filename;

            $attachment = imap_fetchbody($inbox, $msgno, $partnum);
            if ($part->encoding == 3) { // BASE64
                $attachment = base64_decode($attachment);
            } elseif ($part->encoding == 4) { // QUOTED-PRINTABLE
                $attachment = quoted_printable_decode($attachment);
            }
            // Prevent saving empty attachments (just in case)
            if ($attachment && strlen($attachment) > 100) {
                file_put_contents($file_path, $attachment);

                // Save to attachments table
                $a_stmt = $pdo->prepare("INSERT INTO attachments (email_id, file_name, file_path) VALUES (?, ?, ?)");
                $a_stmt->execute([
                    $email_id,
                    $filename,
                    $file_path
                ]);
            }

            // Map CID to file if needed
            if (isset($part->id)) {
                $cid = trim($part->id, "<>");
                $cid_map[$cid] = $file_path;
            }
        }

        // Recurse for sub-parts (handle nesting, crucial for inline images)
        if (isset($part->parts)) {
            save_attachments_recursive($part->parts, $inbox, $msgno, $email_id, $upload_dir, $pdo, $partnum, $cid_map);
        }
    }
}

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

$inbox = @imap_open($hostname, $username, $password);
if (!$inbox) {
    die('IMAP connection failed: ' . imap_last_error());
}

$emails = imap_search($inbox, 'UNSEEN', SE_UID, 'UTF-8');

if ($emails) {
    $saved = 0;
    foreach ($emails as $uid) {
        $msgno = imap_msgno($inbox, $uid);
        $header = imap_headerinfo($inbox, $msgno);
        $subject = isset($header->subject) ? $header->subject : '(No Subject)';
        $from_email = (isset($header->from[0]->mailbox) && isset($header->from[0]->host))
            ? $header->from[0]->mailbox . '@' . $header->from[0]->host
            : '';
        $from_name = isset($header->from[0]->personal) ? $header->from[0]->personal : '';
        $body = imap_body($inbox, $msgno);
        $date = isset($header->date) ? date('Y-m-d H:i:s', strtotime($header->date)) : date('Y-m-d H:i:s');
        $email_uid = $uid;

        // --- Threading Logic ---
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

        // Insert email (or update thread if already exists)
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

        // Get email_id for attachments
        $email_id = $pdo->lastInsertId();
        if ($email_id == 0) {
            $id_stmt = $pdo->prepare("SELECT id FROM emails WHERE email_uid = ?");
            $id_stmt->execute([$email_uid]);
            $row = $id_stmt->fetch(PDO::FETCH_ASSOC);
            $email_id = $row ? $row['id'] : null;
        }

        // --- Save ALL attachments and inline images, even CID-only, with correct part numbers
        $structure = imap_fetchstructure($inbox, $msgno);
        $cid_map = [];
        if (isset($structure->parts) && count($structure->parts)) {
            save_attachments_recursive($structure->parts, $inbox, $msgno, $email_id, $upload_dir, $pdo, '', $cid_map);
        }
        $saved++;
    }
    echo "Processed $saved email(s), with all attachments and inline images (including CID) saved if present!";
} else {
    echo "No unread emails found!";
}

imap_close($inbox);
?>
