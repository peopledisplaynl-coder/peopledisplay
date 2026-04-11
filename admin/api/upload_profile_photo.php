<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * ═══════════════════════════════════════════════════════════════════
 * BESTANDSNAAM: upload_profile_photo.php
 * LOCATIE:      /admin/api/upload_profile_photo.php
 * BESCHRIJVING: Upload user profile photo
 * ═══════════════════════════════════════════════════════════════════
 */

session_start();
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Check if file was uploaded
    if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Geen bestand geüpload');
    }
    
    $file = $_FILES['profile_photo'];
    
    // Validate file type
    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime, $allowed)) {
        throw new Exception('Alleen afbeeldingen toegestaan (JPG, PNG, GIF, WEBP)');
    }
    
    // Validate file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('Bestand te groot (max 5MB)');
    }
    
    // Create uploads directory if not exists
    $uploadDir = __DIR__ . '/../../uploads/profiles/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . $user_id . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    $dbPath = 'uploads/profiles/' . $filename;
    
    // Get old photo to delete
    $stmt = $db->prepare("SELECT profile_photo FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $oldPhoto = $stmt->fetchColumn();
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Upload mislukt');
    }
    
    // Update database
    $stmt = $db->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
    $stmt->execute([$dbPath, $user_id]);
    
    // Delete old photo if exists
    if ($oldPhoto && file_exists(__DIR__ . '/../../' . $oldPhoto)) {
        unlink(__DIR__ . '/../../' . $oldPhoto);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Foto succesvol geüpload',
        'photo_path' => $dbPath
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
