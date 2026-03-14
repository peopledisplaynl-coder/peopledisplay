<?php
/**
 * ═══════════════════════════════════════════════════════════
 * EMAIL HELPER - VISITOR EMAIL FUNCTIES
 * ═══════════════════════════════════════════════════════════
 * LOCATIE: /includes/email_helper.php
 * 
 * VERSIE: 2.0 - Gebruikt centralized email_functions.php
 * 
 * Bevat helper functies voor visitor emails:
 * - sendRegistrationEmail()
 * - sendCheckoutEmail()
 * - sendEmployeeNotification()
 * ═══════════════════════════════════════════════════════════
 */

// Load dependencies
require_once __DIR__ . '/email_functions.php';
require_once __DIR__ . '/db.php';

/**
 * Generate secure token
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * ═══════════════════════════════════════════════════════════
 * SEND REGISTRATION EMAIL TO VISITOR
 * ═══════════════════════════════════════════════════════════
 * 
 * Sent when visitor registers via visitor_register.php
 * Contains check-in link
 */
function sendRegistrationEmail($db, $visitorId) {
    // Fetch visitor data
    $stmt = $db->prepare("SELECT * FROM visitors WHERE id = ?");
    $stmt->execute([$visitorId]);
    $visitor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visitor) {
        logEmailError("Visitor not found: ID {$visitorId}");
        return false;
    }
    
    // Build check-in URL
    $checkinUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/visitor_checkin.php?token=' . $visitor['checkin_token'];
    
    // Format date
    if ($visitor['is_multi_day']) {
        $dateText = date('d-m-Y', strtotime($visitor['start_date'])) . ' t/m ' . date('d-m-Y', strtotime($visitor['end_date']));
    } else {
        $dateText = date('d-m-Y', strtotime($visitor['bezoek_datum']));
    }
    
    // Email content
    $subject = 'Bevestiging bezoekersregistratie - PeopleDisplay';
    $message = "Beste {$visitor['voornaam']},

U bent succesvol geregistreerd als bezoeker bij PeopleDisplay.

Gegevens:
━━━━━━━━━━━━━━━━
Naam: {$visitor['voornaam']} {$visitor['achternaam']}
Bedrijf: {$visitor['bedrijf']}
Locatie: {$visitor['locatie']}
Datum: {$dateText}
Tijd: {$visitor['tijd']}
Contactpersoon: {$visitor['contactpersoon']}

Bij aankomst:
━━━━━━━━━━━━━━━━
Klik op deze link om in te checken:
{$checkinUrl}

U wordt gevraagd akkoord te gaan met onze privacyverklaring.

Met vriendelijke groet,
PeopleDisplay

---
Deze link is 30 dagen geldig.
";

    // Send via centralized system
    return sendEmail($visitor['email'], $subject, $message);
}

/**
 * ═══════════════════════════════════════════════════════════
 * SEND CHECKOUT EMAIL WITH LINK
 * ═══════════════════════════════════════════════════════════
 * 
 * Sent when visitor checks in
 * Contains check-out link
 */
function sendCheckoutEmail($db, $visitorId) {
    // Fetch visitor data
    $stmt = $db->prepare("SELECT * FROM visitors WHERE id = ?");
    $stmt->execute([$visitorId]);
    $visitor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visitor) {
        logEmailError("Visitor not found: ID {$visitorId}");
        return false;
    }
    
    // Build checkout URL
    $checkoutUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/visitor_checkout.php?token=' . $visitor['checkout_token'];
    
    // Email content
    $subject = 'Check-out link - PeopleDisplay';
    $message = "Beste {$visitor['voornaam']},

Bedankt voor het inchecken bij {$visitor['locatie']}.

Wanneer u vertrekt:
━━━━━━━━━━━━━━━━
Klik op deze link om uit te checken:
{$checkoutUrl}

Dit helpt ons om een actueel overzicht te houden van aanwezige bezoekers.

Met vriendelijke groet,
PeopleDisplay

---
Deze link is 30 dagen geldig.
";

    // Send via centralized system
    return sendEmail($visitor['email'], $subject, $message);
}

/**
 * ═══════════════════════════════════════════════════════════
 * NOTIFY EMPLOYEE ABOUT VISITOR
 * ═══════════════════════════════════════════════════════════
 * 
 * @param object $db         Database connection
 * @param int    $visitorId  Visitor ID
 * @param string $type       'registration' or 'checkin'
 * @return bool              Success
 */
