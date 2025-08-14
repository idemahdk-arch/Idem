<?php
/**
 * API pour la gestion des posts IDEM
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::start();

// Vérifier que l'utilisateur est connecté
if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Connexion requise']);
    exit;
}

$db = initDatabase();
$userId = SessionManager::getUserId();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetRequest();
            break;
        case 'POST':
            handlePostRequest();
            break;
        case 'PUT':
            handlePutRequest();
            break;
        case 'DELETE':
            handleDeleteRequest();
            break;
        default:
            throw new Exception('Méthode non autorisée');
    }
} catch (Exception $e) {
    error_log("Erreur API posts: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function handleGetRequest() {
    global $db, $userId;
    
    $action = $_GET['action'] ?? 'feed';
    
    switch ($action) {
        case 'feed':
            getFeed();
            break;
        case 'user':
            getUserPosts();
            break;
        case 'post':
            getPost();
            break;
        default:
            throw new Exception('Action non reconnue');
    }
}

function getFeed() {
    global $db, $userId;
    
    $page = intval($_GET['page'] ?? 1);
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    // Récupérer les posts du feed (posts de l'utilisateur + amis)
    $sql = "
        SELECT 
            p.id, p.content, p.image, p.video, p.privacy, 
            p.likes_count, p.comments_count, p.shares_count, p.created_at,
            u.id as user_id, u.username, u.first_name, u.last_name, u.avatar,
            EXISTS(
                SELECT 1 FROM post_likes pl 
                WHERE pl.post_id = p.id AND pl.user_id = :current_user_id
            ) as user_liked
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.is_deleted = false 
        AND (
            p.user_id = :current_user_id 
            OR p.user_id IN (
                SELECT CASE 
                    WHEN f.requester_id = :current_user_id THEN f.addressee_id
                    ELSE f.requester_id
                END
                FROM friendships f 
                WHERE (f.requester_id = :current_user_id OR f.addressee_id = :current_user_id)
                AND f.status = 'accepted'
            )
            OR (p.privacy = 'public')
        )
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $posts = $db->fetchAll($sql, [
        'current_user_id' => $userId,
        'limit' => $limit,
        'offset' => $offset
    ]);
    
    echo json_encode([
        'success' => true,
        'posts' => $posts,
        'page' => $page,
        'has_more' => count($posts) === $limit
    ]);
}

function getUserPosts() {
    global $db, $userId;
    
    $targetUserId = intval($_GET['user_id'] ?? $userId);
    $page = intval($_GET['page'] ?? 1);
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    // Vérifier les permissions de visualisation
    if ($targetUserId !== $userId) {
        if (!canViewProfile($userId, $targetUserId)) {
            throw new Exception('Vous n\'avez pas l\'autorisation de voir ces publications');
        }
    }
    
    $sql = "
        SELECT 
            p.id, p.content, p.image, p.video, p.privacy,
            p.likes_count, p.comments_count, p.shares_count, p.created_at,
            u.id as user_id, u.username, u.first_name, u.last_name, u.avatar,
            EXISTS(
                SELECT 1 FROM post_likes pl 
                WHERE pl.post_id = p.id AND pl.user_id = :current_user_id
            ) as user_liked
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.user_id = :target_user_id 
        AND p.is_deleted = false
        AND (
            p.user_id = :current_user_id 
            OR p.privacy = 'public'
            OR (p.privacy = 'friends' AND EXISTS(
                SELECT 1 FROM friendships f 
                WHERE (f.requester_id = :current_user_id OR f.addressee_id = :current_user_id)
                AND (f.requester_id = :target_user_id OR f.addressee_id = :target_user_id)
                AND f.status = 'accepted'
            ))
        )
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $posts = $db->fetchAll($sql, [
        'current_user_id' => $userId,
        'target_user_id' => $targetUserId,
        'limit' => $limit,
        'offset' => $offset
    ]);
    
    echo json_encode([
        'success' => true,
        'posts' => $posts,
        'page' => $page,
        'has_more' => count($posts) === $limit
    ]);
}

function getPost() {
    global $db, $userId;
    
    $postId = intval($_GET['id'] ?? 0);
    if (!$postId) {
        throw new Exception('ID du post requis');
    }
    
    $sql = "
        SELECT 
            p.id, p.content, p.image, p.video, p.privacy,
            p.likes_count, p.comments_count, p.shares_count, p.created_at,
            u.id as user_id, u.username, u.first_name, u.last_name, u.avatar,
            EXISTS(
                SELECT 1 FROM post_likes pl 
                WHERE pl.post_id = p.id AND pl.user_id = :current_user_id
            ) as user_liked
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = :post_id AND p.is_deleted = false
    ";
    
    $post = $db->fetchOne($sql, [
        'post_id' => $postId,
        'current_user_id' => $userId
    ]);
    
    if (!$post) {
        throw new Exception('Post non trouvé');
    }
    
    // Vérifier les permissions
    if ($post['user_id'] !== $userId) {
        if ($post['privacy'] === 'private') {
            throw new Exception('Post privé');
        }
        if ($post['privacy'] === 'friends' && !areFriends($userId, $post['user_id'])) {
            throw new Exception('Post réservé aux amis');
        }
    }
    
    echo json_encode([
        'success' => true,
        'post' => $post
    ]);
}

function handlePostRequest() {
    global $db, $userId;
    
    // Vérifier le token CSRF pour les requêtes POST
    $headers = getallheaders();
    $csrfToken = $headers['X-CSRF-Token'] ?? '';
    
    if (!SessionManager::validateCsrfToken($csrfToken)) {
        throw new Exception('Token CSRF invalide');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'create':
            createPost($input);
            break;
        case 'like':
            toggleLike($input);
            break;
        case 'share':
            sharePost($input);
            break;
        default:
            throw new Exception('Action non reconnue');
    }
}

function createPost($input) {
    global $db, $userId;
    
    $content = trim($input['content'] ?? '');
    $privacy = $input['privacy'] ?? 'friends';
    $image = $input['image'] ?? null;
    $video = $input['video'] ?? null;
    
    // Validation
    if (empty($content) && empty($image) && empty($video)) {
        throw new Exception('Le contenu ne peut pas être vide');
    }
    
    if (!in_array($privacy, ['public', 'friends', 'private'])) {
        throw new Exception('Paramètre de confidentialité invalide');
    }
    
    if (strlen($content) > 2000) {
        throw new Exception('Le contenu est trop long (max 2000 caractères)');
    }
    
    $db->beginTransaction();
    
    try {
        // Créer le post
        $postId = $db->insert('posts', [
            'user_id' => $userId,
            'content' => $content,
            'image' => $image,
            'video' => $video,
            'privacy' => $privacy
        ]);
        
        // Extraire et traiter les hashtags
        $hashtags = extractHashtags($content);
        foreach ($hashtags as $tag) {
            // Créer ou mettre à jour le hashtag
            $existingTag = $db->fetchOne(
                "SELECT id FROM hashtags WHERE tag = :tag",
                ['tag' => strtolower($tag)]
            );
            
            if ($existingTag) {
                $hashtagId = $existingTag['id'];
                $db->query(
                    "UPDATE hashtags SET usage_count = usage_count + 1 WHERE id = :id",
                    ['id' => $hashtagId]
                );
            } else {
                $hashtagId = $db->insert('hashtags', [
                    'tag' => strtolower($tag),
                    'usage_count' => 1
                ]);
            }
            
            // Lier le post au hashtag
            $db->insert('post_hashtags', [
                'post_id' => $postId,
                'hashtag_id' => $hashtagId
            ]);
        }
        
        // Traiter les mentions
        $mentions = extractMentions($content);
        foreach ($mentions as $username) {
            $mentionedUser = $db->fetchOne(
                "SELECT id FROM users WHERE username = :username",
                ['username' => $username]
            );
            
            if ($mentionedUser) {
                // Créer une notification
                createNotification(
                    $mentionedUser['id'],
                    'mention',
                    'Vous avez été mentionné dans une publication',
                    $postId,
                    $userId
                );
            }
        }
        
        $db->commit();
        
        // Récupérer le post créé avec toutes les informations
        $newPost = $db->fetchOne("
            SELECT 
                p.id, p.content, p.image, p.video, p.privacy,
                p.likes_count, p.comments_count, p.shares_count, p.created_at,
                u.id as user_id, u.username, u.first_name, u.last_name, u.avatar
            FROM posts p
            JOIN users u ON p.user_id = u.id
            WHERE p.id = :post_id
        ", ['post_id' => $postId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Publication créée avec succès',
            'post' => $newPost
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function toggleLike($input) {
    global $db, $userId;
    
    $postId = intval($input['post_id'] ?? 0);
    $reactionType = $input['reaction_type'] ?? 'like';
    
    if (!$postId) {
        throw new Exception('ID du post requis');
    }
    
    // Vérifier que le post existe
    $post = $db->fetchOne(
        "SELECT user_id FROM posts WHERE id = :id AND is_deleted = false",
        ['id' => $postId]
    );
    
    if (!$post) {
        throw new Exception('Post non trouvé');
    }
    
    $db->beginTransaction();
    
    try {
        // Vérifier si l'utilisateur a déjà liké
        $existingLike = $db->fetchOne(
            "SELECT id FROM post_likes WHERE post_id = :post_id AND user_id = :user_id",
            ['post_id' => $postId, 'user_id' => $userId]
        );
        
        if ($existingLike) {
            // Supprimer le like
            $db->delete('post_likes', 'id = :id', ['id' => $existingLike['id']]);
            $action = 'unliked';
        } else {
            // Ajouter le like
            $db->insert('post_likes', [
                'post_id' => $postId,
                'user_id' => $userId,
                'reaction_type' => $reactionType
            ]);
            $action = 'liked';
            
            // Créer une notification pour le propriétaire du post
            if ($post['user_id'] !== $userId) {
                createNotification(
                    $post['user_id'],
                    'like',
                    'a aimé votre publication',
                    $postId,
                    $userId
                );
            }
        }
        
        // Récupérer le nouveau nombre de likes
        $likesCount = $db->fetchOne(
            "SELECT COUNT(*) as count FROM post_likes WHERE post_id = :post_id",
            ['post_id' => $postId]
        )['count'];
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'action' => $action,
            'likes_count' => intval($likesCount)
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function sharePost($input) {
    global $db, $userId;
    
    $postId = intval($input['post_id'] ?? 0);
    $comment = trim($input['comment'] ?? '');
    
    if (!$postId) {
        throw new Exception('ID du post requis');
    }
    
    // Vérifier que le post existe et est accessible
    $originalPost = $db->fetchOne(
        "SELECT user_id, privacy FROM posts WHERE id = :id AND is_deleted = false",
        ['id' => $postId]
    );
    
    if (!$originalPost) {
        throw new Exception('Post non trouvé');
    }
    
    // Vérifier les permissions de partage
    if ($originalPost['privacy'] === 'private') {
        throw new Exception('Ce post ne peut pas être partagé');
    }
    
    $db->beginTransaction();
    
    try {
        // Créer un nouveau post de partage
        $sharePostId = $db->insert('posts', [
            'user_id' => $userId,
            'content' => $comment,
            'privacy' => 'friends' // Les partages sont toujours pour les amis
        ]);
        
        // Incrémenter le compteur de partages
        $db->query(
            "UPDATE posts SET shares_count = shares_count + 1 WHERE id = :id",
            ['id' => $postId]
        );
        
        // Créer une notification
        if ($originalPost['user_id'] !== $userId) {
            createNotification(
                $originalPost['user_id'],
                'share',
                'a partagé votre publication',
                $postId,
                $userId
            );
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Publication partagée avec succès',
            'share_post_id' => $sharePostId
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function handlePutRequest() {
    global $db, $userId;
    
    $headers = getallheaders();
    $csrfToken = $headers['X-CSRF-Token'] ?? '';
    
    if (!SessionManager::validateCsrfToken($csrfToken)) {
        throw new Exception('Token CSRF invalide');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $postId = intval($input['id'] ?? 0);
    
    if (!$postId) {
        throw new Exception('ID du post requis');
    }
    
    // Vérifier que l'utilisateur est propriétaire du post
    $post = $db->fetchOne(
        "SELECT user_id FROM posts WHERE id = :id AND is_deleted = false",
        ['id' => $postId]
    );
    
    if (!$post || $post['user_id'] !== $userId) {
        throw new Exception('Post non trouvé ou accès refusé');
    }
    
    $content = trim($input['content'] ?? '');
    $privacy = $input['privacy'] ?? 'friends';
    
    if (empty($content)) {
        throw new Exception('Le contenu ne peut pas être vide');
    }
    
    $db->update('posts', [
        'content' => $content,
        'privacy' => $privacy,
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = :id', ['id' => $postId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Publication modifiée avec succès'
    ]);
}

function handleDeleteRequest() {
    global $db, $userId;
    
    $postId = intval($_GET['id'] ?? 0);
    
    if (!$postId) {
        throw new Exception('ID du post requis');
    }
    
    // Vérifier que l'utilisateur est propriétaire du post
    $post = $db->fetchOne(
        "SELECT user_id FROM posts WHERE id = :id AND is_deleted = false",
        ['id' => $postId]
    );
    
    if (!$post || $post['user_id'] !== $userId) {
        throw new Exception('Post non trouvé ou accès refusé');
    }
    
    // Suppression logique
    $db->update('posts', [
        'is_deleted' => true,
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = :id', ['id' => $postId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Publication supprimée avec succès'
    ]);
}
?>