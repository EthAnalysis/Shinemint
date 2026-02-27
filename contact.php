<?php
// contact.php - Simple backend for contact form submissions
// Expected POST fields: name, email, message

header('Content-Type: application/json');

// Helper function to send JSON response
function jsonResponse($success, $message = '') {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}

// Retrieve and sanitize inputs
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

if (empty($name) || empty($email) || empty($message)) {
    jsonResponse(false, 'All fields are required.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Invalid email address.');
}

// Prepare email (adjust the recipient as needed)
$to = 'info@shinemint.com'; // Change to your desired recipient
$subject = "New Shinemint rm submission from $name";
$body = "Name: $name\nEmail: $email\n\nMessage:\n$message";
$headers = "From: $email\r\nReply-To: $email\r\nX-Mailer: PHP/" . phpversion();

// Attempt to send email
if (mail($to, $subject, $body, $headers)) {
    jsonResponse(true, 'Message sent successfully.');
} else {
    jsonResponse(false, 'Failed to send email. Please try again later.');
}
?>
