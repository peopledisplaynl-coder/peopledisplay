<?php
/**
 * ============================================================================
 * BESTANDSNAAM:  logout.php
 * UPLOAD NAAR:   /admin/logout.php (OVERSCHRIJF)
 * DATUM:         2024-12-04
 * VERSIE:        FIXED - Line 21
 * 
 * FIX: Changed login.html → ../login.php (Line 21)
 * NOTE: Dit is /admin/logout.php, NIET root /logout.php!
 * ============================================================================
 */

session_start();

// Destroy session
session_unset();
session_destroy();

// FIXED LINE 21: Was login.html
header('Location: ../login.php');  // ✅ FIXED
exit;
