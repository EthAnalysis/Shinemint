<?php
// contact.php - Backend for contact form submissions
// Expected POST fields: name, email, message

header('Content-Type: application/json');

function jsonResponse($success, $message = '')
{
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonResponse(false, 'Invalid request method.');
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($name === '' || $email === '' || $message === '') {
    http_response_code(400);
    jsonResponse(false, 'All fields are required.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    jsonResponse(false, 'Invalid email address.');
}

// Prevent header injection in user-supplied fields.
$safeName = str_replace(["\r", "\n"], ' ', $name);
$safeEmail = str_replace(["\r", "\n"], '', $email);

$to = 'info@shinemint.com';
$subject = "New contact form submission from {$safeName}";
$body = "Name: {$safeName}\nEmail: {$safeEmail}\n\nMessage:\n{$message}";

// Use a domain-matching sender to avoid HostGator mail rejection.
$fromAddress = 'noreply@shinemint.com';
$headers = [
    "From: ShineMint Contact <{$fromAddress}>",
    "Reply-To: {$safeEmail}",
    'X-Mailer: PHP/' . phpversion(),
    'Content-Type: text/plain; charset=UTF-8'
];

$headersText = implode("\r\n", $headers);
$mailSent = @mail($to, $subject, $body, $headersText, "-f {$fromAddress}");

if ($mailSent) {
    jsonResponse(true, 'Message sent successfully.');
}

error_log("contact.php mail() failed for recipient {$to} from {$safeEmail}");
http_response_code(500);
jsonResponse(false, 'Failed to send email. Please try again later.');
?>
