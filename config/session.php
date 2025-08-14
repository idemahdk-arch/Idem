<?php
/**
 * Gestion des sessions sécurisées pour IDEM
 */

class SessionManager {
    
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            // Configuration sécurisée des sessions
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_secure', 0); // Mettre à 1 en HTTPS
            ini_set('session.gc_maxlifetime', 7200); // 2 heures
            
            session_start();
            
            // Régénération de l'ID de session pour éviter la fixation
            if (!isset($_SESSION['initiated'])) {
                session_regenerate_id(true);
                $_SESSION['initiated'] = true;
            }
            
            // Vérifier l'expiration de la session
            self::checkSessionTimeout();
        }
    }
    
    public static function login($user) {
        self::start();
        
        // Régénérer l'ID pour éviter la fixation de session
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        // Mettre à jour le statut en ligne
        self::updateLastSeen($user['id']);
    }
    
    public static function logout() {
        self::start();
        
        // Détruire toutes les données de session
        $_SESSION = array();
        
        // Détruire le cookie de session
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
    
    public static function isLoggedIn() {
        self::start();
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    public static function getUserId() {
        return self::isLoggedIn() ? $_SESSION['user_id'] : null;
    }
    
    public static function getUsername() {
        return self::isLoggedIn() ? $_SESSION['username'] : null;
    }
    
    public static function getCsrfToken() {
        self::start();
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validateCsrfToken($token) {
        self::start();
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit();
        }
    }
    
    private static function checkSessionTimeout() {
        $timeout = 7200; // 2 heures en secondes
        
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity']) > $timeout) {
            self::logout();
            header('Location: /login.php?timeout=1');
            exit();
        }
        
        $_SESSION['last_activity'] = time();
    }
    
    public static function updateLastSeen($userId) {
        try {
            $db = Database::getInstance();
            $db->update('users', 
                ['last_seen' => date('Y-m-d H:i:s')], 
                'id = :id', 
                ['id' => $userId]
            );
        } catch (Exception $e) {
            error_log("Erreur mise à jour last_seen: " . $e->getMessage());
        }
    }
    
    public static function setFlashMessage($message, $type = 'info') {
        self::start();
        $_SESSION['flash_messages'][] = [
            'message' => $message,
            'type' => $type
        ];
    }
    
    public static function getFlashMessages() {
        self::start();
        if (isset($_SESSION['flash_messages'])) {
            $messages = $_SESSION['flash_messages'];
            unset($_SESSION['flash_messages']);
            return $messages;
        }
        return [];
    }
    
    public static function getCurrentUser() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        try {
            $db = Database::getInstance();
            return $db->fetchOne(
                "SELECT id, username, email, first_name, last_name, avatar, bio, 
                        privacy_level, show_online, allow_messages, last_seen
                 FROM users WHERE id = :id AND is_active = true",
                ['id' => self::getUserId()]
            );
        } catch (Exception $e) {
            error_log("Erreur récupération utilisateur: " . $e->getMessage());
            return null;
        }
    }
}
?>