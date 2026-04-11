<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * ============================================================================
 * BESTANDSNAAM:  employees_delete.php
 * UPLOAD NAAR:   /admin/employees_delete.php
 * ============================================================================
 */

require_once __DIR__ . '/auth_helper.php';
requireAdmin();

require_once __DIR__ . '/../includes/db.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: employees_manage.php');
    exit;
}

// Soft delete - set actief = 0
$stmt = $db->prepare("UPDATE employees SET actief = 0 WHERE id = ?");
$stmt->execute([$id]);

// Redirect back
header('Location: employees_manage.php?deleted=1');
exit;
