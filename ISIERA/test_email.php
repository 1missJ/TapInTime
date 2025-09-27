<?php
$to = "youremail@example.com"; // Your real email for testing
$subject = "Test Email";
$message = "This is a test email from the server.";
$headers = "From: noreply@yourschool.com\r\n";

if (mail($to, $subject, $message, $headers)) {
    echo "Email sent successfully! Check your inbox (and spam folder).";
} else {
    echo "Email failed to send. Check server error logs.";
    error_log("Test email failed to send to $to");
}
?>