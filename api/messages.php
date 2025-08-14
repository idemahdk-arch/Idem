<?php
/**
 * API pour la gestion des messages IDEM
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
    error_log("Erreur API messages: " . $e->getMessage());
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
            getMessagesList();
            break;
        case 'search':
            searchMessages();
            break;
        default:
            throw new Exception('Action non reconnue');
    }
}

function getMessagesList() {
    global $db, $userId;
    
    $conversationId = intval($_GET['conversation_id'] ?? 0);
    $page = intval($_GET['page'] ?? 1);
    $limit = 50;
    $offset = ($page - 1) * $limit;
    
    if (!$conversationId) {
        throw new Exception('ID de conversation requis');
    }
    
    // Vérifier que l'utilisateur fait partie de la conversation
    $participant = $db->fetchOne(
        "SELECT id FROM conversation_participants 
         WHERE conversation_id = :conv_id AND user_id = :user_id AND is_deleted = false",
        ['conv_id' => $conversationId, 'user_id' => $userId]
    );
    
    if (!participant) {
        throw new Exception('Accès refusé à cette conversation');
    }
    
    $sql = "
        SELECT 
            m.id,
            m.content,
            m.message_type,
            m.file_url,
            m.original_filename,
            m.created_at,
            m.updated_at,
            m.sender_id,
            u.username as sender_username,
            CONCAT(u.first_name, ' ', u.last_name) as sender_name,
            u.avatar as sender_avatar,
            EXISTS(
                SELECT 1 FROM message_reads mr 
                WHERE mr.message_id = m.id AND mr.user_id != m.sender_id
            ) as is_read,
            mr.read_at,
            CASE 
                WHEN m.created_at != m.updated_at THEN true
                ELSE false
            END as is_edited
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        LEFT JOIN message_reads mr ON m.id = mr.message_id AND mr.user_id != m.sender_id
        WHERE m.conversation_id = :conv_id 
        AND m.is_deleted = false
        ORDER BY m.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $messages = $db->fetchAll($sql, [
        'conv_id' => $conversationId,
        'limit' => $limit,
        'offset' => $offset
    ]);
    
    // Inverser l'ordre pour avoir les plus anciens en premier
    $messages = array_reverse($messages);
    
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'page' => $page,
        'has_more' => count($messages) === $limit
    ]);
}

function searchMessages() {
    global $db, $userId;
    
    $query = trim($_GET['query'] ?? '');
    $conversationId = intval($_GET['conversation_id'] ?? 0);
    
    if (strlen($query) < 2) {
        throw new Exception('Requête trop courte (minimum 2 caractères)');
    }
    
    $sql = "
        SELECT 
            m.id,
            m.content,
            m.message_type,
            m.created_at,
            m.sender_id,
            u.username as sender_username,
            CONCAT(u.first_name, ' ', u.last_name) as sender_name,
            c.id as conversation_id
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        JOIN conversations c ON m.conversation_id = c.id
        JOIN conversation_participants cp ON c.id = cp.conversation_id AND cp.user_id = :user_id
        WHERE m.is_deleted = false 
        AND cp.is_deleted = false
        AND (m.content ILIKE :query OR u.first_name ILIKE :query OR u.last_name ILIKE :query)
    ";
    
    $params = [
        'user_id' => $userId,
        'query' => '%' . $query . '%'
    ];
    
    if ($conversationId) {
        $sql .= " AND m.conversation_id = :conv_id";
        $params['conv_id'] = $conversationId;
    }
    
    $sql .= " ORDER BY m.created_at DESC LIMIT 50";
    
    $results = $db->fetchAll($sql, $params);
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'query' => $query
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
        case 'send':
            sendMessage($input);
            break;
        case 'react':
            addReaction($input);
            break;
        default:
            throw new Exception('Action non reconnue');
    }
}

function sendMessage($input) {
    global $db, $userId;
    
    $conversationId = intval($input['conversation_id'] ?? 0);
    $content = trim($input['content'] ?? '');
    $messageType = $input['type'] ?? 'text';
    $fileUrl = $input['file_url'] ?? null;
    $originalFilename = $input['original_filename'] ?? null;
    $replyToId = intval($input['reply_to_id'] ?? 0) ?: null;
    
    if (!$conversationId) {
        throw new Exception('ID de conversation requis');
    }
    
    // Vérifier que l'utilisateur fait partie de la conversation
    $participant = $db->fetchOne(
        "SELECT id FROM conversation_participants 
         WHERE conversation_id = :conv_id AND user_id = :user_id AND is_deleted = false",
        ['conv_id' => $conversationId, 'user_id' => $userId]
    );
    
    if (!$participant) {
        throw new Exception('Accès refusé à cette conversation');
    }
    
    // Validation du contenu
    if ($messageType === 'text' && empty($content)) {
        throw new Exception('Le message ne peut pas être vide');
    }
    
    if ($messageType !== 'text' && empty($fileUrl)) {
        throw new Exception('URL du fichier requise pour ce type de message');
    }
    
    if (strlen($content) > 2000) {
        throw new Exception('Message trop long (max 2000 caractères)');
    }
    
    $db->beginTransaction();
    
    try {
        // Créer le message
        $messageId = $db->insert('messages', [
            'conversation_id' => $conversationId,
            'sender_id' => $userId,
            'content' => $content,
            'message_type' => $messageType,
            'file_url' => $fileUrl,
            'original_filename' => $originalFilename,
            'reply_to_id' => $replyToId
        ]);
        
        // Mettre à jour la conversation (last activity)
        $db->update('conversations', [
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $conversationId]);
        
        // Récupérer le message créé avec les infos utilisateur
        $newMessage = $db->fetchOne("
            SELECT 
                m.id,
                m.content,
                m.message_type,
                m.file_url,
                m.original_filename,
                m.created_at,
                m.sender_id,
                u.username as sender_username,
                CONCAT(u.first_name, ' ', u.last_name) as sender_name,
                u.avatar as sender_avatar
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.id = :id
        ", ['id' => $messageId]);
        
        // Récupérer les autres participants pour les notifications
        $otherParticipants = $db->fetchAll(
            "SELECT user_id FROM conversation_participants 
             WHERE conversation_id = :conv_id AND user_id != :user_id AND is_deleted = false",
            ['conv_id' => $conversationId, 'user_id' => $userId]
        );
        
        // Créer les notifications
        foreach ($otherParticipants as $participant) {
            createNotification(
                $participant['user_id'],
                'message',
                'vous a envoyé un message',
                $conversationId,
                $userId
            );
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => $newMessage,
            'message_id' => $messageId
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function addReaction($input) {
    global $db, $userId;
    
    $messageId = intval($input['message_id'] ?? 0);
    $reactionType = $input['reaction_type'] ?? 'like';
    
    if (!$messageId) {
        throw new Exception('ID du message requis');
    }
    
    // Vérifier que le message existe et est accessible
    $message = $db->fetchOne(
        "SELECT m.id, m.conversation_id 
         FROM messages m
         JOIN conversation_participants cp ON m.conversation_id = cp.conversation_id 
         WHERE m.id = :msg_id AND cp.user_id = :user_id AND m.is_deleted = false AND cp.is_deleted = false",
        ['msg_id' => $messageId, 'user_id' => $userId]
    );
    
    if (!$message) {
        throw new Exception('Message non trouvé');
    }
    
    // Vérifier si l'utilisateur a déjà réagi
    $existingReaction = $db->fetchOne(
        "SELECT id, reaction_type FROM message_reactions 
         WHERE message_id = :msg_id AND user_id = :user_id",
        ['msg_id' => $messageId, 'user_id' => $userId]
    );
    
    if ($existingReaction) {
        if ($existingReaction['reaction_type'] === $reactionType) {
            // Supprimer la réaction
            $db->delete('message_reactions', 'id = :id', ['id' => $existingReaction['id']]);
            $action = 'removed';
        } else {
            // Modifier la réaction
            $db->update('message_reactions', [
                'reaction_type' => $reactionType,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $existingReaction['id']]);
            $action = 'updated';
        }
    } else {
        // Ajouter une nouvelle réaction
        $db->insert('message_reactions', [
            'message_id' => $messageId,
            'user_id' => $userId,
            'reaction_type' => $reactionType
        ]);
        $action = 'added';
    }
    
    // Compter les réactions
    $reactions = $db->fetchAll(
        "SELECT reaction_type, COUNT(*) as count 
         FROM message_reactions 
         WHERE message_id = :msg_id 
         GROUP BY reaction_type",
        ['msg_id' => $messageId]
    );
    
    echo json_encode([
        'success' => true,
        'action' => $action,
        'reactions' => $reactions
    ]);
}

function handlePutRequest() {
    global $db, $userId;
    
    $headers = getallheaders();
    $csrfToken = $headers['X-CSRF-Token'] ?? '';
    
    if (!SessionManager::validateCsrfToken($csrfToken)) {
        throw new Exception('Token CSRF invalide');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'edit':
            editMessage($input);
            break;
        case 'mark_read':
            markMessageAsRead($input);
            break;
        default:
            throw new Exception('Action non reconnue');
    }
}

function editMessage($input) {
    global $db, $userId;
    
    $messageId = intval($input['message_id'] ?? 0);
    $newContent = trim($input['content'] ?? '');
    
    if (!$messageId) {
        throw new Exception('ID du message requis');
    }
    
    if (empty($newContent)) {
        throw new Exception('Le contenu ne peut pas être vide');
    }
    
    if (strlen($newContent) > 2000) {
        throw new Exception('Message trop long (max 2000 caractères)');
    }
    
    // Vérifier que l'utilisateur est propriétaire du message
    $message = $db->fetchOne(
        "SELECT sender_id, created_at FROM messages 
         WHERE id = :id AND is_deleted = false",
        ['id' => $messageId]
    );
    
    if (!$message || $message['sender_id'] != $userId) {
        throw new Exception('Message non trouvé ou accès refusé');
    }
    
    // Vérifier le délai de modification (15 minutes)
    $createdAt = new DateTime($message['created_at']);
    $now = new DateTime();
    $interval = $now->diff($createdAt);
    
    if ($interval->i > 15 || $interval->h > 0 || $interval->d > 0) {
        throw new Exception('Délai de modification dépassé (15 minutes)');
    }
    
    // Modifier le message
    $db->update('messages', [
        'content' => $newContent,
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = :id', ['id' => $messageId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Message modifié avec succès'
    ]);
}

function markMessageAsRead($input) {
    global $db, $userId;
    
    $messageId = intval($input['message_id'] ?? 0);
    
    if (!$messageId) {
        throw new Exception('ID du message requis');
    }
    
    // Vérifier que le message existe et n'est pas envoyé par l'utilisateur
    $message = $db->fetchOne(
        "SELECT m.id 
         FROM messages m
         JOIN conversation_participants cp ON m.conversation_id = cp.conversation_id
         WHERE m.id = :msg_id AND cp.user_id = :user_id AND m.sender_id != :user_id 
         AND m.is_deleted = false AND cp.is_deleted = false",
        ['msg_id' => $messageId, 'user_id' => $userId]
    );
    
    if (!$message) {
        throw new Exception('Message non trouvé');
    }
    
    // Vérifier s'il n'est pas déjà marqué comme lu
    $existingRead = $db->fetchOne(
        "SELECT id FROM message_reads 
         WHERE message_id = :msg_id AND user_id = :user_id",
        ['msg_id' => $messageId, 'user_id' => $userId]
    );
    
    if (!$existingRead) {
        $db->insert('message_reads', [
            'message_id' => $messageId,
            'user_id' => $userId,
            'read_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Message marqué comme lu'
    ]);
}

function handleDeleteRequest() {
    global $db, $userId;
    
    $messageId = intval($_GET['id'] ?? 0);
    $deleteFor = $_GET['delete_for'] ?? 'me'; // 'me' ou 'everyone'
    
    if (!$messageId) {
        throw new Exception('ID du message requis');
    }
    
    // Vérifier que l'utilisateur est propriétaire du message
    $message = $db->fetchOne(
        "SELECT sender_id, created_at, conversation_id FROM messages 
         WHERE id = :id AND is_deleted = false",
        ['id' => $messageId]
    );
    
    if (!$message || $message['sender_id'] != $userId) {
        throw new Exception('Message non trouvé ou accès refusé');
    }
    
    if ($deleteFor === 'everyone') {
        // Vérifier le délai de suppression pour tous (5 minutes)
        $createdAt = new DateTime($message['created_at']);
        $now = new DateTime();
        $interval = $now->diff($createdAt);
        
        if ($interval->i > 5 || $interval->h > 0 || $interval->d > 0) {
            throw new Exception('Délai de suppression pour tous dépassé (5 minutes)');
        }
        
        // Suppression logique pour tous
        $db->update('messages', [
            'is_deleted' => true,
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $userId,
            'delete_type' => 'everyone'
        ], 'id = :id', ['id' => $messageId]);
        
        $message = 'Message supprimé pour tous';
    } else {
        // Suppression seulement pour l'utilisateur
        // Créer un enregistrement de suppression personnelle
        $existing = $db->fetchOne(
            "SELECT id FROM message_deletions 
             WHERE message_id = :msg_id AND user_id = :user_id",
            ['msg_id' => $messageId, 'user_id' => $userId]
        );
        
        if (!$existing) {
            $db->insert('message_deletions', [
                'message_id' => $messageId,
                'user_id' => $userId,
                'deleted_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        $message = 'Message supprimé pour vous';
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
}
?>