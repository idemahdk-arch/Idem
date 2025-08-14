<?php
/**
 * API pour la gestion des amis IDEM
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
    error_log("Erreur API friends: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function handleGetRequest() {
    global $db, $userId;
    
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            getFriendsList();
            break;
        case 'requests':
            getFriendRequests();
            break;
        case 'suggestions':
            getFriendSuggestions();
            break;
        case 'status':
            getFriendshipStatus();
            break;
        default:
            throw new Exception('Action non reconnue');
    }
}

function getFriendsList() {
    global $db, $userId;
    
    $sql = "
        SELECT 
            u.id, u.username, u.first_name, u.last_name, u.avatar, u.bio,
            u.last_seen, f.created_at as friends_since
        FROM friendships f
        JOIN users u ON (
            CASE 
                WHEN f.requester_id = :user_id THEN u.id = f.addressee_id
                ELSE u.id = f.requester_id
            END
        )
        WHERE (f.requester_id = :user_id OR f.addressee_id = :user_id)
        AND f.status = 'accepted'
        AND u.is_active = true
        ORDER BY u.first_name, u.last_name
    ";
    
    $friends = $db->fetchAll($sql, ['user_id' => $userId]);
    
    echo json_encode([
        'success' => true,
        'friends' => $friends,
        'count' => count($friends)
    ]);
}

function getFriendRequests() {
    global $db, $userId;
    
    $type = $_GET['type'] ?? 'received'; // received, sent
    
    if ($type === 'received') {
        $sql = "
            SELECT 
                u.id, u.username, u.first_name, u.last_name, u.avatar, u.bio,
                f.id as request_id, f.created_at as request_date
            FROM friendships f
            JOIN users u ON u.id = f.requester_id
            WHERE f.addressee_id = :user_id
            AND f.status = 'pending'
            AND u.is_active = true
            ORDER BY f.created_at DESC
        ";
    } else {
        $sql = "
            SELECT 
                u.id, u.username, u.first_name, u.last_name, u.avatar, u.bio,
                f.id as request_id, f.created_at as request_date
            FROM friendships f
            JOIN users u ON u.id = f.addressee_id
            WHERE f.requester_id = :user_id
            AND f.status = 'pending'
            AND u.is_active = true
            ORDER BY f.created_at DESC
        ";
    }
    
    $requests = $db->fetchAll($sql, ['user_id' => $userId]);
    
    echo json_encode([
        'success' => true,
        'requests' => $requests,
        'type' => $type,
        'count' => count($requests)
    ]);
}

function getFriendSuggestions() {
    global $db, $userId;
    
    $limit = intval($_GET['limit'] ?? 5);
    
    // Suggestions basées sur les amis d'amis et utilisateurs populaires
    $sql = "
        SELECT DISTINCT
            u.id, u.username, u.first_name, u.last_name, u.avatar, u.bio,
            COUNT(mutual_f.id) as mutual_friends
        FROM users u
        LEFT JOIN friendships mutual_f ON (
            (mutual_f.requester_id = u.id OR mutual_f.addressee_id = u.id)
            AND mutual_f.status = 'accepted'
            AND (
                mutual_f.requester_id IN (
                    SELECT CASE 
                        WHEN f.requester_id = :user_id THEN f.addressee_id
                        ELSE f.requester_id
                    END
                    FROM friendships f
                    WHERE (f.requester_id = :user_id OR f.addressee_id = :user_id)
                    AND f.status = 'accepted'
                )
                OR mutual_f.addressee_id IN (
                    SELECT CASE 
                        WHEN f.requester_id = :user_id THEN f.addressee_id
                        ELSE f.requester_id
                    END
                    FROM friendships f
                    WHERE (f.requester_id = :user_id OR f.addressee_id = :user_id)
                    AND f.status = 'accepted'
                )
            )
        )
        WHERE u.id != :user_id
        AND u.is_active = true
        AND u.id NOT IN (
            SELECT CASE 
                WHEN f.requester_id = :user_id THEN f.addressee_id
                ELSE f.requester_id
            END
            FROM friendships f
            WHERE (f.requester_id = :user_id OR f.addressee_id = :user_id)
        )
        GROUP BY u.id, u.username, u.first_name, u.last_name, u.avatar, u.bio
        ORDER BY mutual_friends DESC, RANDOM()
        LIMIT :limit
    ";
    
    $suggestions = $db->fetchAll($sql, [
        'user_id' => $userId,
        'limit' => $limit
    ]);
    
    echo json_encode([
        'success' => true,
        'suggestions' => $suggestions
    ]);
}

function getFriendshipStatus() {
    global $db, $userId;
    
    $targetUserId = intval($_GET['user_id'] ?? 0);
    
    if (!$targetUserId) {
        throw new Exception('ID utilisateur requis');
    }
    
    if ($targetUserId === $userId) {
        echo json_encode([
            'success' => true,
            'status' => 'self'
        ]);
        return;
    }
    
    $friendship = $db->fetchOne(
        "SELECT status, requester_id, addressee_id FROM friendships 
         WHERE (requester_id = :user_id AND addressee_id = :target_id)
         OR (requester_id = :target_id AND addressee_id = :user_id)",
        ['user_id' => $userId, 'target_id' => $targetUserId]
    );
    
    if (!$friendship) {
        $status = 'none';
    } else {
        if ($friendship['status'] === 'accepted') {
            $status = 'friends';
        } elseif ($friendship['status'] === 'blocked') {
            $status = 'blocked';
        } elseif ($friendship['requester_id'] === $userId) {
            $status = 'sent';
        } else {
            $status = 'received';
        }
    }
    
    echo json_encode([
        'success' => true,
        'status' => $status
    ]);
}

function handlePostRequest() {
    global $db, $userId;
    
    $headers = getallheaders();
    $csrfToken = $headers['X-CSRF-Token'] ?? '';
    
    if (!SessionManager::validateCsrfToken($csrfToken)) {
        throw new Exception('Token CSRF invalide');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'send_request':
            sendFriendRequest($input);
            break;
        case 'accept_request':
            acceptFriendRequest($input);
            break;
        case 'decline_request':
            declineFriendRequest($input);
            break;
        case 'block_user':
            blockUser($input);
            break;
        default:
            throw new Exception('Action non reconnue');
    }
}

function sendFriendRequest($input) {
    global $db, $userId;
    
    $targetUserId = intval($input['user_id'] ?? 0);
    
    if (!$targetUserId || $targetUserId === $userId) {
        throw new Exception('ID utilisateur invalide');
    }
    
    // Vérifier que l'utilisateur cible existe
    $targetUser = $db->fetchOne(
        "SELECT id, first_name, allow_messages FROM users WHERE id = :id AND is_active = true",
        ['id' => $targetUserId]
    );
    
    if (!$targetUser) {
        throw new Exception('Utilisateur non trouvé');
    }
    
    // Vérifier s'il y a déjà une relation
    $existingFriendship = $db->fetchOne(
        "SELECT status FROM friendships 
         WHERE (requester_id = :user_id AND addressee_id = :target_id)
         OR (requester_id = :target_id AND addressee_id = :user_id)",
        ['user_id' => $userId, 'target_id' => $targetUserId]
    );
    
    if ($existingFriendship) {
        switch ($existingFriendship['status']) {
            case 'accepted':
                throw new Exception('Vous êtes déjà amis');
            case 'pending':
                throw new Exception('Demande déjà envoyée');
            case 'blocked':
                throw new Exception('Impossible d\'envoyer une demande');
        }
    }
    
    $db->beginTransaction();
    
    try {
        // Créer la demande d'ami
        $db->insert('friendships', [
            'requester_id' => $userId,
            'addressee_id' => $targetUserId,
            'status' => 'pending'
        ]);
        
        // Créer une notification
        createNotification(
            $targetUserId,
            'friend_request',
            'vous a envoyé une demande d\'ami',
            null,
            $userId
        );
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Demande d\'ami envoyée à ' . $targetUser['first_name']
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function acceptFriendRequest($input) {
    global $db, $userId;
    
    $requestId = intval($input['request_id'] ?? 0);
    
    if (!$requestId) {
        throw new Exception('ID de demande requis');
    }
    
    // Vérifier que la demande existe et nous concerne
    $request = $db->fetchOne(
        "SELECT id, requester_id FROM friendships 
         WHERE id = :id AND addressee_id = :user_id AND status = 'pending'",
        ['id' => $requestId, 'user_id' => $userId]
    );
    
    if (!$request) {
        throw new Exception('Demande non trouvée');
    }
    
    $db->beginTransaction();
    
    try {
        // Accepter la demande
        $db->update('friendships', [
            'status' => 'accepted',
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $requestId]);
        
        // Créer une notification pour celui qui a envoyé la demande
        createNotification(
            $request['requester_id'],
            'friend_request',
            'a accepté votre demande d\'ami',
            null,
            $userId
        );
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Demande d\'ami acceptée'
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function declineFriendRequest($input) {
    global $db, $userId;
    
    $requestId = intval($input['request_id'] ?? 0);
    
    if (!$requestId) {
        throw new Exception('ID de demande requis');
    }
    
    // Vérifier que la demande existe et nous concerne
    $request = $db->fetchOne(
        "SELECT id FROM friendships 
         WHERE id = :id AND addressee_id = :user_id AND status = 'pending'",
        ['id' => $requestId, 'user_id' => $userId]
    );
    
    if (!$request) {
        throw new Exception('Demande non trouvée');
    }
    
    // Supprimer la demande
    $db->delete('friendships', 'id = :id', ['id' => $requestId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Demande d\'ami refusée'
    ]);
}

function blockUser($input) {
    global $db, $userId;
    
    $targetUserId = intval($input['user_id'] ?? 0);
    
    if (!$targetUserId || $targetUserId === $userId) {
        throw new Exception('ID utilisateur invalide');
    }
    
    $db->beginTransaction();
    
    try {
        // Supprimer toute relation existante
        $db->delete('friendships', 
            '(requester_id = :user_id AND addressee_id = :target_id) 
             OR (requester_id = :target_id AND addressee_id = :user_id)',
            ['user_id' => $userId, 'target_id' => $targetUserId]
        );
        
        // Créer un blocage
        $db->insert('friendships', [
            'requester_id' => $userId,
            'addressee_id' => $targetUserId,
            'status' => 'blocked'
        ]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Utilisateur bloqué'
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function handlePutRequest() {
    // Actuellement pas d'actions PUT pour les amis
    throw new Exception('Action non disponible');
}

function handleDeleteRequest() {
    global $db, $userId;
    
    $targetUserId = intval($_GET['user_id'] ?? 0);
    
    if (!$targetUserId) {
        throw new Exception('ID utilisateur requis');
    }
    
    // Supprimer l'amitié
    $deleted = $db->delete('friendships',
        '(requester_id = :user_id AND addressee_id = :target_id) 
         OR (requester_id = :target_id AND addressee_id = :user_id)
         AND status = \'accepted\'',
        ['user_id' => $userId, 'target_id' => $targetUserId]
    );
    
    if ($deleted->rowCount() === 0) {
        throw new Exception('Amitié non trouvée');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Ami supprimé'
    ]);
}
?>