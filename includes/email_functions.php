<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * ═══════════════════════════════════════════════════════════════
 * EMAIL FUNCTIES - CENTRAAL
 * ═══════════════════════════════════════════════════════════════
 * Locatie: /includes/email_functions.php
 * 
 * Alle email functionaliteit op ÉÉN plek!
 * Gebruikt door: visitor_register, notificaties, wachtwoord reset, etc.
 * 
 * GEBRUIK:
 *   require_once __DIR__ . '/includes/email_functions.php';
 *   sendEmail('naar@email.nl', 'Onderwerp', 'Bericht');
 * ═══════════════════════════════════════════════════════════════
 */

// Prevent direct access
if (!defined('PEOPLEDISPLAY_LOADED')) {
    define('PEOPLEDISPLAY_LOADED', true);
}

// Load configuration
$EMAIL_CONFIG = require_once __DIR__ . '/email_config.php';

// Initialize globals
$EMAIL_LOG = [];
$EMAIL_ERRORS = [];

/**
 * ═══════════════════════════════════════════════════════════════
 * MAIN FUNCTION - Verstuur een email
 * ═══════════════════════════════════════════════════════════════
 * 
 * @param string $to         Email adres ontvanger
 * @param string $subject    Onderwerp
 * @param string $message    Bericht (plain text of HTML)
 * @param array  $options    Extra opties (zie hieronder)
 * @return bool              True als verzonden, false bij fout
 * 
 * OPTIONS:
 *   'reply_to'     => 'email@domain.nl'  - Reply-To adres
 *   'cc'           => ['email1', 'email2'] - CC ontvangers
 *   'bcc'          => ['email1', 'email2'] - BCC ontvangers
 *   'attachments'  => ['/path/file.pdf']  - Attachments
 *   'html'         => true/false           - HTML email (default: false)
 *   'from_name'    => 'Anders Naam'        - Override from name
 *   'from_email'   => 'anders@email.nl'    - Override from email
 */
function sendEmail($to, $subject, $message, $options = []) {
    global $EMAIL_CONFIG, $EMAIL_LOG, $EMAIL_ERRORS;
    
    // Validate inputs
    if (empty($to) || empty($subject) || empty($message)) {
        logEmailError("Missing required fields: to, subject, or message");
        return false;
    }
    
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        logEmailError("Invalid email address: {$to}");
        return false;
    }
    
    // Test mode - don't actually send
    if ($EMAIL_CONFIG['test_mode']) {
        logEmail("TEST MODE: Email zou verzonden worden naar {$to}");
        logEmail("Subject: {$subject}");
        logEmail("Message: " . substr($message, 0, 100) . "...");
        return true;
    }
    
    // Choose method
    if ($EMAIL_CONFIG['method'] === 'smtp') {
        return sendEmailSMTP($to, $subject, $message, $options);
    } else {
        return sendEmailMail($to, $subject, $message, $options);
    }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * METHOD 1: PHP mail() functie (WERKT OP MEESTE SERVERS)
 * ═══════════════════════════════════════════════════════════════
 */
