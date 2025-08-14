<?php
/**
 * API pour la gestion des conversations IDEM
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
    error_log("Erreur API conversations: " . $e->getMessage());
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
            getConversationsList();
            break;
        case 'details':
            getConversationDetails();
            break;
        case 'archived':
            getArchivedConversations();
            break;
        default:
            throw new Exception('Action non reconnue');
    }
}

function getConversationsList() {
    global $db, $userId;
    
    $sql = "
        SELECT 
            c.id,
            c.created_at,
            c.is_archived,
            u.id as participant_id,
            u.username as participant_username,
            CONCAT(u.first_name, ' ', u.last_name) as participant_name,
            u.avatar as participant_avatar,
            u.last_seen as participant_last_seen,
            CASE 
                WHEN u.last_seen > NOW() - INTERVAL '5 minutes' THEN 'online'
                ELSE 'offline'
            END as participant_status,
            lm.content as last_message_content,
            lm.message_type as last_message_type,
            lm.created_at as last_message_time,
            lm.sender_id as last_sender_id,
            COALESCE(unread.count, 0) as unread_count
        FROM conversations c
        JOIN conversation_participants cp1 ON c.id = cp1.conversation_id AND cp1.user_id = :user_id
        JOIN conversation_participants cp2 ON c.id = cp2.conversation_id AND cp2.user_id != :user_id
        JOIN users u ON u.id = cp2.user_id
        LEFT JOIN (
            SELECT 
                conversation_id,
                content,
                message_type,
                created_at,
                sender_id,
                ROW_NUMBER() OVER (PARTITION BY conversation_id ORDER BY created_at DESC) as rn
            FROM messages
            WHERE is_deleted = false
        ) lm ON c.id = lm.conversation_id AND lm.rn = 1
        LEFT JOIN (
            SELECT 
                conversation_id,
                COUNT(*) as count
            FROM messages m
            WHERE m.sender_id != :user_id 
            AND m.is_deleted = false
            AND NOT EXISTS (
                SELECT 1 FROM message_reads mr 
                WHERE mr.message_id = m.id AND mr.user_id = :user_id
            )
            GROUP BY conversation_id
        ) unread ON c.id = unread.conversation_id
        WHERE c.is_deleted = false 
        AND cp1.is_archived = false
        ORDER BY 
            CASE WHEN lm.created_at IS NULL THEN c.created_at ELSE lm.created_at END DESC
    ";
    
    $conversations = $db->fetchAll($sql, ['user_id' => $userId]);
    
    // Formatter les derniers messages
    foreach ($conversations as &$conv) {
        if ($conv['last_message_content']) {
            $conv['last_message'] = [
                'content' => $conv['last_message_content'],
                'type' => $conv['last_message_type'],
                'sender_id' => $conv['last_sender_id']
            ];
        } else {
            $conv['last_message'] = null;
        }
        
        unset($conv['last_message_content'], $conv['last_message_type'], $conv['last_sender_id']);
    }
    
    echo json_encode([
        'success' => true,
        'conversations' => $conversations
    ]);
}

function getConversationDetails() {
    global $db, $userId;
    
    $conversationId = intval($_GET['id'] ?? 0);
    
    if (!$conversationId) {
        throw new Exception('ID de conversation requis');
    }
    
    // Vérifier que l'utilisateur fait partie de la conversation
    $participant = $db->fetchOne(
        "SELECT id FROM conversation_participants 
         WHERE conversation_id = :conv_id AND user_id = :user_id",
        ['conv_id' => $conversationId, 'user_id' => $userId]
    );
    
    if (!$participant) {
        throw new Exception('Conversation non trouvée');
    }
    
    // Récupérer les détails
    $sql = "
        SELECT 
            c.id,
            c.created_at,
            u.id as participant_id,
            u.username as participant_username,
            CONCAT(u.first_name, ' ', u.last_name) as participant_name,
            u.avatar as participant_avatar,
            u.last_seen as participant_last_seen,
            CASE 
                WHEN u.last_seen > NOW() - INTERVAL '5 minutes' THEN 'online'
                ELSE 'offline'
            END as participant_status
        FROM conversations c
        JOIN conversation_participants cp ON c.id = cp.conversation_id AND cp.user_id != :user_id
        JOIN users u ON u.id = cp.user_id
        WHERE c.id = :conv_id
    ";
    
    $conversation = $db->fetchOne($sql, [
        'user_id' => $userId,
        'conv_id' => $conversationId
    ]);
    
    if (!$conversation) {
        throw new Exception('Détails de conversation non trouvés');
    }
    
    echo json_encode([
        'success' => true,
        'conversation' => $conversation
    ]);
}

function getArchivedConversations() {
    global $db, $userId;
    
    $sql = "
        SELECT 
            c.id,
            c.created_at,
            u.id as participant_id,
            CONCAT(u.first_name, ' ', u.last_name) as participant_name,
            u.avatar as participant_avatar,
            cp.archived_at
        FROM conversations c
        JOIN conversation_participants cp ON c.id = cp.conversation_id AND cp.user_id = :user_id AND cp.is_archived = true
        JOIN conversation_participants cp2 ON c.id = cp2.conversation_id AND cp2.user_id != :user_id
        JOIN users u ON u.id = cp2.user_id
        WHERE c.is_deleted = false
        ORDER BY cp.archived_at DESC
    ";
    
    $archived = $db->fetchAll($sql, ['user_id' => $userId]);
    
    echo json_encode([
        'success' => true,
        'conversations' => $archived
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
        case 'create':
            createConversation($input);
            break;
        default:
            throw new Exception('Action non reconnue');
    }
}

function createConversation($input) {
    global $db, $userId;
    
    $participantId = intval($input['participant_id'] ?? 0);
    
    if (!$participantId || $participantId === $userId) {
        throw new Exception('ID participant invalide');
    }
    
    // Vérifier que le participant existe et n'est pas bloqué
    $participant = $db->fetchOne(
        "SELECT id FROM users 
         WHERE id = :id AND is_active = true
         AND id NOT IN (
             SELECT CASE 
                 WHEN requester_id = :user_id THEN addressee_id
                 ELSE requester_id
             END
             FROM friendships 
             WHERE (requester_id = :user_id OR addressee_id = :user_id)
             AND status = 'blocked'
         )",
        ['id' => $participantId, 'user_id' => $userId]
    );
    
    if (!$participant) {
        throw new Exception('Impossible de créer une conversation avec cet utilisateur');
    }
    
    // Vérifier s'il existe déjà une conversation
    $existingConv = $db->fetchOne(
        "SELECT c.id 
         FROM conversations c
         JOIN conversation_participants cp1 ON c.id = cp1.conversation_id AND cp1.user_id = :user_id
         JOIN conversation_participants cp2 ON c.id = cp2.conversation_id AND cp2.user_id = :participant_id
         WHERE c.is_deleted = false
         AND (SELECT COUNT(*) FROM conversation_participants WHERE conversation_id = c.id) = 2",
        ['user_id' => $userId, 'participant_id' => $participantId]
    );
    
    if ($existingConv) {
        echo json_encode([
            'success' => true,
            'conversation_id' => $existingConv['id'],
            'message' => 'Conversation existante récupérée'
        ]);
        return;
    }
    
    $db->beginTransaction();
    
    try {
        // Créer la conversation
        $conversationId = $db->insert('conversations', [
            'created_by' => $userId
        ]);
        
        // Ajouter les participants
        $db->insert('conversation_participants', [
            'conversation_id' => $conversationId,
            'user_id' => $userId
        ]);
        
        $db->insert('conversation_participants', [
            'conversation_id' => $conversationId,
            'user_id' => $participantId
        ]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'conversation_id' => $conversationId,
            'message' => 'Conversation créée avec succès'
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
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'archive':
            archiveConversation($input);
            break;
        case 'unarchive':
            unarchiveConversation($input);
            break;
        case 'mark_read':
            markConversationAsRead($input);
            break;
        case 'mute':
            muteConversation($input);
            break;
        case 'unmute':
            unmuteConversation($input);
            break;
        default:
            throw new Exception('Action non reconnue');
    }
}

function archiveConversation($input) {
    global $db, $userId;
    
    $conversationId = intval($input['conversation_id'] ?? 0);
    
    if (!$conversationId) {
        throw new Exception('ID de conversation requis');
    }
    
    $updated = $db->update('conversation_participants', [
        'is_archived' => true,
        'archived_at' => date('Y-m-d H:i:s')
    ], 'conversation_id = :conv_id AND user_id = :user_id', [
        'conv_id' => $conversationId,
        'user_id' => $userId
    ]);
    
    if ($updated->rowCount() === 0) {
        throw new Exception('Conversation non trouvée');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Conversation archivée'
    ]);
}

function unarchiveConversation($input) {
    global $db, $userId;
    
    $conversationId = intval($input['conversation_id'] ?? 0);
    
    if (!$conversationId) {
        throw new Exception('ID de conversation requis');
    }
    
    $updated = $db->update('conversation_participants', [
        'is_archived' => false,
        'archived_at' => null
    ], 'conversation_id = :conv_id AND user_id = :user_id', [
        'conv_id' => $conversationId,
        'user_id' => $userId
    ]);
    
    if ($updated->rowCount() === 0) {
        throw new Exception('Conversation non trouvée');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Conversation restaurée'
    ]);
}

function markConversationAsRead($input) {
    global $db, $userId;
    
    $conversationId = intval($input['conversation_id'] ?? 0);
    
    if (!$conversationId) {
        throw new Exception('ID de conversation requis');
    }
    
    $db->beginTransaction();
    
    try {
        // Récupérer tous les messages non lus de cette conversation
        $unreadMessages = $db->fetchAll(
            "SELECT id FROM messages 
             WHERE conversation_id = :conv_id 
             AND sender_id != :user_id 
             AND is_deleted = false
             AND id NOT IN (
                 SELECT message_id FROM message_reads WHERE user_id = :user_id
             )",
            ['conv_id' => $conversationId, 'user_id' => $userId]
        );
        
        // Marquer comme lus
        foreach ($unreadMessages as $message) {
            $db->insert('message_reads', [
                'message_id' => $message['id'],
                'user_id' => $userId,
                'read_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'messages_read' => count($unreadMessages)
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function muteConversation($input) {
    global $db, $userId;
    
    $conversationId = intval($input['conversation_id'] ?? 0);
    
    if (!$conversationId) {
        throw new Exception('ID de conversation requis');
    }
    
    $updated = $db->update('conversation_participants', [
        'is_muted' => true,
        'muted_at' => date('Y-m-d H:i:s')
    ], 'conversation_id = :conv_id AND user_id = :user_id', [
        'conv_id' => $conversationId,
        'user_id' => $userId
    ]);
    
    if ($updated->rowCount() === 0) {
        throw new Exception('Conversation non trouvée');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Notifications coupées'
    ]);
}

function unmuteConversation($input) {
    global $db, $userId;
    
    $conversationId = intval($input['conversation_id'] ?? 0);
    
    if (!$conversationId) {
        throw new Exception('ID de conversation requis');
    }
    
    $updated = $db->update('conversation_participants', [
        'is_muted' => false,
        'muted_at' => null
    ], 'conversation_id = :conv_id AND user_id = :user_id', [
        'conv_id' => $conversationId,
        'user_id' => $userId
    ]);
    
    if ($updated->rowCount() === 0) {
        throw new Exception('Conversation non trouvée');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Notifications rétablies'
    ]);
}

function handleDeleteRequest() {
    global $db, $userId;
    
    $conversationId = intval($_GET['id'] ?? 0);
    
    if (!$conversationId) {
        throw new Exception('ID de conversation requis');
    }
    
    // Vérifier que l'utilisateur fait partie de la conversation
    $participant = $db->fetchOne(
        "SELECT id FROM conversation_participants 
         WHERE conversation_id = :conv_id AND user_id = :user_id",
        ['conv_id' => $conversationId, 'user_id' => $userId]
    );
    
    if (!$participant) {
        throw new Exception('Conversation non trouvée');
    }
    
    $db->beginTransaction();
    
    try {
        // Supprimer la participation (soft delete)
        $db->update('conversation_participants', [
            'is_deleted' => true,
            'deleted_at' => date('Y-m-d H:i:s')
        ], 'conversation_id = :conv_id AND user_id = :user_id', [
            'conv_id' => $conversationId,
            'user_id' => $userId
        ]);
        
        // Vérifier si tous les participants ont supprimé la conversation
        $remainingParticipants = $db->fetchOne(
            "SELECT COUNT(*) as count FROM conversation_participants 
             WHERE conversation_id = :conv_id AND is_deleted = false",
            ['conv_id' => $conversationId]
        );
        
        // Si plus personne, supprimer la conversation complètement
        if ($remainingParticipants['count'] == 0) {
            $db->update('conversations', [
                'is_deleted' => true,
                'deleted_at' => date('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $conversationId]);
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Conversation supprimée'
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}
?>