function sendEmployeeNotification($db, $visitorId, $type) {
    global $EMAIL_CONFIG;
    
    // Fetch visitor data
    $stmt = $db->prepare("SELECT * FROM visitors WHERE id = ?");
    $stmt->execute([$visitorId]);
    $visitor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visitor) {
        logEmailError("Visitor not found for notification: ID {$visitorId}");
        return false;
    }
    
    // Find employee email
    // Try exact match first
    $stmt = $db->prepare("
        SELECT naam, email FROM employees 
        WHERE naam = ? 
        AND locatie = ? 
        AND email IS NOT NULL 
        AND email != ''
        AND actief = 1
        LIMIT 1
    ");
    $stmt->execute([
        $visitor['contactpersoon'],
        $visitor['locatie']
    ]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If not found, try LIKE search
    if (!$employee) {
        $stmt = $db->prepare("
            SELECT naam, email FROM employees 
            WHERE naam LIKE ? 
            AND locatie = ? 
            AND email IS NOT NULL 
            AND email != ''
            AND actief = 1
            LIMIT 1
        ");
        $stmt->execute([
            '%' . $visitor['contactpersoon'] . '%',
            $visitor['locatie']
        ]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$employee || empty($employee['email'])) {
        logEmail("No email found for employee: {$visitor['contactpersoon']} at {$visitor['locatie']}");
        return false;  // Not an error, just no email available
    }
    
    // Format date
    if ($visitor['is_multi_day']) {
        $dateText = date('d-m-Y', strtotime($visitor['start_date'])) . ' t/m ' . date('d-m-Y', strtotime($visitor['end_date']));
    } else {
        $dateText = date('d-m-Y', strtotime($visitor['bezoek_datum']));
    }
    
    // Build email based on type
    if ($type === 'registration') {
        // Visitor registered - notify contact person
        $subject = "Bezoeker aangemeld - {$visitor['voornaam']} {$visitor['achternaam']} op {$dateText}";
        $message = "Beste {$employee['naam']},

Er is een bezoeker voor u aangemeld:

Bezoeker Gegevens:
━━━━━━━━━━━━━━━━
Naam: {$visitor['voornaam']} {$visitor['achternaam']}
Bedrijf: {$visitor['bedrijf']}
Email: {$visitor['email']}
Telefoon: {$visitor['telefoon']}

Bezoek Details:
━━━━━━━━━━━━━━━━
Datum: {$dateText}
Tijd: {$visitor['tijd']}
Locatie: {$visitor['locatie']}

De bezoeker zal zich bij aankomst inchecken via email link.
U ontvangt dan een notificatie.

Met vriendelijke groet,
PeopleDisplay
";
        
    } elseif ($type === 'checkin') {
        // Visitor checked in - notify contact person
        $subject = "Bezoeker gearriveerd - {$visitor['voornaam']} {$visitor['achternaam']}";
        $message = "Beste {$employee['naam']},

Uw bezoeker is gearriveerd en heeft ingecheckt:

Bezoeker:
━━━━━━━━━━━━━━━━
Naam: {$visitor['voornaam']} {$visitor['achternaam']}
Bedrijf: {$visitor['bedrijf']}
Email: {$visitor['email']}

Check-in:
━━━━━━━━━━━━━━━━
Tijd: " . date('H:i') . "
Locatie: {$visitor['locatie']}

Met vriendelijke groet,
PeopleDisplay
";
        
    } else {
        logEmailError("Unknown notification type: {$type}");
        return false;
    }
    
    // Send via centralized system
    return sendEmail($employee['email'], $subject, $message);
}

/**
 * ═══════════════════════════════════════════════════════════
 * HELPER: Get visitor full name
 * ═══════════════════════════════════════════════════════════
 */
function getVisitorFullName($visitor) {
    return trim($visitor['voornaam'] . ' ' . $visitor['achternaam']);
}

/**
 * ═══════════════════════════════════════════════════════════
 * HELPER: Format visitor date range
 * ═══════════════════════════════════════════════════════════
 */
function getVisitorDateRange($visitor) {
    if ($visitor['is_multi_day']) {
        return date('d-m-Y', strtotime($visitor['start_date'])) . ' t/m ' . 
               date('d-m-Y', strtotime($visitor['end_date']));
    } else {
        return date('d-m-Y', strtotime($visitor['bezoek_datum']));
    }
}
