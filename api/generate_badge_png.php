<?php
// Badge PNG Exporter
// Upload naar: /api/generate_badge_png.php

error_reporting(E_ALL);
ini_set('display_errors', 0);

function jsonError($msg, $code = 500) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

try {
    session_start();
    require_once __DIR__ . '/../includes/db.php';

    if (!isset($_SESSION['user_id'])) {
        jsonError('Niet ingelogd', 401);
    }

    $stmt = $db->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || !in_array($user['role'], ['admin', 'superadmin'])) {
        jsonError('Geen toegang', 403);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data) || empty($data['employee_ids'])) {
        jsonError('Geen employees opgegeven', 400);
    }

    $ids = array_values(array_filter(array_map('intval', $data['employee_ids']), fn($v) => $v > 0));
    if (empty($ids)) {
        jsonError('Geen geldige employee IDs', 400);
    }

    $codeType = $data['code_type'] ?? 'qr';
    $template = $data['template'] ?? 'professional';
    $logoData = $data['logo'] ?? null;

    // Employee data fetch (same logic as generate_badges.php)
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT id, employee_id, naam, voornaam, achternaam, functie, afdeling, locatie, foto_url, bhv FROM employees WHERE id IN($ph) AND actief=1 ORDER BY naam");
    $stmt->execute($ids);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($employees)) {
        jsonError('Geen employees gevonden', 404);
    }

    // Prepare ZIP
    $tmpZip = sys_get_temp_dir() . '/badges_png_' . uniqid() . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        jsonError('Kan ZIP niet aanmaken', 500);
    }

    $tempFiles = []; // Store temp file paths for cleanup after ZIP creation

    // Template colors
    $templateBackgrounds = [
        'professional' => [230, 236, 255],
        'colorful' => [255, 237, 241],
        'minimalist' => [255, 255, 255],
        'emergency' => [255, 236, 238],
    ];

    $templateAccent = [
        'professional' => [51, 102, 204],
        'colorful' => [252, 112, 156],
        'minimalist' => [40, 40, 40],
        'emergency' => [220, 38, 38],
    ];

    foreach ($employees as $employee) {
        // Create base image
        $img = imagecreatetruecolor(600, 400);
        $bg = $templateBackgrounds[$template] ?? $templateBackgrounds['professional'];
        $bgColor = imagecolorallocate($img, $bg[0], $bg[1], $bg[2]);
        imagefilledrectangle($img, 0, 0, 599, 399, $bgColor);

        $accent = $templateAccent[$template] ?? $templateAccent['professional'];
        $accentColor = imagecolorallocate($img, $accent[0], $accent[1], $accent[2]);
        $black = imagecolorallocate($img, 0, 0, 0);
        $darkGray = imagecolorallocate($img, 54, 69, 79);
        $white = imagecolorallocate($img, 255, 255, 255);
        $red = imagecolorallocate($img, 220, 38, 38);

        // Header bar
        imagefilledrectangle($img, 0, 0, 599, 70, $accentColor);

        // Name
        $name = trim($employee['voornaam'] . ' ' . $employee['achternaam']);
        if ($name === '') { $name = $employee['naam'] ?: 'Onbekend'; }
        imagestring($img, 5, 18, 80, $name, $black);

        // Functie
        $functie = $employee['functie'] ?: 'Functie onbekend';
        imagestring($img, 3, 18, 115, 'Functie: ' . $functie, $darkGray);

        // Afdeling
        $afdeling = $employee['afdeling'] ?: 'Onbekend';
        imagestring($img, 3, 18, 140, 'Afdeling: ' . $afdeling, $darkGray);

        // Locatie
        $locatie = $employee['locatie'] ?: 'Onbekend';
        imagestring($img, 3, 18, 165, 'Locatie: ' . $locatie, $darkGray);

        // BHV badge
        if (trim(strtolower($employee['bhv'] ?? '')) === 'ja') {
            imagefilledellipse($img, 525, 60, 100, 100, $red);
            imagestring($img, 5, 485, 50, 'BHV', $white);
        }

        // Profile photo or placeholder
        $photoAreaX = 18;
        $photoAreaY = 230;
        $photoSize = 120;
        imagefilledellipse($img, $photoAreaX + $photoSize / 2, $photoAreaY + $photoSize / 2, $photoSize, $photoSize, $white);

        if (!empty($employee['foto_url'])) {
            $photoData = @file_get_contents($employee['foto_url']);
            if ($photoData !== false) {
                $src = @imagecreatefromstring($photoData);
                if ($src) {
                    $temp = imagecreatetruecolor($photoSize, $photoSize);
                    imagefill($temp, 0, 0, $white);
                    imagecopyresampled($temp, $src, 0, 0, 0, 0, $photoSize, $photoSize, imagesx($src), imagesy($src));
                    imagecopy($img, $temp, $photoAreaX, $photoAreaY, 0, 0, $photoSize, $photoSize);
                    imagedestroy($temp);
                    imagedestroy($src);
                } else {
                    // fallback placeholder initials
                    $initials = strtoupper(substr($employee['voornaam'],0,1) . substr($employee['achternaam'],0,1));
                    imagestring($img, 5, $photoAreaX + 35, $photoAreaY + 50, $initials, $darkGray);
                }
            } else {
                $initials = strtoupper(substr($employee['voornaam'],0,1) . substr($employee['achternaam'],0,1));
                imagestring($img, 5, $photoAreaX + 35, $photoAreaY + 50, $initials, $darkGray);
            }
        } else {
            $initials = strtoupper(substr($employee['voornaam'],0,1) . substr($employee['achternaam'],0,1));
            imagestring($img, 5, $photoAreaX + 35, $photoAreaY + 50, $initials, $darkGray);
        }

        // QR/Barcode
        $codeAreaX = 390;
        $codeAreaY = 220;

        if ($codeType === 'barcode') {
            // Simple visual barcode placeholder
            imagefilledrectangle($img, $codeAreaX, $codeAreaY, $codeAreaX + 180, $codeAreaY + 110, $white);
            $codeValue = $employee['employee_id'] ?: $employee['id'];
            imagestring($img, 3, $codeAreaX + 8, $codeAreaY + 45, 'BAR:' . $codeValue, $black);
            for ($i = 0; $i < 20; $i++) {
                $x = $codeAreaX + 10 + $i * 8;
                $h = rand(60, 100);
                imageline($img, $x, $codeAreaY + 10, $x, $codeAreaY + 10 + $h, $black);
            }
        } else {
            $codeValue = $employee['employee_id'] ?: $employee['id'];
            $qrUrl = 'https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=' . urlencode($codeValue);
            $qrData = @file_get_contents($qrUrl);
            if ($qrData !== false) {
                $qr = @imagecreatefromstring($qrData);
                if ($qr) {
                    imagecopyresampled($img, $qr, $codeAreaX, $codeAreaY, 0, 0, 150, 150, imagesx($qr), imagesy($qr));
                    imagedestroy($qr);
                }
            } else {
                imagefilledrectangle($img, $codeAreaX, $codeAreaY, $codeAreaX + 150, $codeAreaY + 150, $white);
                imagestring($img, 3, $codeAreaX + 5, $codeAreaY + 65, $codeValue, $black);
            }

            if ($codeType === 'both') {
                // add barcode below QR as fallback
                $bcY = $codeAreaY + 160;
                imagefilledrectangle($img, $codeAreaX, $bcY, $codeAreaX + 180, $bcY + 20, $white);
                imagestring($img, 3, $codeAreaX + 8, $bcY + 4, 'BAR:' . $codeValue, $black);
            }
        }

        // Draw label
        imagestring($img, 3, $codeAreaX, $codeAreaY + 165, 'ID: ' . ($employee['employee_id'] ?? $employee['id']), $darkGray);

        // Save image to temp file
        $fileName = 'badge_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($employee['voornaam'] . '_' . $employee['achternaam'])) . '.png';
        $tmpPng = tempnam(sys_get_temp_dir(), 'badge_') . '.png';
        imagepng($img, $tmpPng);
        imagedestroy($img);

        $zip->addFile($tmpPng, $fileName);
        $tempFiles[] = $tmpPng; // Store for cleanup after ZIP close
    }

    $zip->close();

    // Clean up temp PNG files after ZIP is closed
    foreach ($tempFiles as $tempFile) {
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="badges_png_' . date('Ymd_His') . '.zip"');
    header('Content-Length: ' . filesize($tmpZip));

    readfile($tmpZip);
    unlink($tmpZip);
    exit;
} catch (Exception $e) {
    jsonError('PNG generatiefout: ' . $e->getMessage(), 500);
}
