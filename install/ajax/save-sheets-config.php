<?php
/**
 * AJAX: Google Sheets configuratie
 * 
 * NIET MEER IN GEBRUIK — PeopleDisplay v2.x gebruikt geen Google Sheets.
 * Dit bestand is alleen aanwezig voor backwards compatibiliteit.
 */

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Google Sheets is niet meer beschikbaar in PeopleDisplay v2.x. Sla dit scherm over.'
]);
