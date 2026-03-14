<?php
/**
 * ═══════════════════════════════════════════════════════════
 * EMAIL PROXY - UPDATED VOOR CENTRAAL EMAIL SYSTEEM
 * ═══════════════════════════════════════════════════════════
 * LOCATIE: /email-proxy.php (ROOT)
 * BESCHRIJVING: API endpoint voor BHV overzicht emails
 * 
 * VERSIE: 2.0 - Gebruikt email_functions.php
 * ═══════════════════════════════════════════════════════════
 */

header('Content-Type: application/json');

// Load centralized email system
require_once __DIR__ . '/includes/email_functions.php';

// Read POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    echo json_encode([
        'success' => false,
        'error' => 'Geen geldige JSON data ontvangen'
    ]);
    exit;
}

$to = $data['to'] ?? '';
$subject = $data['subject'] ?? 'BHV Overzicht';
$message = $data['message'] ?? '';

// Validation
if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'error' => 'Ongeldig e-mailadres'
    ]);
    exit;
}

if (empty($message)) {
    echo json_encode([
        'success' => false,
        'error' => 'Bericht mag niet leeg zijn'
    ]);
    exit;
}

// ✅ NIEUW: Gebruik centralized email system
// Dit gebruikt automatisch de correcte headers (Return-Path, -f parameter, etc.)
$success = sendEmail($to, $subject, $message);

// Return result
if ($success) {
    echo json_encode([
        'success' => true,
        'message' => 'Email succesvol verstuurd naar ' . $to
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Email kon niet worden verstuurd. Check server mail configuratie.',
        'debug' => getEmailErrors()  // Include errors for debugging
    ]);
}
