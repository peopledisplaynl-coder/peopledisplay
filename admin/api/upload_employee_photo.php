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
 * BESTANDSNAAM: upload_employee_photo.php
 * LOCATIE:      /admin/api/upload_employee_photo.php
 * BESCHRIJVING: Upload + comprimeer een medewerkersfoto (voor employees.foto_url).
 *               Slaat op in /images/ (zelfde map als BASE_PHOTO_URL in app.js
 *               gebruikt) en geeft een absolute URL terug, zodat zowel de
 *               kaartweergave als pagina's die foto_url rechtstreeks als
 *               <img src> gebruiken (bv. badges) 'm correct kunnen tonen.
 * ═══════════════════════════════════════════════════════════════════
 */

session_start();
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit;
}

// Alleen admin/superadmin mag medewerkersfoto's uploaden (geen requireAdmin()
// hier — die redirect op falen, en dit endpoint moet altijd JSON teruggeven).
$_roleStmt = $db->prepare("SELECT role FROM users WHERE id = ?");
$_roleStmt->execute([(int)$_SESSION['user_id']]);
$_role = $_roleStmt->fetchColumn();
if (!in_array($_role, ['admin', 'superadmin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Geen rechten']);
    exit;
}

try {
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Geen bestand geüpload');
    }

    $file = $_FILES['photo'];

    // Echte MIME-detectie (niet alleen de bestandsextensie vertrouwen)
    $allowed = [
        'image/jpeg' => 'jpeg',
        'image/jpg'  => 'jpeg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mime])) {
        throw new Exception('Alleen afbeeldingen toegestaan (JPG, PNG, GIF, WEBP)');
    }

    // Max 15MB origineel bestand (voor compressie — het resultaat is veel kleiner)
    if ($file['size'] > 15 * 1024 * 1024) {
        throw new Exception('Bestand te groot (max 15MB)');
    }

    $uploadDir = __DIR__ . '/../../images/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = 'emp_' . bin2hex(random_bytes(6)) . '_' . time() . '.jpg';
    $filepath = $uploadDir . $filename;

    $compressed = false;

    if (function_exists('gd_info')) {
        $src = null;
        switch ($allowed[$mime]) {
            case 'jpeg': $src = @imagecreatefromjpeg($file['tmp_name']); break;
            case 'png':  $src = @imagecreatefrompng($file['tmp_name']); break;
            case 'gif':  $src = @imagecreatefromgif($file['tmp_name']); break;
            case 'webp': $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : null; break;
        }

        if ($src !== false && $src !== null) {
            $origWidth  = imagesx($src);
            $origHeight = imagesy($src);

            // Langste zijde cappen op 500px — ruim voldoende voor kaarten (±60px)
            // en gedrukte badges (120x120px), maar flink kleiner dan een foto
            // rechtstreeks van een telefooncamera (vaak 3000px+).
            $maxSide = 500;
            if ($origWidth > $maxSide || $origHeight > $maxSide) {
                if ($origWidth >= $origHeight) {
                    $newWidth  = $maxSide;
                    $newHeight = max(1, (int) round($origHeight * ($maxSide / $origWidth)));
                } else {
                    $newHeight = $maxSide;
                    $newWidth  = max(1, (int) round($origWidth * ($maxSide / $origHeight)));
                }
            } else {
                $newWidth  = $origWidth;
                $newHeight = $origHeight;
            }

            $dst = imagecreatetruecolor($newWidth, $newHeight);
            // Witte achtergrond onder eventuele transparantie (PNG/GIF) — foto's
            // op medewerkerskaarten hebben geen transparantie nodig.
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefill($dst, 0, 0, $white);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

            imagejpeg($dst, $filepath, 82);

            imagedestroy($src);
            imagedestroy($dst);
            $compressed = true;
        }
    }

    if (!$compressed) {
        // GD niet beschikbaar of laden mislukt — bewaar het origineel ongewijzigd
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Upload mislukt');
        }
    }

    // Absolute URL — werkt met de relatieve BASE_PHOTO_URL-logica in app.js
    // (die alleen http(s)-URL's ongemoeid laat) én met pagina's die foto_url
    // rechtstreeks als <img src> gebruiken, ongeacht hun eigen locatie.
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? '';
    $url    = $scheme . '://' . $host . '/images/' . $filename;

    echo json_encode([
        'success'    => true,
        'url'        => $url,
        'filename'   => $filename,
        'compressed' => $compressed,
        'size'       => filesize($filepath),
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
}
