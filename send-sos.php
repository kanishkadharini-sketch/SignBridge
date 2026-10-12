<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['error' => 'Method not allowed.']);
}

if (!is_file(__DIR__ . '/vendor/autoload.php')) {
    error_log('SOS mailer: Composer dependencies are missing.');
    respond(500, ['error' => 'Mail service is not installed yet. Follow the setup instructions in README.md.']);
}

require __DIR__ . '/vendor/autoload.php';

try {
    // Load secrets from .env; never put credentials in browser-side code.
    if (class_exists(\Dotenv\Dotenv::class) && is_file(__DIR__ . '/.env')) {
        \Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
    }

    $gmailAddress = trim((string) (getenv('GMAIL_ADDRESS') ?: ($_ENV['GMAIL_ADDRESS'] ?? '')));
    $gmailPassword = (string) (getenv('GMAIL_APP_PASSWORD') ?: ($_ENV['GMAIL_APP_PASSWORD'] ?? ''));
    $recipient = trim((string) (getenv('SOS_RECIPIENT') ?: ($_ENV['SOS_RECIPIENT'] ?? '')));

    if ($gmailAddress === '' || $gmailPassword === '' || $recipient === '') {
        respond(500, ['error' => 'Mail configuration is incomplete. Check the server .env file.']);
    }
    if (!filter_var($gmailAddress, FILTER_VALIDATE_EMAIL) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        respond(500, ['error' => 'The configured sender or recipient email address is invalid.']);
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') === false) {
        respond(415, ['error' => 'Expected a JSON request.']);
    }

    $payload = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($payload)) {
        respond(400, ['error' => 'Invalid request body.']);
    }

    $location = $payload['location'] ?? null;
    $locationLine = 'Location was not shared.';
    $mapsUrl = null;

    if ($location !== null) {
        if (!is_array($location)
            || !isset($location['latitude'], $location['longitude'])
            || !is_numeric($location['latitude'])
            || !is_numeric($location['longitude'])) {
            respond(400, ['error' => 'Location data is invalid.']);
        }

        $lat = (float) $location['latitude'];
        $lng = (float) $location['longitude'];
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            respond(400, ['error' => 'Location coordinates are out of range.']);
        }

        $locationLine = sprintf('Latitude: %.6f, Longitude: %.6f', $lat, $lng);
        $mapsUrl = sprintf('https://www.google.com/maps?q=%.6f,%.6f', $lat, $lng);
    }

    $profileName = 'Kanishka Dharini';
    $emergencyMessage = 'I need immediate help. Please contact me.';
    $sentAt = date('Y-m-d H:i:s T');

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $gmailAddress;
    $mail->Password = $gmailPassword;
    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';
    $mail->Timeout = 15;

    $mail->setFrom($gmailAddress, 'SOS Emergency Relay');
    $mail->addAddress($recipient);
    $mail->addReplyTo($gmailAddress, $profileName);
    $mail->isHTML(true);
    $mail->Subject = 'SOS Emergency Alert — Immediate Assistance Required';

    $safeName = htmlspecialchars($profileName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeMessage = htmlspecialchars($emergencyMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeLocation = htmlspecialchars($locationLine, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $locationHtml = $mapsUrl
        ? '<p><strong>Map:</strong> <a href="' . htmlspecialchars($mapsUrl, ENT_QUOTES, 'UTF-8') . '">Open location in Google Maps</a></p>'
        : '<p><strong>Map:</strong> No location link available.</p>';

    $mail->Body = '<h2>SOS Emergency Alert</h2>'
        . '<p><strong>This is an emergency assistance request.</strong></p>'
        . '<p><strong>Profile:</strong> ' . $safeName . '</p>'
        . '<p><strong>Message:</strong> ' . $safeMessage . '</p>'
        . '<p><strong>Location:</strong> ' . $safeLocation . '</p>'
        . $locationHtml
        . '<p><strong>Alert time:</strong> ' . htmlspecialchars($sentAt, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p>Please contact the person immediately and verify their safety.</p>';

    $mail->AltBody = "SOS EMERGENCY ALERT\n\n"
        . "Profile: {$profileName}\n"
        . "Message: {$emergencyMessage}\n"
        . "Location: {$locationLine}\n"
        . ($mapsUrl ? "Google Maps: {$mapsUrl}\n" : '')
        . "Alert time: {$sentAt}\n\n"
        . 'Please contact the person immediately and verify their safety.';

    $mail->send();
    respond(200, ['message' => 'SOS email sent successfully to the configured emergency contact.']);
} catch (\Throwable $e) {
    // Keep SMTP details and secrets out of the browser response.
    error_log('SOS mailer failure: ' . $e->getMessage());
    respond(502, ['error' => 'Email delivery failed. Check the server log and Gmail app-password configuration, then try again or call the emergency contact.']);
}
