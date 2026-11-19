<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * ═══════════════════════════════════════════════════════════
 * EMAIL PROXY - BHV overzicht per e-mail versturen
 * ═══════════════════════════════════════════════════════════
 * LOCATIE: /email-proxy.php (ROOT)
 * BESCHRIJVING: API endpoint voor BHV overzicht emails
 *
 * VERSIE: 2.1 - BEVEILIGD
 *   Voorheen kon iedereen via dit bestand een e-mail laten versturen
 *   zonder in te loggen (open mail-relay). Nu geldt:
 *   - alleen POST;
 *   - alleen voor ingelogde gebruikers (sessie of "blijf ingelogd");
 *   - alleen vanuit dezelfde website (Origin-controle, tegen CSRF);
 *   - maximaal 10 e-mails per 10 minuten per sessie;
 *   - onderwerp en bericht begrensd, regeleinden uit het onderwerp;
 *   - foutdetails worden niet meer aan de browser teruggegeven.
 * ═══════════════════════════════════════════════════════════
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');

// db.php start de sessie en herstelt een "blijf ingelogd"-sessie
require_once __DIR__ . '/includes/db.php';
// Centraal e-mailsysteem
require_once __DIR__ . '/includes/email_functions.php';

/**
 * Geef een foutmelding als JSON en stop.
 */
function pd_email_proxy_fail(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// 1. Alleen POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    pd_email_proxy_fail(405, 'Alleen POST is toegestaan');
}

// 2. Alleen ingelogde gebruikers
if (empty($_SESSION['user_id'])) {
    pd_email_proxy_fail(401, 'Niet ingelogd');
}

// 3. Alleen verzoeken vanaf deze website zelf (tegen CSRF)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $originHost = parse_url($origin, PHP_URL_HOST);
    $ownHost    = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
    if (!$originHost || strcasecmp($originHost, $ownHost) !== 0) {
        pd_email_proxy_fail(403, 'Verzoek niet toegestaan');
    }
}

// 4. Maximaal 10 e-mails per 10 minuten per sessie
$now = time();
$sent = array_filter($_SESSION['email_proxy_sent'] ?? [], function ($t) use ($now) {
    return ($now - (int)$t) < 600;
});
if (count($sent) >= 10) {
    pd_email_proxy_fail(429, 'Te veel e-mails verstuurd. Probeer het over een paar minuten opnieuw.');
}

// Read POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!is_array($data)) {
    pd_email_proxy_fail(400, 'Geen geldige JSON data ontvangen');
}

$to      = trim((string)($data['to'] ?? ''));
$subject = trim((string)($data['subject'] ?? 'BHV Overzicht'));
$message = (string)($data['message'] ?? '');

// Regeleinden uit het onderwerp (tegen header-injectie) en lengtes begrenzen
$subject = trim(preg_replace('/[\r\n]+/', ' ', $subject));
if ($subject === '') {
    $subject = 'BHV Overzicht';
}
$subject = mb_substr($subject, 0, 200);

// Validation
if ($to === '' || strlen($to) > 254 || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    pd_email_proxy_fail(400, 'Ongeldig e-mailadres');
}

if (trim($message) === '') {
    pd_email_proxy_fail(400, 'Bericht mag niet leeg zijn');
}
if (strlen($message) > 50000) {
    pd_email_proxy_fail(413, 'Bericht is te lang');
}

// Versturen via het centrale e-mailsysteem
$success = sendEmail($to, $subject, $message);

// Onthoud dit verzoek voor de begrenzing
$sent[] = $now;
$_SESSION['email_proxy_sent'] = array_values($sent);

// Return result
if ($success) {
    echo json_encode([
        'success' => true,
        'message' => 'Email succesvol verstuurd naar ' . $to
    ]);
} else {
    // Details staan in het e-maillog op de server; niet teruggeven aan de browser
    error_log('email-proxy: versturen mislukt: ' . json_encode(function_exists('getEmailErrors') ? getEmailErrors() : null));
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Email kon niet worden verstuurd. Check server mail configuratie.'
    ]);
}
