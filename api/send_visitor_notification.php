<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
// /api/send_visitor_notification.php
// Verstuurt email notificatie bij bezoeker aanmelding

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$visitor_id = $data['visitor_id'] ?? null;
$employee_naam = $data['employee_naam'] ?? null;
$notification_type = $data['type'] ?? 'registration'; // 'registration' or 'checkin'

if (!$visitor_id) {
    echo json_encode(['success' => false, 'error' => 'Missing visitor_id']);
    exit;
}

try {
    // Get visitor details
    $stmt = $db->prepare("
        SELECT naam, bedrijf, email, telefoon, bezoek_datum, bezoek_tijd, locatie, doel_bezoek
        FROM visitors 
        WHERE id = ?
    ");
    $stmt->execute([$visitor_id]);
    $visitor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visitor) {
        echo json_encode(['success' => false, 'error' => 'Visitor not found']);
        exit;
    }
    
    // Get employee email if employee_naam provided
    $employee_email = null;
    if ($employee_naam) {
        $stmt = $db->prepare("SELECT email FROM employees WHERE naam = ? LIMIT 1");
        $stmt->execute([$employee_naam]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);
        $employee_email = $emp['email'] ?? null;
    }
    
    // Get fallback email from config
    $stmt = $db->query("SELECT visitor_notification_fallback_email FROM config WHERE id = 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    $fallback_email = $config['visitor_notification_fallback_email'] ?? null;
    
    // Determine recipient
    $to_email = $employee_email ?: $fallback_email;
    
    if (!$to_email) {
        echo json_encode([
            'success' => false, 
            'error' => 'No email address available (neither employee nor fallback)'
        ]);
        exit;
    }
    
    // Format date/time nicely
    $datum = $visitor['bezoek_datum'] ? date('d-m-Y', strtotime($visitor['bezoek_datum'])) : 'Onbekend';
    $tijd = $visitor['bezoek_tijd'] ?: 'Onbekend';
    
    // Build email based on type
    if ($notification_type === 'checkin') {
        $subject = "✅ Bezoeker is gearriveerd - " . $visitor['naam'];
        $message = "Beste " . ($employee_naam ?: 'collega') . ",\n\n";
        $message .= "Uw bezoeker is zojuist ingecheckt:\n\n";
    } else {
        $subject = "📅 Nieuwe bezoeker aangemeld - " . $visitor['naam'];
        $message = "Beste " . ($employee_naam ?: 'collega') . ",\n\n";
        $message .= "Er is een bezoeker voor u aangemeld:\n\n";
    }
    
    $message .= "Bezoeker: " . $visitor['naam'] . "\n";
    if ($visitor['bedrijf']) {
        $message .= "Bedrijf: " . $visitor['bedrijf'] . "\n";
    }
    $message .= "Datum: " . $datum . "\n";
    $message .= "Tijd: " . $tijd . "\n";
    if ($visitor['locatie']) {
        $message .= "Locatie: " . $visitor['locatie'] . "\n";
    }
    if ($visitor['doel_bezoek']) {
        $message .= "Doel bezoek: " . $visitor['doel_bezoek'] . "\n";
    }
    $message .= "\n";
    
    if ($notification_type === 'checkin') {
        $message .= "De bezoeker wacht op u.\n";
    } else {
        $message .= "U ontvangt een nieuwe melding wanneer de bezoeker incheckt.\n";
    }
    
    $message .= "\n---\n";
    $message .= "Dit is een automatisch gegenereerd bericht van PeopleDisplay.\n";
    
    // Email headers — use server domain for From address
    $fromDomain = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $fromEmail  = 'noreply@' . $fromDomain;
    $headers = "From: PeopleDisplay <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    // Send email
    $sent = mail($to_email, $subject, $message, $headers);
    
    if ($sent) {
        echo json_encode([
            'success' => true,
            'sent_to' => $to_email,
            'type' => $employee_email ? 'employee' : 'fallback'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to send email'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
