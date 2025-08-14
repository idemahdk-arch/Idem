/**
 * Gestionnaire de notifications temps réel pour IDEM
 */

class NotificationSystem {
    constructor() {
        this.websocket = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 1000;
        this.isConnected = false;
        this.userId = window.userId || null;
        this.csrfToken = window.csrfToken || '';
        
        this.init();
    }

    init() {
        if (!this.userId) {
            console.log('Utilisateur non connecté - notifications désactivées');
            return;
        }

        this.connectWebSocket();
        this.setupEventListeners();
        this.loadInitialNotificationCount();
        this.setupPeriodicUpdates();
    }

    connectWebSocket() {
        if (this.websocket && this.websocket.readyState === WebSocket.OPEN) {
            return;
        }

        const protocol = window.location.protocol === "https:" ? "wss:" : "ws:";
        const wsUrl = `${protocol}//${window.location.hostname}:8080`;
        
        try {
            this.websocket = new WebSocket(wsUrl);
            
            this.websocket.onopen = () => {
                console.log('WebSocket connecté');
                this.isConnected = true;
                this.reconnectAttempts = 0;
                
                // S'authentifier
                this.authenticate();
                
                // Mettre à jour l'indicateur de connexion
                this.updateConnectionStatus(true);
            };
            
            this.websocket.onmessage = (event) => {
                this.handleWebSocketMessage(event);
            };
            
            this.websocket.onclose = () => {
                console.log('WebSocket fermé');
                this.isConnected = false;
                this.updateConnectionStatus(false);
                this.scheduleReconnect();
            };
            
            this.websocket.onerror = (error) => {
                console.error('Erreur WebSocket:', error);
                this.isConnected = false;
                this.updateConnectionStatus(false);
            };
            
        } catch (error) {
            console.error('Impossible de se connecter au WebSocket:', error);
            this.fallbackToPolling();
        }
    }

    authenticate() {
        if (this.websocket && this.websocket.readyState === WebSocket.OPEN) {
            this.websocket.send(JSON.stringify({
                type: 'auth',
                session_id: this.getSessionId()
            }));
        }
    }

    handleWebSocketMessage(event) {
        try {
            const data = JSON.parse(event.data);
            
            switch (data.type) {
                case 'auth_success':
                    console.log('Authentification WebSocket réussie');
                    break;
                case 'auth_error':
                    console.error('Erreur authentification WebSocket:', data.message);
                    this.fallbackToPolling();
                    break;
                case 'notification':
                    this.handleNewNotification(data.notification);
                    break;
                case 'unread_count':
                    this.updateNotificationBadge(data.count);
                    break;
                case 'new_message':
                    this.handleNewMessage(data);
                    break;
                case 'typing':
                    this.handleTypingIndicator(data);
                    break;
                case 'stop_typing':
                    this.handleStopTyping(data);
                    break;
                case 'user_status':
                    this.handleUserStatusUpdate(data);
                    break;
                case 'pong':
                    // Heartbeat response
                    break;
                default:
                    console.log('Message WebSocket non géré:', data.type);
            }
        } catch (error) {
            console.error('Erreur parsing message WebSocket:', error);
        }
    }

    handleNewNotification(notification) {
        // Mettre à jour le badge
        this.incrementNotificationBadge();
        
        // Afficher la notification
        this.showNotification(notification);
        
        // Jouer un son si autorisé
        this.playNotificationSound();
        
        // Notification navigateur si autorisé
        this.showBrowserNotification(notification);
    }

    handleNewMessage(data) {
        // Incrémenter le badge de messages
        this.incrementMessageBadge();
        
        // Si on est sur la page messages, mettre à jour l'interface
        if (window.messagingSystem) {
            window.messagingSystem.handleNewMessage(data);
        }
        
        // Notification desktop
        this.showBrowserNotification({
            type: 'message',
            message: `Nouveau message de ${data.message.sender_name}`,
            sender_name: data.message.sender_name
        });
    }

