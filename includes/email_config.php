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
 * EMAIL CONFIGURATIE - CENTRAAL
 * ═══════════════════════════════════════════════════════════════
 * Locatie: /includes/email_config.php
 * 
 * ALLE email instellingen op ÉÉN plek!
 * Wordt aangemaakt door installer of handmatig.
 * ═══════════════════════════════════════════════════════════════
 */

// Prevent direct access
if (!defined('PEOPLEDISPLAY_LOADED')) {
    die('Direct access not allowed');
}

/**
 * Email Configuration
 * 
 * Deze instellingen worden gebruikt door ALLE email functionaliteit
 * in PeopleDisplay (bezoekers, notificaties, wachtwoord resets, etc.)
 */

return [
    
    // ═══════════════════════════════════════════════════════════
    // GENERAL SETTINGS
    // ═══════════════════════════════════════════════════════════
    
    /**
     * Email methode
     * 
     * 'mail'     - PHP mail() functie (eenvoudig, werkt op meeste servers)
     * 'smtp'     - SMTP met PHPMailer (vereist PHPMailer installatie)
     * 
     * AANBEVELING: Begin met 'mail', switch naar 'smtp' als het niet werkt
     */
    'method' => 'mail',  // 'mail' of 'smtp'
    
    /**
     * From Email & Name
     * 
     * Dit is het afzender adres dat ontvangers zien
     * Moet een geldig email adres zijn van jouw domein!
     * 
     * BELANGRIJK: Gebruik altijd het VOLLEDIGE domein
     * ✅ GOED:  noreply@jouwdomein.nl
     * ❌ FOUT: noreply@gmail.com (wordt geblokkeerd!)
     */
    'from_email' => 'noreply@' . $_SERVER['HTTP_HOST'],  // Auto-detect domain
    'from_name'  => 'PeopleDisplay',
    
    /**
     * Reply-To Email (optioneel)
     * 
     * Als ontvangers antwoorden, gaat het naar dit adres.
     * Laat leeg om from_email te gebruiken.
     */
    'reply_to_email' => '',  // Bijv: info@jouwdomein.nl
    'reply_to_name'  => '',
    
    
    // ═══════════════════════════════════════════════════════════
    // SMTP SETTINGS (alleen als method = 'smtp')
    // ═══════════════════════════════════════════════════════════
    
    'smtp' => [
        /**
         * SMTP Host
         * Voorbeelden:
         * - Strato:   smtp.strato.de
         * - TransIP:  smtp.transip.email  
         * - Gmail:    smtp.gmail.com (niet aanbevolen voor productie)
         */
        'host' => 'smtp.strato.de',
        
        /**
         * SMTP Port
         * - 25:  Onversleuteld (niet aanbevolen)
         * - 587: TLS/STARTTLS (aanbevolen)
         * - 465: SSL (legacy, maar werkt vaak goed)
         */
        'port' => 465,
        
        /**
         * SMTP Encryptie
         * - 'tls':  Voor poort 587 (aanbevolen)
         * - 'ssl':  Voor poort 465
         * - '':     Geen encryptie (niet aanbevolen)
         */
        'encryption' => 'ssl',  // 'tls', 'ssl' of ''
        
        /**
         * SMTP Authentication
         */
        'auth' => true,  // Bijna altijd true
        'username' => 'info@jouwdomein.nl',  // Volledige email adres
        'password' => '',  // ⚠️ VEILIG OPSLAAN! Nooit in git!
    ],
    
    
    // ═══════════════════════════════════════════════════════════
    // ADVANCED SETTINGS
    // ═══════════════════════════════════════════════════════════
    
    /**
     * Character Set
     */
    'charset' => 'UTF-8',
    
    /**
     * Email Format
     * - 'text': Plain text emails
     * - 'html': HTML emails (voor mooie opmaak)
     */
    'format' => 'text',  // 'text' of 'html'
    
    /**
     * Debug Mode
     * 
     * true:  Toon debug output (alleen tijdens development!)
     * false: Geen debug output (gebruik in productie)
     */
    'debug' => false,
    
    /**
     * Test Mode
     * 
     * true:  Emails worden NIET echt verzonden, alleen gelogd
     * false: Emails worden normaal verzonden
     * 
     * Handig voor testing zonder spam te versturen!
     */
    'test_mode' => false,
    
    /**
     * Log Emails
     * 
     * true:  Log alle verzonden emails naar database/log file
     * false: Geen logging
     */
    'log_emails' => true,
    
    /**
     * Max Retries
     * 
     * Als email verzenden faalt, hoeveel keer opnieuw proberen?
     */
    'max_retries' => 2,
    
    
    // ═══════════════════════════════════════════════════════════
    // EMAIL TEMPLATES
    // ═══════════════════════════════════════════════════════════
    
    /**
     * Template Settings
     * 
     * Als je HTML emails wilt (format = 'html'), kun je hier
     * template instellingen configureren.
     */
    'templates' => [
        'header_color' => '#667eea',  // Paars/blauw gradient
        'footer_text' => 'PeopleDisplay - Medewerker Aanwezigheid Systeem',
        'logo_url' => '',  // URL naar logo (optioneel)
    ],
    
    
    // ═══════════════════════════════════════════════════════════
    // VALIDATION
    // ═══════════════════════════════════════════════════════════
    
    /**
     * Config Validation
     * 
     * Wordt automatisch gecheckt door email_functions.php
     */
    'version' => '1.0',
    'last_updated' => '2026-02-02',
    
];

/**
 * ═══════════════════════════════════════════════════════════
 * USAGE VOORBEELDEN
 * ═══════════════════════════════════════════════════════════
 * 
 * In je PHP files:
 * 
 * require_once __DIR__ . '/includes/email_functions.php';
 * 
 * // Simpele email:
 * sendEmail('ontvanger@email.nl', 'Onderwerp', 'Bericht tekst');
 * 
 * // Email met opties:
 * sendEmail('ontvanger@email.nl', 'Onderwerp', 'Bericht', [
 *     'reply_to' => 'anders@email.nl',
 *     'cc' => ['kopie@email.nl'],
 *     'attachments' => ['/path/to/file.pdf']
 * ]);
 * 
 * // HTML email:
 * sendEmailHTML('ontvanger@email.nl', 'Onderwerp', '<h1>HTML!</h1>');
 * 
 * ═══════════════════════════════════════════════════════════
 */