function sendEmailMail($to, $subject, $message, $options = []) {
    global $EMAIL_CONFIG, $EMAIL_LOG;
    
    // Get config
    $from_email = $options['from_email'] ?? $EMAIL_CONFIG['from_email'];
    $from_name = $options['from_name'] ?? $EMAIL_CONFIG['from_name'];
    $charset = $EMAIL_CONFIG['charset'];
    $is_html = $options['html'] ?? ($EMAIL_CONFIG['format'] === 'html');
    
    // Build headers - EXACTE methode zoals Test 2 die werkt!
    $headers = [];
    
    // From header
    $headers[] = "From: {$from_name} <{$from_email}>";
    
    // Reply-To
    $reply_to = $options['reply_to'] ?? $EMAIL_CONFIG['reply_to_email'];
    if (!empty($reply_to)) {
        $headers[] = "Reply-To: {$reply_to}";
    } else {
        $headers[] = "Reply-To: {$from_email}";
    }
    
    // Return-Path (BELANGRIJK!)
    $headers[] = "Return-Path: {$from_email}";
    
    // CC
    if (!empty($options['cc'])) {
        $cc_list = is_array($options['cc']) ? implode(', ', $options['cc']) : $options['cc'];
        $headers[] = "Cc: {$cc_list}";
    }
    
    // BCC
    if (!empty($options['bcc'])) {
        $bcc_list = is_array($options['bcc']) ? implode(', ', $options['bcc']) : $options['bcc'];
        $headers[] = "Bcc: {$bcc_list}";
    }
    
    // X-Mailer
    $headers[] = "X-Mailer: PHP/" . phpversion();
    
    // MIME Version
    $headers[] = "MIME-Version: 1.0";
    
    // Content-Type
    if ($is_html) {
        $headers[] = "Content-Type: text/html; charset={$charset}";
    } else {
        $headers[] = "Content-Type: text/plain; charset={$charset}";
    }
    
    // Join headers
    $headers_string = implode("\r\n", $headers);
    
    // Additional parameters (BELANGRIJK: -f flag!)
    $additional_params = "-f{$from_email}";
    
    // Send email
    $sent = @mail($to, $subject, $message, $headers_string, $additional_params);
    
    // Log result
    if ($sent) {
        logEmail("✅ Email verzonden via mail() naar: {$to}");
        logEmailToDatabase($to, $subject, $message, 'mail', 'sent');
        return true;
    } else {
        $error = error_get_last();
        $error_msg = $error ? $error['message'] : 'Unknown error';
        logEmailError("❌ Email NIET verzonden naar {$to}: {$error_msg}");
        logEmailToDatabase($to, $subject, $message, 'mail', 'failed', $error_msg);
        return false;
    }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * METHOD 2: SMTP met PHPMailer (VOOR ALS mail() NIET WERKT)
 * ═══════════════════════════════════════════════════════════════
 */
function sendEmailSMTP($to, $subject, $message, $options = []) {
    global $EMAIL_CONFIG, $EMAIL_LOG;
    
    // Check if PHPMailer is available
    $phpmailerPaths = [
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/PHPMailer/PHPMailer.php',
    ];
    
    $phpmailerLoaded = false;
    foreach ($phpmailerPaths as $path) {
        if (file_exists($path)) {
            if (strpos($path, 'autoload.php') !== false) {
                require_once $path;
                $phpmailerLoaded = true;
            } else {
                require_once dirname($path) . '/PHPMailer.php';
                require_once dirname($path) . '/SMTP.php';
                require_once dirname($path) . '/Exception.php';
                $phpmailerLoaded = true;
            }
            break;
        }
    }
    
    if (!$phpmailerLoaded) {
        logEmailError("PHPMailer niet gevonden! Installeer via: composer require phpmailer/phpmailer");
        return false;
    }
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Debug mode
        if ($EMAIL_CONFIG['debug']) {
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function($str) {
                logEmail("SMTP: " . $str);
            };
        }
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $EMAIL_CONFIG['smtp']['host'];
        $mail->SMTPAuth = $EMAIL_CONFIG['smtp']['auth'];
        $mail->Username = $EMAIL_CONFIG['smtp']['username'];
        $mail->Password = $EMAIL_CONFIG['smtp']['password'];
        
        // Encryption
        if ($EMAIL_CONFIG['smtp']['encryption'] === 'tls') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($EMAIL_CONFIG['smtp']['encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        }
        
        $mail->Port = $EMAIL_CONFIG['smtp']['port'];
        $mail->CharSet = $EMAIL_CONFIG['charset'];
        
        // From
        $from_email = $options['from_email'] ?? $EMAIL_CONFIG['from_email'];
        $from_name = $options['from_name'] ?? $EMAIL_CONFIG['from_name'];
        $mail->setFrom($from_email, $from_name);
        
        // Reply-To
        $reply_to = $options['reply_to'] ?? $EMAIL_CONFIG['reply_to_email'];
        if (!empty($reply_to)) {
            $reply_name = $options['reply_to_name'] ?? $EMAIL_CONFIG['reply_to_name'];
            $mail->addReplyTo($reply_to, $reply_name);
        }
        
        // To
        $mail->addAddress($to);
        
        // CC
        if (!empty($options['cc'])) {
            $cc_list = is_array($options['cc']) ? $options['cc'] : [$options['cc']];
            foreach ($cc_list as $cc_email) {
                $mail->addCC($cc_email);
            }
        }
        
        // BCC
        if (!empty($options['bcc'])) {
            $bcc_list = is_array($options['bcc']) ? $options['bcc'] : [$options['bcc']];
            foreach ($bcc_list as $bcc_email) {
                $mail->addBCC($bcc_email);
            }
        }
        
        // Attachments
        if (!empty($options['attachments'])) {
            $attachments = is_array($options['attachments']) ? $options['attachments'] : [$options['attachments']];
            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    $mail->addAttachment($attachment);
                }
            }
        }
        
        // Content
        $is_html = $options['html'] ?? ($EMAIL_CONFIG['format'] === 'html');
        $mail->isHTML($is_html);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        // Send
        $mail->send();
        
        logEmail("✅ Email verzonden via SMTP naar: {$to}");
        logEmailToDatabase($to, $subject, $message, 'smtp', 'sent');
        return true;
        
    } catch (Exception $e) {
        logEmailError("❌ SMTP Error: {$mail->ErrorInfo}");
        logEmailToDatabase($to, $subject, $message, 'smtp', 'failed', $mail->ErrorInfo);
        return false;
    }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * HTML EMAIL HELPER
 * ═══════════════════════════════════════════════════════════════
 */
function sendEmailHTML($to, $subject, $html_message, $options = []) {
    $options['html'] = true;
    return sendEmail($to, $subject, $html_message, $options);
}

/**
 * ═══════════════════════════════════════════════════════════════
 * TEMPLATE EMAIL - Voor herbruikbare email types
 * ═══════════════════════════════════════════════════════════════
 */
function sendEmailTemplate($to, $template, $variables = [], $options = []) {
    global $EMAIL_CONFIG;
    
    $templates = [
        
        // Bezoeker bevestiging
        'visitor_confirmation' => [
            'subject' => 'Bevestiging bezoekersregistratie - PeopleDisplay',
            'message' => "Beste {visitor_name},

Je bent succesvol geregistreerd als bezoeker bij PeopleDisplay.

Gegevens:
━━━━━━━━━━━━━━━━
Naam: {visitor_name}
Bedrijf: {visitor_company}
Locatie: {location_name}
Datum: {expected_date}
Tijd: {expected_time}

Bij aankomst kun je inchecken via de PeopleDisplay interface.

Met vriendelijke groet,
PeopleDisplay"
        ],
        
        // Contactpersoon notificatie
        'contact_notification' => [
            'subject' => 'Bezoeker aangemeld - {visitor_name} op {expected_date}',
            'message' => "Beste {contact_person},

Er is een bezoeker voor je aangemeld:

Bezoeker: {visitor_name}
Bedrijf: {visitor_company}
Email: {visitor_email}
Datum: {expected_date}
Tijd: {expected_time}
Locatie: {location_name}

De bezoeker zal zich bij aankomst inchecken.

Met vriendelijke groet,
PeopleDisplay"
        ],
        
        // Bezoeker check-in notificatie
        'visitor_checkin' => [
            'subject' => 'Bezoeker {visitor_name} is gearriveerd',
            'message' => "Beste {contact_person},

Je bezoeker is gearriveerd en heeft ingecheckt:

Bezoeker: {visitor_name}
Bedrijf: {visitor_company}
Tijd: {checkin_time}
Locatie: {location_name}

Met vriendelijke groet,
PeopleDisplay"
        ],
        
        // Wachtwoord reset
        'password_reset' => [
            'subject' => 'Wachtwoord reset - PeopleDisplay',
            'message' => "Beste {display_name},

Je hebt een wachtwoord reset aangevraagd voor je PeopleDisplay account.

Klik op deze link om je wachtwoord te resetten:
{reset_link}

Deze link is 24 uur geldig.

Heb je deze aanvraag niet gedaan? Negeer dan deze email.

Met vriendelijke groet,
PeopleDisplay"
        ],
        
    ];
    
    if (!isset($templates[$template])) {
        logEmailError("Template '{$template}' niet gevonden");
        return false;
    }
    
    $template_data = $templates[$template];
    
    // Replace variables
    $subject = $template_data['subject'];
    $message = $template_data['message'];
    
    foreach ($variables as $key => $value) {
        $subject = str_replace('{' . $key . '}', $value, $subject);
        $message = str_replace('{' . $key . '}', $value, $message);
    }
    
    return sendEmail($to, $subject, $message, $options);
}

/**
 * ═══════════════════════════════════════════════════════════════
 * LOGGING FUNCTIES
 * ═══════════════════════════════════════════════════════════════
 */
function logEmail($message) {
    global $EMAIL_LOG, $EMAIL_CONFIG;
    $EMAIL_LOG[] = date('Y-m-d H:i:s') . ' - ' . $message;
    
    if ($EMAIL_CONFIG['debug']) {
        error_log("EMAIL: " . $message);
    }
}

function logEmailError($error) {
    global $EMAIL_ERRORS, $EMAIL_CONFIG;
    $EMAIL_ERRORS[] = date('Y-m-d H:i:s') . ' - ' . $error;
    error_log("EMAIL ERROR: " . $error);
}

function logEmailToDatabase($to, $subject, $message, $method, $status, $error = null) {
    global $EMAIL_CONFIG;
    
    if (!$EMAIL_CONFIG['log_emails']) {
        return;
    }
    
    // Optioneel: Log naar database
    // Vereist email_log tabel (zie installer)
    try {
        if (isset($GLOBALS['db'])) {
            $db = $GLOBALS['db'];
            $stmt = $db->prepare("
                INSERT INTO email_log 
                (to_email, subject, message, method, status, error, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $to,
                $subject,
                substr($message, 0, 1000),  // Truncate long messages
                $method,
                $status,
                $error
            ]);
        }
    } catch (Exception $e) {
        // Ignore database errors voor logging
    }
}

/**
 * Get email log
 */
function getEmailLog() {
    global $EMAIL_LOG;
    return $EMAIL_LOG;
}

/**
 * Get email errors
 */
function getEmailErrors() {
    global $EMAIL_ERRORS;
    return $EMAIL_ERRORS;
}

/**
 * ═══════════════════════════════════════════════════════════════
 * CONFIG VALIDATION
 * ═══════════════════════════════════════════════════════════════
 */
function validateEmailConfig() {
    global $EMAIL_CONFIG;
    
    $errors = [];
    
    // Check method
    if (!in_array($EMAIL_CONFIG['method'], ['mail', 'smtp'])) {
        $errors[] = "Invalid email method: {$EMAIL_CONFIG['method']}";
    }
    
    // Check from email
    if (empty($EMAIL_CONFIG['from_email']) || !filter_var($EMAIL_CONFIG['from_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid from_email: {$EMAIL_CONFIG['from_email']}";
    }
    
    // Check SMTP config if using SMTP
    if ($EMAIL_CONFIG['method'] === 'smtp') {
        if (empty($EMAIL_CONFIG['smtp']['host'])) {
            $errors[] = "SMTP host not configured";
        }
        if (empty($EMAIL_CONFIG['smtp']['username'])) {
            $errors[] = "SMTP username not configured";
        }
        if (empty($EMAIL_CONFIG['smtp']['password'])) {
            $errors[] = "SMTP password not configured";
        }
    }
    
    return $errors;
}

// Auto-validate on load
$config_errors = validateEmailConfig();
if (!empty($config_errors)) {
    foreach ($config_errors as $error) {
        logEmailError("CONFIG ERROR: " . $error);
    }
}
