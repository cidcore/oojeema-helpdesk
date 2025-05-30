<?php
$hostname = '{mail.oojeema.com:993/imap/ssl}INBOX';
$username = 'support@oojeema.com';
$password = '4678@cid'; // Replace with your actual password

$inbox = @imap_open($hostname, $username, $password);

if (!$inbox) {
    die('Connection failed: ' . imap_last_error());
}

// Search for all emails, newest first
$emails = imap_search($inbox, 'ALL', SE_UID, 'UTF-8');

if ($emails) {
    // Get the latest email
    rsort($emails); // Sort from newest to oldest
    $latest_uid = $emails[0];
    
    $header = imap_headerinfo($inbox, imap_msgno($inbox, $latest_uid));
    $subject = isset($header->subject) ? $header->subject : '(No Subject)';
    $from = isset($header->fromaddress) ? $header->fromaddress : '(No From)';
    
    // Try to fetch the body (works for simple emails)
    $body = imap_body($inbox, imap_msgno($inbox, $latest_uid));
    
    echo "<b>From:</b> " . htmlspecialchars($from) . "<br>";
    echo "<b>Subject:</b> " . htmlspecialchars($subject) . "<br>";
    echo "<b>Body:</b><br><pre>" . htmlspecialchars($body) . "</pre>";
} else {
    echo "No emails found!";
}

imap_close($inbox);
?>