    showNotification(notification) {
        // Créer un toast de notification
        const toast = document.createElement('div');
        toast.className = 'notification-toast';
        toast.innerHTML = `
            <div class="notification-toast-icon">
                <i class="fas ${this.getNotificationIcon(notification.type)}"></i>
            </div>
            <div class="notification-toast-content">
                <div class="notification-toast-title">Nouvelle notification</div>
                <div class="notification-toast-message">${this.formatNotificationMessage(notification)}</div>
            </div>
            <button class="notification-toast-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        // Ajouter au container
        let container = document.getElementById('notification-toasts');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notification-toasts';
            container.className = 'notification-toasts-container';
            document.body.appendChild(container);
        }
        
        container.appendChild(toast);
        
        // Animation d'entrée
        setTimeout(() => {
            toast.classList.add('show');
        }, 100);
        
        // Auto-suppression après 5 secondes
        setTimeout(() => {
            toast.classList.add('fade-out');
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.parentElement.removeChild(toast);
                }
            }, 300);
        }, 5000);
        
        // Clic pour rediriger
        toast.addEventListener('click', () => {
            this.redirectToNotification(notification);
            toast.remove();
        });
    }

    showBrowserNotification(notification) {
        if (!('Notification' in window) || Notification.permission !== 'granted') {
            return;
        }

        const title = notification.type === 'message' ? 
            'Nouveau message' : 'Nouvelle notification';
        
        const options = {
            body: this.formatNotificationMessage(notification),
            icon: '/assets/images/logo.png',
            badge: '/assets/images/badge.png',
            tag: `notification-${notification.id || Date.now()}`,
            renotify: false,
            requireInteraction: false
        };

        const browserNotification = new Notification(title, options);
        
        browserNotification.onclick = () => {
            window.focus();
            this.redirectToNotification(notification);
            browserNotification.close();
        };

        // Auto-fermeture après 8 secondes
        setTimeout(() => {
            browserNotification.close();
        }, 8000);
    }

    updateNotificationBadge(count) {
        const badge = document.getElementById('notifications-badge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }
    }

    incrementNotificationBadge() {
        const badge = document.getElementById('notifications-badge');
        if (badge) {
            const current = parseInt(badge.textContent) || 0;
            this.updateNotificationBadge(current + 1);
        }
    }

    async loadInitialNotificationCount() {
        try {
            const response = await apiRequest('/api/notifications.php?action=unread_count');
            if (response.success) {
                this.updateNotificationBadge(response.unread_count);
            }
        } catch (error) {
            console.error('Erreur chargement nombre notifications:', error);
        }
    }

    scheduleReconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.log('Nombre maximum de tentatives de reconnexion atteint');
            this.fallbackToPolling();
            return;
        }

        this.reconnectAttempts++;
        const delay = this.reconnectDelay * Math.pow(2, this.reconnectAttempts - 1);
        
        console.log(`Reconnexion dans ${delay}ms (tentative ${this.reconnectAttempts})`);
        
        setTimeout(() => {
            this.connectWebSocket();
        }, delay);
    }

    fallbackToPolling() {
        console.log('Passage en mode polling pour les notifications');
        
        // Polling toutes les 30 secondes
        setInterval(async () => {
            try {
                const response = await apiRequest('/api/notifications.php?action=unread_count');
                if (response.success) {
                    this.updateNotificationBadge(response.unread_count);
                }
            } catch (error) {
                console.error('Erreur polling notifications:', error);
            }
        }, 30000);
    }

    setupPeriodicUpdates() {
        // Heartbeat WebSocket toutes les 30 secondes
        setInterval(() => {
            if (this.websocket && this.websocket.readyState === WebSocket.OPEN) {
                this.websocket.send(JSON.stringify({ type: 'ping' }));
            }
        }, 30000);

        // Mise à jour du statut utilisateur
        this.updateUserLastSeen();
        setInterval(() => {
            this.updateUserLastSeen();
        }, 60000); // Toutes les minutes
    }

    async updateUserLastSeen() {
        try {
            await apiRequest('/api/users.php', {
                method: 'PUT',
                body: JSON.stringify({
                    action: 'update_last_seen'
                })
            });
        } catch (error) {
            // Silencieux - pas critique
        }
    }

    setupEventListeners() {
        // Demander permission pour les notifications navigateur
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        // Gérer la visibilité de la page
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && this.websocket && this.websocket.readyState !== WebSocket.OPEN) {
                this.connectWebSocket();
            }
        });

        // Reconnecter lors du focus
        window.addEventListener('focus', () => {
            if (!this.isConnected) {
                this.connectWebSocket();
            }
        });
    }

    updateConnectionStatus(connected) {
        const indicator = document.getElementById('connection-status');
        if (indicator) {
            indicator.className = connected ? 'connection-online' : 'connection-offline';
            indicator.title = connected ? 'En ligne' : 'Hors ligne';
        }
    }

    getNotificationIcon(type) {
        const icons = {
            'like': 'fa-heart',
            'comment': 'fa-comment',
            'mention': 'fa-at',
            'friend_request': 'fa-user-plus',
            'message': 'fa-envelope',
            'share': 'fa-share'
        };
        return icons[type] || 'fa-bell';
    }

    formatNotificationMessage(notification) {
        if (notification.message) {
            return notification.message;
        }

        const senderName = notification.sender_name || 'Quelqu\'un';
        
        switch (notification.type) {
            case 'like':
                return `${senderName} a aimé votre publication`;
            case 'comment':
                return `${senderName} a commenté votre publication`;
            case 'mention':
                return `${senderName} vous a mentionné`;
            case 'friend_request':
                return `${senderName} vous a envoyé une demande d'ami`;
            case 'message':
                return `${senderName} vous a envoyé un message`;
            default:
                return 'Nouvelle notification';
        }
    }

    redirectToNotification(notification) {
        switch (notification.type) {
            case 'like':
            case 'comment':
            case 'share':
                if (notification.post_id) {
                    window.location.href = `/feed.php#post-${notification.post_id}`;
                } else {
                    window.location.href = '/feed.php';
                }
                break;
            case 'message':
                if (notification.conversation_id) {
                    window.location.href = `/messages.php#conversation-${notification.conversation_id}`;
                } else {
                    window.location.href = '/messages.php';
                }
                break;
            case 'friend_request':
                window.location.href = '/friends.php';
                break;
            default:
                window.location.href = '/notifications.php';
        }
    }

    playNotificationSound() {
        // Jouer un son de notification subtil
        if (typeof Audio !== 'undefined') {
            const audio = new Audio('/assets/sounds/notification.mp3');
            audio.volume = 0.3;
            audio.play().catch(() => {
                // Ignorer les erreurs de lecture audio
            });
        }
    }

    getSessionId() {
        // Récupérer l'ID de session PHP
        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === 'PHPSESSID') {
                return value;
            }
        }
        return '';
    }

    // Méthodes publiques pour d'autres scripts
    sendTyping(conversationId) {
        if (this.websocket && this.websocket.readyState === WebSocket.OPEN) {
            this.websocket.send(JSON.stringify({
                type: 'typing',
                conversation_id: conversationId
            }));
        }
    }

    sendStopTyping(conversationId) {
        if (this.websocket && this.websocket.readyState === WebSocket.OPEN) {
            this.websocket.send(JSON.stringify({
                type: 'stop_typing',
                conversation_id: conversationId
            }));
        }
    }

    handleTypingIndicator(data) {
        if (window.messagingSystem) {
            window.messagingSystem.showTypingIndicator(data.user_id, data.conversation_id);
        }
    }

    handleStopTyping(data) {
        if (window.messagingSystem) {
            window.messagingSystem.hideTypingIndicator(data.user_id);
        }
    }

    handleUserStatusUpdate(data) {
        // Mettre à jour les indicateurs de statut utilisateur
        document.querySelectorAll(`[data-user-id="${data.user_id}"] .online-indicator`).forEach(indicator => {
            indicator.style.display = data.status === 'online' ? 'block' : 'none';
        });
    }

    incrementMessageBadge() {
        // Incrémenter le badge de messages (si existant)
        const messageLink = document.querySelector('a[href*="messages.php"] .badge');
        if (messageLink) {
            const current = parseInt(messageLink.textContent) || 0;
            messageLink.textContent = current + 1;
            messageLink.style.display = 'flex';
        }
    }
}

