<?php
/**
 * Serveur WebSocket pour IDEM - Notifications et messages temps réel
 */

require_once 'config/database.php';
require_once 'config/session.php';

require_once 'ratchet/src/Ratchet/Server/IoServer.php';
require_once 'ratchet/src/Ratchet/Http/HttpServer.php';
require_once 'ratchet/src/Ratchet/WebSocket/WsServer.php';
require_once 'ratchet/src/Ratchet/MessageComponentInterface.php';
require_once 'ratchet/src/Ratchet/ConnectionInterface.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class WebSocketServer implements MessageComponentInterface {
    protected $clients;
    protected $userConnections;
    protected $db;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->userConnections = [];
        $this->db = initDatabase();

        echo "Serveur WebSocket IDEM démarré...\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "Nouvelle connexion: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        try {
            $data = json_decode($msg, true);

            if (!$data || !isset($data['type'])) {
                return;
            }

            switch ($data['type']) {
                case 'auth':
                    $this->handleAuth($from, $data);
                    break;
                case 'typing':
                    $this->handleTyping($from, $data);
                    break;
                case 'stop_typing':
                    $this->handleStopTyping($from, $data);
                    break;
                case 'ping':
                    $this->handlePing($from);
                    break;
                default:
                    echo "Type de message non reconnu: {$data['type']}\n";
            }
        } catch (Exception $e) {
            echo "Erreur traitement message: " . $e->getMessage() . "\n";
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);

        // Supprimer l'utilisateur des connexions actives
        foreach ($this->userConnections as $userId => $userConn) {
            if ($userConn === $conn) {
                unset($this->userConnections[$userId]);
                $this->broadcastUserStatus($userId, 'offline');
                break;
            }
        }

        echo "Connexion fermée: {$conn->resourceId}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Erreur WebSocket: " . $e->getMessage() . "\n";
        $conn->close();
    }

    protected function handleAuth(ConnectionInterface $conn, $data) {
        $sessionId = $data['session_id'] ?? '';

        if (empty($sessionId)) {
            $conn->send(json_encode([
                'type' => 'auth_error',
                'message' => 'Session ID requis'
            ]));
            return;
        }

        // Vérifier la session
        $userId = $this->validateSession($sessionId);

        if ($userId) {
            $this->userConnections[$userId] = $conn;
            $conn->userId = $userId;

            // Confirmer l'authentification
            $conn->send(json_encode([
                'type' => 'auth_success',
                'user_id' => $userId
            ]));

            // Notifier les autres utilisateurs que cet utilisateur est en ligne
            $this->broadcastUserStatus($userId, 'online');

            // Envoyer les notifications non lues
            $this->sendUnreadNotifications($conn, $userId);

            echo "Utilisateur authentifié: {$userId}\n";
        } else {
            $conn->send(json_encode([
                'type' => 'auth_error',
                'message' => 'Session invalide'
            ]));
        }
    }

    protected function handleTyping(ConnectionInterface $from, $data) {
        if (!isset($from->userId)) return;

        $conversationId = $data['conversation_id'] ?? 0;

        if (!$conversationId) return;

        // Récupérer les autres participants de la conversation
        $participants = $this->getConversationParticipants($conversationId, $from->userId);

        // Envoyer l'indicateur de frappe aux autres participants
        foreach ($participants as $participantId) {
            if (isset($this->userConnections[$participantId])) {
                $this->userConnections[$participantId]->send(json_encode([
                    'type' => 'typing',
                    'user_id' => $from->userId,
                    'conversation_id' => $conversationId
                ]));
            }
        }
    }

    protected function handleStopTyping(ConnectionInterface $from, $data) {
        if (!isset($from->userId)) return;

        $conversationId = $data['conversation_id'] ?? 0;

        if (!$conversationId) return;

        $participants = $this->getConversationParticipants($conversationId, $from->userId);

        foreach ($participants as $participantId) {
            if (isset($this->userConnections[$participantId])) {
                $this->userConnections[$participantId]->send(json_encode([
                    'type' => 'stop_typing',
                    'user_id' => $from->userId,
                    'conversation_id' => $conversationId
                ]));
            }
        }
    }

    protected function handlePing(ConnectionInterface $from) {
        $from->send(json_encode(['type' => 'pong']));
    }

    protected function validateSession($sessionId) {
        // Dans un vrai système, vérifier la session en base
        // Pour la démo, on retourne un ID utilisateur fictif
        session_id($sessionId);
        session_start();

        return $_SESSION['user_id'] ?? null;
    }

    protected function broadcastUserStatus($userId, $status) {
        $message = json_encode([
            'type' => 'user_status',
            'user_id' => $userId,
            'status' => $status,
            'timestamp' => date('c')
        ]);

        foreach ($this->userConnections as $conn) {
            $conn->send($message);
        }
    }

    protected function sendUnreadNotifications(ConnectionInterface $conn, $userId) {
        try {
            $notifications = $this->db->fetchAll(
                "SELECT COUNT(*) as count FROM notifications 
                 WHERE user_id = :user_id AND is_read = 0",
                ['user_id' => $userId]
            );

            $count = $notifications[0]['count'] ?? 0;

            $conn->send(json_encode([
                'type' => 'unread_count',
                'count' => intval($count)
            ]));
        } catch (Exception $e) {
            echo "Erreur récupération notifications: " . $e->getMessage() . "\n";
        }
    }

    protected function getConversationParticipants($conversationId, $excludeUserId) {
        try {
            $participants = $this->db->fetchAll(
                "SELECT user_id FROM conversation_participants 
                 WHERE conversation_id = :conv_id AND user_id != :user_id AND left_at IS NULL",
                ['conv_id' => $conversationId, 'user_id' => $excludeUserId]
            );

            return array_column($participants, 'user_id');
        } catch (Exception $e) {
            echo "Erreur récupération participants: " . $e->getMessage() . "\n";
            return [];
        }
    }

    // Méthodes publiques pour envoyer des notifications
    public function sendNewMessage($conversationId, $message) {
        $participants = $this->getConversationParticipants($conversationId, $message['sender_id']);

        foreach ($participants as $participantId) {
            if (isset($this->userConnections[$participantId])) {
                $this->userConnections[$participantId]->send(json_encode([
                    'type' => 'new_message',
                    'conversation_id' => $conversationId,
                    'message' => $message
                ]));
            }
        }
    }

    public function sendNotification($userId, $notification) {
        if (isset($this->userConnections[$userId])) {
            $this->userConnections[$userId]->send(json_encode([
                'type' => 'notification',
                'notification' => $notification
            ]));
        }
    }
}

// Démarrer le serveur WebSocket
if (php_sapi_name() === 'cli') {
    $server = IoServer::factory(
        new HttpServer(
            new WsServer(
                new WebSocketServer()
            )
        ),
        8080
    );

    echo "Serveur WebSocket IDEM en écoute sur le port 8080\n";
    $server->run();
} else {
    echo "Ce script doit être exécuté en ligne de commande\n";
}
?>