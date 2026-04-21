<?php
/**
 * AJAX: Delete Installer Folder
 */

session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Check if installation is complete
if (empty($_SESSION['installation_complete'])) {
    echo json_encode(['success' => false, 'error' => 'Installation not complete']);
    exit;
}

$installDir = __DIR__ . '/..';
$rootDir = dirname($installDir);

/**
 * Recursively delete directory
 */
function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return true;
    }
    
    if (!is_dir($dir)) {
        return unlink($dir);
    }
    
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}

try {
    // Check if we can delete
    if (!is_writable($installDir)) {
        throw new Exception('Installer folder is not writable. Please delete it manually via FTP.');
    }
    
    // Delete the installer folder
    $deleted = deleteDirectory($installDir);
    
    if (!$deleted) {
        throw new Exception('Could not delete installer folder. Please delete it manually.');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Installer folder deleted successfully! Redirecting to login...'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