// Styles CSS pour les toasts de notification
const notificationStyles = `
<style>
.notification-toasts-container {
    position: fixed;
    top: 80px;
    right: 20px;
    z-index: 9999;
    pointer-events: none;
}

.notification-toast {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
    padding: var(--spacing-md);
    margin-bottom: var(--spacing-sm);
    max-width: 400px;
    display: flex;
    align-items: flex-start;
    gap: var(--spacing-md);
    opacity: 0;
    transform: translateX(100%);
    transition: all 0.3s ease;
    pointer-events: auto;
    cursor: pointer;
    backdrop-filter: blur(10px);
}

.notification-toast.show {
    opacity: 1;
    transform: translateX(0);
}

.notification-toast.fade-out {
    opacity: 0;
    transform: translateX(100%);
}

.notification-toast:hover {
    transform: translateX(-5px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.16);
}

.notification-toast-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-full);
    background: var(--primary-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.notification-toast-content {
    flex: 1;
    min-width: 0;
}

.notification-toast-title {
    font-weight: 600;
    font-size: var(--text-sm);
    color: var(--text-primary);
    margin-bottom: var(--spacing-xs);
}

.notification-toast-message {
    font-size: var(--text-sm);
    color: var(--text-secondary);
    line-height: 1.4;
}

.notification-toast-close {
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: var(--spacing-xs);
    border-radius: var(--radius-sm);
    transition: all var(--transition-fast);
    flex-shrink: 0;
}

.notification-toast-close:hover {
    background: var(--bg-secondary);
    color: var(--text-primary);
}

@media (max-width: 768px) {
    .notification-toasts-container {
        left: 20px;
        right: 20px;
        top: 70px;
    }
    
    .notification-toast {
        max-width: none;
    }
}
</style>
`;

// Injecter les styles
document.head.insertAdjacentHTML('beforeend', notificationStyles);

// Variable globale
window.notificationSystem = null;

// Initialisation automatique
document.addEventListener('DOMContentLoaded', () => {
    if (window.userId) {
        window.notificationSystem = new NotificationSystem();
    }
});