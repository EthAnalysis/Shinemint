<?php
// contact.php - Backend for contact form submissions with Cloudflare Turnstile
// Expected POST fields: name, email, message, cf-turnstile-response

header('Content-Type: application/json');

function jsonResponse($success, $message = '')
{
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

function logContactEvent($event, $context = [])
{
    $timestamp = date('c');
    $json = json_encode($context, JSON_UNESCAPED_SLASHES);
    $line = "{$timestamp} {$event} {$json}\n";
    @file_put_contents(__DIR__ . '/contact_debug.log', $line, FILE_APPEND | LOCK_EX);
    error_log("contact.php {$event} {$json}");
}

function verifyTurnstile($secretKey, $token, $remoteIp)
{
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $payload = http_build_query([
        'secret' => $secretKey,
        'response' => $token,
        'remoteip' => $remoteIp,
    ]);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log('Turnstile cURL error: ' . $curlError);
            return [false, 'captcha_verification_failed'];
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 10,
            ]
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            error_log('Turnstile stream request failed');
            return [false, 'captcha_verification_failed'];
        }
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded) || empty($decoded['success'])) {
        $codes = isset($decoded['error-codes']) ? implode(',', (array)$decoded['error-codes']) : 'unknown';
        return [false, $codes];
    }

    return [true, 'ok'];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonResponse(false, 'Invalid request method.');
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');
$turnstileToken = trim($_POST['cf-turnstile-response'] ?? '');

if ($name === '' || $email === '' || $message === '') {
    http_response_code(400);
    jsonResponse(false, 'All fields are required.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    jsonResponse(false, 'Invalid email address.');
}

if ($turnstileToken === '') {
    http_response_code(400);
    jsonResponse(false, 'CAPTCHA verification is required.');
}

$turnstileSecret = getenv('TURNSTILE_SECRET_KEY') ?: '';
if ($turnstileSecret === '' || $turnstileSecret === 'YOUR_TURNSTILE_SECRET_KEY') {
    http_response_code(500);
    logContactEvent('captcha_secret_missing');
    jsonResponse(false, 'Server CAPTCHA is not configured.');
}

$remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
[$captchaOk, $captchaReason] = verifyTurnstile($turnstileSecret, $turnstileToken, $remoteIp);
if (!$captchaOk) {
    logContactEvent('captcha_failed', ['reason' => $captchaReason, 'ip' => $remoteIp]);
    http_response_code(403);
    jsonResponse(false, 'CAPTCHA verification failed. Please try again.');
}

// Prevent header injection in user-supplied fields.
$safeName = str_replace(["\r", "\n"], ' ', $name);
$safeEmail = str_replace(["\r", "\n"], '', $email);

$to = getenv('CONTACT_TO_EMAIL') ?: 'info@shinemint.com';
$subject = "New contact form submission from {$safeName}";
$body = "Name: {$safeName}\nEmail: {$safeEmail}\n\nMessage:\n{$message}";

$fromAddress = 'info@shinemint.com';
$headers = [
    "From: ShineMint Contact <{$fromAddress}>",
    "Reply-To: {$safeEmail}",
    "Sender: {$fromAddress}",
    'X-Mailer: PHP/' . phpversion(),
    'Content-Type: text/plain; charset=UTF-8'
];

$headersText = implode("\r\n", $headers);
$mailSent = @mail($to, $subject, $body, $headersText, "-f {$fromAddress}");

if ($mailSent) {
    logContactEvent('mail_accepted', ['to' => $to, 'from' => $fromAddress, 'reply_to' => $safeEmail]);
    jsonResponse(true, 'Message sent successfully.');
}

$lastError = error_get_last();
logContactEvent('mail_failed', [
    'to' => $to,
    'from' => $fromAddress,
    'reply_to' => $safeEmail,
    'error' => $lastError ? ($lastError['message'] ?? 'unknown') : 'none'
]);
http_response_code(500);
jsonResponse(false, 'Failed to send email. Please try again later.');
?>
