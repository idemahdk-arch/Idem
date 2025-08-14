<?php
/**
 * API pour l'upload de fichiers IDEM
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Connexion requise']);
    exit;
}

$db = initDatabase();
$userId = SessionManager::getUserId();

// Vérifier le token CSRF
$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? '';

if (!SessionManager::validateCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalide']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }

    if (!isset($_FILES['file'])) {
        throw new Exception('Aucun fichier fourni');
    }

    $file = $_FILES['file'];
    $fileType = $_POST['type'] ?? 'image';

    // Validation du fichier
    validateFile($file);

    // Créer les dossiers si nécessaires
    $uploadDir = getUploadDirectory($fileType);
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Générer un nom unique
    $fileName = generateUniqueFileName($file['name']);
    $filePath = $uploadDir . '/' . $fileName;

    // Déplacer le fichier
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Erreur lors du téléchargement');
    }

    // Optimiser l'image si nécessaire
    if ($fileType === 'image') {
        optimizeImage($filePath);
    }

    // Enregistrer en base de données
    $fileId = $db->insert('uploads', [
        'user_id' => $userId,
        'filename' => $fileName,
        'original_name' => $file['name'],
        'file_type' => $fileType,
        'file_size' => $file['size'],
        'mime_type' => $file['type'],
        'file_path' => $filePath
    ]);

    // Générer l'URL d'accès
    $fileUrl = '/uploads/' . ($fileType === 'image' ? 'images' : 'videos') . '/' . $fileName;

    echo json_encode([
        'success' => true,
        'file_id' => $fileId,
        'filename' => $fileName,
        'url' => $fileUrl,
        'type' => $fileType,
        'size' => $file['size']
    ]);

} catch (Exception $e) {
    error_log("Erreur upload: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function validateFile($file) {
    // Vérifier les erreurs d'upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new Exception('Le fichier est trop volumineux');
            case UPLOAD_ERR_PARTIAL:
                throw new Exception('Le fichier a été partiellement téléchargé');
            case UPLOAD_ERR_NO_FILE:
                throw new Exception('Aucun fichier sélectionné');
            default:
                throw new Exception('Erreur lors du téléchargement');
        }
    }

    // Vérifier la taille (max 10MB)
    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        throw new Exception('Le fichier est trop volumineux (max 10MB)');
    }

    // Vérifier le type MIME
    $allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'video/mp4', 'video/webm', 'video/ogg',
        'audio/mp3', 'audio/wav', 'audio/ogg'
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Type de fichier non autorisé');
    }

    // Vérifier l'extension
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'mp4', 'webm', 'ogg', 'mp3', 'wav'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions)) {
        throw new Exception('Extension de fichier non autorisée');
    }
}

function getUploadDirectory($fileType) {
    $baseDir = '../uploads';
    
    switch ($fileType) {
        case 'image':
            return $baseDir . '/images';
        case 'video':
            return $baseDir . '/videos';
        case 'audio':
            return $baseDir . '/audio';
        default:
            return $baseDir . '/files';
    }
}

function generateUniqueFileName($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $baseName = pathinfo($originalName, PATHINFO_FILENAME);
    
    // Nettoyer le nom de base
    $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
    $baseName = substr($baseName, 0, 30); // Limiter la longueur
    
    // Générer un nom unique
    $timestamp = time();
    $random = bin2hex(random_bytes(4));
    
    return $baseName . '_' . $timestamp . '_' . $random . '.' . $extension;
}

function optimizeImage($filePath) {
    $imageInfo = getimagesize($filePath);
    if (!$imageInfo) {
        return;
    }

    $mimeType = $imageInfo['mime'];
    $maxWidth = 1200;
    $maxHeight = 1200;
    $quality = 85;

    switch ($mimeType) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($filePath);
            break;
        case 'image/png':
            $image = imagecreatefrompng($filePath);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($filePath);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($filePath);
            break;
        default:
            return; // Type non supporté pour l'optimisation
    }

    if (!$image) {
        return;
    }

    $originalWidth = imagesx($image);
    $originalHeight = imagesy($image);

    // Calculer les nouvelles dimensions
    if ($originalWidth > $maxWidth || $originalHeight > $maxHeight) {
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        $newWidth = round($originalWidth * $ratio);
        $newHeight = round($originalHeight * $ratio);

        // Redimensionner l'image
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Préserver la transparence pour PNG et GIF
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
            imagefill($resizedImage, 0, 0, $transparent);
        }

        imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
        imagedestroy($image);
        $image = $resizedImage;
    }

    // Sauvegarder l'image optimisée
    switch ($mimeType) {
        case 'image/jpeg':
            imagejpeg($image, $filePath, $quality);
            break;
        case 'image/png':
            imagepng($image, $filePath, 9);
            break;
        case 'image/gif':
            imagegif($image, $filePath);
            break;
        case 'image/webp':
            imagewebp($image, $filePath, $quality);
            break;
    }

    imagedestroy($image);
}
?>