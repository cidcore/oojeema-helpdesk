<?php
$hostname = '{mail.oojeema.com:993/imap/ssl}INBOX';
$username = 'support@oojeema.com';
$password = '4678@cid';

@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
while (ob_get_level()) ob_end_flush();
header('Content-Type: text/plain; charset=utf-8');

$inbox = @imap_open($hostname, $username, $password);
if (!$inbox) {
    die('IMAP connection failed: ' . imap_last_error());
}

$emails = imap_search($inbox, 'ALL', SE_UID, 'UTF-8');
if ($emails) {
    rsort($emails); // Newest first
    $msgno = imap_msgno($inbox, $emails[0]);
    $structure = imap_fetchstructure($inbox, $msgno);

    print_r($structure);
} else {
    echo "No emails found!";
}

imap_close($inbox);
?>
