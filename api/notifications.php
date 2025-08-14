<?php
/**
 * API pour la gestion des notifications IDEM
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
    error_log("Erreur API notifications: " . $e->getMessage());
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
            getNotificationsList();
            break;
        case 'unread_count':
            getUnreadCount();
            break;
        case 'settings':
            getNotificationSettings();
            break;
        default:
            throw new Exception('Action non reconnue');
    }
}

function getNotificationsList() {
    global $db, $userId;
    
    $filter = $_GET['filter'] ?? 'all';
    $page = intval($_GET['page'] ?? 1);
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $whereConditions = ['n.user_id = :user_id'];
    $params = ['user_id' => $userId];
    
    // Filtres
    switch ($filter) {
        case 'unread':
            $whereConditions[] = 'n.read_at IS NULL';
            break;
        case 'mentions':
            $whereConditions[] = 'n.type = \'mention\'';
            break;
        case 'likes':
            $whereConditions[] = 'n.type = \'like\'';
            break;
        case 'friends':
            $whereConditions[] = 'n.type IN (\'friend_request\', \'friend_accepted\')';
            break;
        case 'messages':
            $whereConditions[] = 'n.type = \'message\'';
            break;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $sql = "
        SELECT 
            n.id,
            n.type,
            n.message,
            n.read_at,
            n.created_at,
            n.post_id,
            n.conversation_id,
            n.sender_id,
            sender.username as sender_username,
            CONCAT(sender.first_name, ' ', sender.last_name) as sender_name,
            sender.avatar as sender_avatar,
            CASE 
                WHEN n.type = 'friend_request' THEN 
                    NOT EXISTS(
                        SELECT 1 FROM friendships f 
                        WHERE (f.requester_id = n.sender_id AND f.addressee_id = :user_id)
                        OR (f.requester_id = :user_id AND f.addressee_id = n.sender_id)
                    )
                ELSE false
            END as friend_request_handled
        FROM notifications n
        LEFT JOIN users sender ON n.sender_id = sender.id
        WHERE {$whereClause}
        ORDER BY n.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $params['limit'] = $limit;
    $params['offset'] = $offset;
    
    $notifications = $db->fetchAll($sql, $params);
    
    // Statistiques
    $statsQuery = "
        SELECT 
            COUNT(*) as total_count,
            COUNT(CASE WHEN read_at IS NULL THEN 1 END) as unread_count
        FROM notifications 
        WHERE user_id = :user_id
    ";
    
    $stats = $db->fetchOne($statsQuery, ['user_id' => $userId]);
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'stats' => $stats,
        'page' => $page,
        'has_more' => count($notifications) === $limit
    ]);
}

function getUnreadCount() {
    global $db, $userId;
    
    $count = $db->fetchOne(
        "SELECT COUNT(*) as count FROM notifications 
         WHERE user_id = :user_id AND read_at IS NULL",
        ['user_id' => $userId]
    );
    
    echo json_encode([
        'success' => true,
        'unread_count' => intval($count['count'])
    ]);
}

function getNotificationSettings() {
    global $db, $userId;
    
    $settings = $db->fetchOne(
        "SELECT 
            push_enabled,
            notify_likes,
            notify_comments,
            notify_mentions,
            notify_friends,
            notify_messages,
            email_frequency
         FROM user_notification_settings 
         WHERE user_id = :user_id",
        ['user_id' => $userId]
    );
    
    if (!$settings) {
        // Créer les paramètres par défaut
        $db->insert('user_notification_settings', [
            'user_id' => $userId,
            'push_enabled' => true,
            'notify_likes' => true,
            'notify_comments' => true,
            'notify_mentions' => true,
            'notify_friends' => true,
            'notify_messages' => true,
            'email_frequency' => 'weekly'
        ]);
        
        $settings = [
            'push_enabled' => true,
            'notify_likes' => true,
            'notify_comments' => true,
            'notify_mentions' => true,
            'notify_friends' => true,
            'notify_messages' => true,
            'email_frequency' => 'weekly'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'settings' => $settings
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
            createNotification($input);
            break;
        default:
            throw new Exception('Action non reconnue');
    }
}

function createNotification($input) {
    global $db, $userId;
    
    $targetUserId = intval($input['user_id'] ?? 0);
    $type = $input['type'] ?? '';
    $message = trim($input['message'] ?? '');
    $postId = intval($input['post_id'] ?? 0) ?: null;
    $conversationId = intval($input['conversation_id'] ?? 0) ?: null;
    
    if (!$targetUserId || $targetUserId === $userId) {
        throw new Exception('ID utilisateur cible invalide');
    }
    
    if (empty($type)) {
        throw new Exception('Type de notification requis');
    }
    
    // Vérifier que l'utilisateur cible existe
    $targetUser = $db->fetchOne(
        "SELECT id FROM users WHERE id = :id AND is_active = true",
        ['id' => $targetUserId]
    );
    
    if (!$targetUser) {
        throw new Exception('Utilisateur cible non trouvé');
    }
    
    // Vérifier les paramètres de notification de l'utilisateur
    $settings = $db->fetchOne(
        "SELECT notify_likes, notify_comments, notify_mentions, notify_friends, notify_messages
         FROM user_notification_settings 
         WHERE user_id = :user_id",
        ['user_id' => $targetUserId]
    );
    
    if ($settings) {
        $settingKey = 'notify_' . str_replace(['_request', '_accepted'], '', $type);
        if ($type === 'friend_request' || $type === 'friend_accepted') {
            $settingKey = 'notify_friends';
        } elseif ($type === 'comment') {
            $settingKey = 'notify_comments';
        }
        
        if (isset($settings[$settingKey]) && !$settings[$settingKey]) {
            // L'utilisateur a désactivé ce type de notification
            echo json_encode([
                'success' => true,
                'message' => 'Notification ignorée (paramètres utilisateur)'
            ]);
            return;
        }
    }
    
    // Éviter les doublons récents (même type, même sender, dans les 5 dernières minutes)
    $existingRecent = $db->fetchOne(
        "SELECT id FROM notifications 
         WHERE user_id = :user_id AND sender_id = :sender_id AND type = :type
         AND created_at > NOW() - INTERVAL '5 minutes'",
        [
            'user_id' => $targetUserId,
            'sender_id' => $userId,
            'type' => $type
        ]
    );
    
    if ($existingRecent) {
        echo json_encode([
            'success' => true,
            'message' => 'Notification récente existante ignorée'
        ]);
        return;
    }
    
    // Créer la notification
    $notificationId = $db->insert('notifications', [
        'user_id' => $targetUserId,
        'sender_id' => $userId,
        'type' => $type,
        'message' => $message,
        'post_id' => $postId,
        'conversation_id' => $conversationId
    ]);
    
    echo json_encode([
        'success' => true,
        'notification_id' => $notificationId,
        'message' => 'Notification créée avec succès'
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
        case 'mark_read':
            markNotificationAsRead($input);
            break;
        case 'mark_all_read':
            markAllNotificationsAsRead();
            break;
        case 'update_settings':
            updateNotificationSettings($input);
            break;
        default:
            throw new Exception('Action non reconnue');
    }
}

function markNotificationAsRead($input) {
    global $db, $userId;
    
    $notificationId = intval($input['notification_id'] ?? 0);
    
    if (!$notificationId) {
        throw new Exception('ID de notification requis');
    }
    
    $updated = $db->update('notifications', [
        'read_at' => date('Y-m-d H:i:s')
    ], 'id = :id AND user_id = :user_id AND read_at IS NULL', [
        'id' => $notificationId,
        'user_id' => $userId
    ]);
    
    if ($updated->rowCount() === 0) {
        throw new Exception('Notification non trouvée ou déjà lue');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Notification marquée comme lue'
    ]);
}

function markAllNotificationsAsRead() {
    global $db, $userId;
    
    $updated = $db->update('notifications', [
        'read_at' => date('Y-m-d H:i:s')
    ], 'user_id = :user_id AND read_at IS NULL', [
        'user_id' => $userId
    ]);
    
    echo json_encode([
        'success' => true,
        'notifications_updated' => $updated->rowCount(),
        'message' => 'Toutes les notifications marquées comme lues'
    ]);
}

function updateNotificationSettings($input) {
    global $db, $userId;
    
    $settings = $input['settings'] ?? [];
    
    // Validation des paramètres
    $allowedSettings = [
        'push_enabled', 'notify_likes', 'notify_comments', 
        'notify_mentions', 'notify_friends', 'notify_messages', 'email_frequency'
    ];
    
    $cleanSettings = [];
    foreach ($allowedSettings as $setting) {
        if (isset($settings[$setting])) {
            if ($setting === 'email_frequency') {
                $allowedFrequencies = ['never', 'daily', 'weekly', 'monthly'];
                if (in_array($settings[$setting], $allowedFrequencies)) {
                    $cleanSettings[$setting] = $settings[$setting];
                }
            } else {
                $cleanSettings[$setting] = (bool)$settings[$setting];
            }
        }
    }
    
    if (empty($cleanSettings)) {
        throw new Exception('Aucun paramètre valide fourni');
    }
    
    // Vérifier si les paramètres existent
    $existing = $db->fetchOne(
        "SELECT id FROM user_notification_settings WHERE user_id = :user_id",
        ['user_id' => $userId]
    );
    
    if ($existing) {
        // Mettre à jour
        $cleanSettings['updated_at'] = date('Y-m-d H:i:s');
        $db->update('user_notification_settings', 
            $cleanSettings, 
            'user_id = :user_id', 
            ['user_id' => $userId]
        );
    } else {
        // Créer
        $cleanSettings['user_id'] = $userId;
        $db->insert('user_notification_settings', $cleanSettings);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Paramètres mis à jour avec succès'
    ]);
}

function handleDeleteRequest() {
    global $db, $userId;
    
    $notificationId = intval($_GET['id'] ?? 0);
    
    if (!$notificationId) {
        throw new Exception('ID de notification requis');
    }
    
    $deleted = $db->delete('notifications', 
        'id = :id AND user_id = :user_id', 
        ['id' => $notificationId, 'user_id' => $userId]
    );
    
    if ($deleted->rowCount() === 0) {
        throw new Exception('Notification non trouvée');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Notification supprimée'
    ]);
}
?>