<?php
/**
 * AJAX: Test Google Sheets
 * 
 * NIET MEER IN GEBRUIK — PeopleDisplay v2.x gebruikt geen Google Sheets.
 */

header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'error' => 'Google Sheets is niet meer beschikbaar in PeopleDisplay v2.x.'
]);
