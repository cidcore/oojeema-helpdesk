<?php
$hostname = '{mail.oojeema.com:993/imap/ssl}INBOX';
$username = 'support@oojeema.com';
$password = '4678@cid'; // Replace with your real password

echo "Trying to connect...<br>";

$inbox = @imap_open($hostname, $username, $password);

if ($inbox) {
    echo "Connection successful!";
    imap_close($inbox);
} else {
    echo "Connection failed: " . imap_last_error();
}
?>
