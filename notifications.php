<?php
/**
 * Page des notifications IDEM
 */

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

SessionManager::requireLogin();

$pageTitle = "Notifications";
$pageDescription = "Restez informé de toute l'activité sur votre compte";
$bodyClass = "notifications-page";

$db = initDatabase();
$currentUser = SessionManager::getCurrentUser();

include 'includes/header.php';
?>

<div class="notifications-container">
    <div class="notifications-layout">
        <!-- Header des notifications -->
        <div class="notifications-header">
            <div class="header-left">
                <h1>Notifications</h1>
                <div class="notifications-stats">
                    <span class="unread-count" id="unread-count">0</span>
                    <span class="total-count" id="total-count">notifications</span>
                </div>
            </div>
            
            <div class="header-actions">
                <div class="filter-tabs">
                    <button type="button" class="filter-tab active" data-filter="all">
                        Toutes
                    </button>
                    <button type="button" class="filter-tab" data-filter="unread">
                        Non lues
                    </button>
                    <button type="button" class="filter-tab" data-filter="mentions">
                        Mentions
                    </button>
                    <button type="button" class="filter-tab" data-filter="likes">
                        J'aime
                    </button>
                    <button type="button" class="filter-tab" data-filter="friends">
                        Amis
                    </button>
                </div>
                
                <div class="action-buttons">
                    <button type="button" class="btn btn-outline" id="mark-all-read">
                        <i class="fas fa-check-double"></i>
                        Tout marquer comme lu
                    </button>
                    <button type="button" class="btn btn-outline" id="notification-settings">
                        <i class="fas fa-cog"></i>
                        Paramètres
                    </button>
                </div>
            </div>
        </div>

        <!-- Liste des notifications -->
        <div class="notifications-content">
            <div class="notifications-list" id="notifications-list">
                <!-- Notifications chargées dynamiquement -->
                <div class="loading-notifications">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Chargement des notifications...</p>
                </div>
            </div>
            
            <!-- Pagination -->
            <div class="notifications-pagination" id="notifications-pagination" style="display: none;">
                <button type="button" class="btn btn-outline" id="load-more-btn">
                    Charger plus
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal paramètres notifications -->
<div class="modal" id="notification-settings-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Paramètres des notifications</h3>
            <button type="button" class="modal-close" onclick="closeModal('notification-settings-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="notification-settings-form">
                <div class="settings-section">
                    <h4>Notifications push</h4>
                    <div class="setting-item">
                        <label class="setting-label">
                            <input type="checkbox" name="push_enabled" id="push-enabled">
                            <span>Activer les notifications push</span>
                        </label>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h4>Types de notifications</h4>
                    <div class="setting-item">
                        <label class="setting-label">
                            <input type="checkbox" name="notify_likes" id="notify-likes" checked>
                            <span>Nouveaux j'aime sur mes publications</span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <label class="setting-label">
                            <input type="checkbox" name="notify_comments" id="notify-comments" checked>
                            <span>Nouveaux commentaires</span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <label class="setting-label">
                            <input type="checkbox" name="notify_mentions" id="notify-mentions" checked>
                            <span>Mentions dans les publications</span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <label class="setting-label">
                            <input type="checkbox" name="notify_friends" id="notify-friends" checked>
                            <span>Demandes d'amis</span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <label class="setting-label">
                            <input type="checkbox" name="notify_messages" id="notify-messages" checked>
                            <span>Nouveaux messages</span>
                        </label>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h4>Fréquence</h4>
                    <div class="setting-item">
                        <label class="setting-label">
                            Résumé par email
                            <select name="email_frequency" id="email-frequency">
                                <option value="never">Jamais</option>
                                <option value="daily">Quotidien</option>
                                <option value="weekly" selected>Hebdomadaire</option>
                                <option value="monthly">Mensuel</option>
                            </select>
                        </label>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">
                        Enregistrer
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('notification-settings-modal')">
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Styles pour les notifications */
.notifications-page .main-content {
    padding: 0;
    background: var(--bg-secondary);
}

.notifications-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: var(--spacing-lg);
}

.notifications-layout {
    background: var(--bg-primary);
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.notifications-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--spacing-lg);
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-primary);
    flex-wrap: wrap;
    gap: var(--spacing-md);
}

.header-left h1 {
    margin: 0 0 var(--spacing-xs) 0;
    font-size: var(--text-2xl);
    color: var(--text-primary);
}

.notifications-stats {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    font-size: var(--text-sm);
    color: var(--text-secondary);
}

.unread-count {
    background: var(--primary-color);
    color: white;
    padding: 2px 8px;
    border-radius: var(--radius-full);
    font-weight: 600;
    min-width: 20px;
    text-align: center;
}

.filter-tabs {
    display: flex;
    gap: var(--spacing-xs);
    background: var(--bg-secondary);
    padding: 4px;
    border-radius: var(--radius-md);
}

.filter-tab {
    padding: var(--spacing-sm) var(--spacing-md);
    background: none;
    border: none;
    border-radius: var(--radius-sm);
    color: var(--text-secondary);
    font-size: var(--text-sm);
    cursor: pointer;
    transition: all var(--transition-fast);
    white-space: nowrap;
}

.filter-tab.active,
.filter-tab:hover {
    background: var(--bg-primary);
    color: var(--primary-color);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.action-buttons {
    display: flex;
    gap: var(--spacing-sm);
}

.notifications-content {
    min-height: 400px;
}

.notifications-list {
    display: flex;
    flex-direction: column;
}

.notification-item {
    display: flex;
    align-items: flex-start;
    gap: var(--spacing-md);
    padding: var(--spacing-lg);
    border-bottom: 1px solid var(--border-color);
    cursor: pointer;
    transition: all var(--transition-fast);
    position: relative;
}

.notification-item:hover {
    background: var(--bg-secondary);
}

.notification-item.unread {
    background: rgba(var(--primary-rgb), 0.05);
    border-left: 3px solid var(--primary-color);
}

.notification-item.unread::before {
    content: '';
    position: absolute;
    top: var(--spacing-lg);
    right: var(--spacing-lg);
    width: 8px;
    height: 8px;
    background: var(--primary-color);
    border-radius: var(--radius-full);
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-full);
    background: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--text-lg);
    color: var(--text-secondary);
    flex-shrink: 0;
    position: relative;
}

.notification-icon.like { background: rgba(var(--danger-rgb), 0.1); color: var(--danger-color); }
.notification-icon.comment { background: rgba(var(--info-rgb), 0.1); color: var(--info-color); }
.notification-icon.friend { background: rgba(var(--success-rgb), 0.1); color: var(--success-color); }
.notification-icon.mention { background: rgba(var(--warning-rgb), 0.1); color: var(--warning-color); }
.notification-icon.message { background: rgba(var(--primary-rgb), 0.1); color: var(--primary-color); }

.notification-avatar {
    position: absolute;
    bottom: -2px;
    right: -2px;
    width: 20px;
    height: 20px;
    border-radius: var(--radius-full);
    border: 2px solid var(--bg-primary);
    object-fit: cover;
}

.notification-content {
    flex: 1;
    min-width: 0;
}

.notification-text {
    margin: 0 0 var(--spacing-xs) 0;
    font-size: var(--text-base);
    line-height: 1.4;
    color: var(--text-primary);
}

.notification-text .username {
    font-weight: 600;
    color: var(--primary-color);
}

.notification-meta {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    font-size: var(--text-sm);
    color: var(--text-secondary);
}

.notification-time {
    color: var(--text-muted);
}

.notification-actions {
    display: flex;
    gap: var(--spacing-xs);
    margin-top: var(--spacing-sm);
}

.notification-action {
    padding: var(--spacing-xs) var(--spacing-sm);
    background: var(--bg-secondary);
    border: none;
    border-radius: var(--radius-sm);
    font-size: var(--text-xs);
    color: var(--text-secondary);
    cursor: pointer;
    transition: all var(--transition-fast);
}

.notification-action:hover {
    background: var(--primary-color);
    color: white;
}

.notification-action.accept {
    background: var(--success-color);
    color: white;
}

.notification-action.decline {
    background: var(--danger-color);
    color: white;
}

.loading-notifications {
    text-align: center;
    padding: var(--spacing-2xl);
    color: var(--text-muted);
}

.loading-notifications i {
    font-size: var(--text-xl);
    margin-bottom: var(--spacing-md);
}

.empty-notifications {
    text-align: center;
    padding: var(--spacing-2xl);
    color: var(--text-muted);
}

.empty-notifications i {
    font-size: 3rem;
    margin-bottom: var(--spacing-lg);
    color: var(--text-muted);
}

.notifications-pagination {
    padding: var(--spacing-lg);
    text-align: center;
    border-top: 1px solid var(--border-color);
}

/* Modal paramètres */
.settings-section {
    margin-bottom: var(--spacing-lg);
}

.settings-section h4 {
    margin-bottom: var(--spacing-md);
    font-size: var(--text-lg);
    color: var(--text-primary);
}

.setting-item {
    margin-bottom: var(--spacing-md);
}

.setting-label {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    cursor: pointer;
    font-size: var(--text-base);
    color: var(--text-primary);
}

.setting-label input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: var(--primary-color);
}

.setting-label select {
    margin-left: auto;
    padding: var(--spacing-xs) var(--spacing-sm);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    background: var(--bg-secondary);
    color: var(--text-primary);
}

.modal-actions {
    display: flex;
    gap: var(--spacing-md);
    justify-content: flex-end;
    margin-top: var(--spacing-lg);
    padding-top: var(--spacing-lg);
    border-top: 1px solid var(--border-color);
}

/* Responsive notifications */
@media (max-width: 768px) {
    .notifications-container {
        padding: var(--spacing-md);
    }
    
    .notifications-header {
        flex-direction: column;
        align-items: stretch;
        gap: var(--spacing-md);
    }
    
    .header-left {
        text-align: center;
    }
    
    .filter-tabs {
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .notification-item {
        padding: var(--spacing-md);
    }
    
    .notification-icon {
        width: 32px;
        height: 32px;
        font-size: var(--text-base);
    }
    
    .notification-avatar {
        width: 16px;
        height: 16px;
    }
    
    .notification-actions {
        flex-wrap: wrap;
    }
}

@media (max-width: 480px) {
    .filter-tabs {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: var(--spacing-xs);
    }
    
    .filter-tab {
        padding: var(--spacing-xs);
        font-size: var(--text-xs);
    }
    
    .notification-text {
        font-size: var(--text-sm);
    }
    
    .notification-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--spacing-xs);
    }
}

/* Animations */
@keyframes notificationSlideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.notification-item {
    animation: notificationSlideIn 0.3s ease;
}

.notification-item.new {
    animation: notificationSlideIn 0.5s ease, pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(var(--primary-rgb), 0.4); }
    50% { box-shadow: 0 0 0 10px rgba(var(--primary-rgb), 0); }
}
</style>

<script>
// Gestionnaire de notifications
class NotificationManager {
    constructor() {
        this.currentFilter = 'all';
        this.currentPage = 1;
        this.hasMore = true;
        this.notifications = [];
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadNotifications();
        this.setupPushNotifications();
        this.loadSettings();
    }

    setupEventListeners() {
        // Filtres
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                this.switchFilter(e.target.dataset.filter);
            });
        });

        // Actions header
        document.getElementById('mark-all-read').addEventListener('click', () => {
            this.markAllAsRead();
        });

        document.getElementById('notification-settings').addEventListener('click', () => {
            document.getElementById('notification-settings-modal').style.display = 'block';
        });

        // Charger plus
        document.getElementById('load-more-btn').addEventListener('click', () => {
            this.loadMoreNotifications();
        });

        // Formulaire paramètres
        document.getElementById('notification-settings-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveSettings();
        });
    }

    async loadNotifications(reset = true) {
        if (reset) {
            this.currentPage = 1;
            this.notifications = [];
            document.getElementById('notifications-list').innerHTML = `
                <div class="loading-notifications">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Chargement des notifications...</p>
                </div>
            `;
        }

        try {
            const response = await apiRequest(`/api/notifications.php?action=list&filter=${this.currentFilter}&page=${this.currentPage}`);
            
            if (response.success) {
                if (reset) {
                    this.notifications = response.notifications;
                } else {
                    this.notifications.push(...response.notifications);
                }
                
                this.hasMore = response.has_more;
                this.renderNotifications();
                this.updateStats(response.stats);
            }
        } catch (error) {
            console.error('Erreur chargement notifications:', error);
            this.showError();
        }
    }

    renderNotifications() {
        const container = document.getElementById('notifications-list');
        
        if (this.notifications.length === 0) {
            container.innerHTML = `
                <div class="empty-notifications">
                    <i class="fas fa-bell"></i>
                    <h3>Aucune notification</h3>
                    <p>Vous êtes à jour !</p>
                </div>
            `;
            return;
        }

        container.innerHTML = this.notifications.map(notif => this.createNotificationHTML(notif)).join('');
        
        // Ajouter les event listeners
        container.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', (e) => {
                if (!e.target.closest('.notification-action')) {
                    this.handleNotificationClick(item.dataset.notificationId);
                }
            });
        });

        // Pagination
        const pagination = document.getElementById('notifications-pagination');
        pagination.style.display = this.hasMore ? 'block' : 'none';
    }

    createNotificationHTML(notification) {
        const isUnread = !notification.read_at;
        const iconType = this.getIconType(notification.type);
        const timeAgo = formatTimeAgo(notification.created_at);
        
        return `
            <div class="notification-item ${isUnread ? 'unread' : ''}" 
                 data-notification-id="${notification.id}"
                 data-type="${notification.type}">
                <div class="notification-icon ${iconType.class}">
                    <i class="${iconType.icon}"></i>
                    ${notification.sender_avatar ? 
                        `<img src="/uploads/avatars/${notification.sender_avatar}" 
                             alt="Avatar" class="notification-avatar">` : ''}
                </div>
                
                <div class="notification-content">
                    <p class="notification-text">
                        ${this.formatNotificationText(notification)}
                    </p>
                    <div class="notification-meta">
                        <span class="notification-time">${timeAgo}</span>
                        ${notification.post_id ? '<span>• Publication</span>' : ''}
                        ${notification.conversation_id ? '<span>• Message</span>' : ''}
                    </div>
                    ${this.createNotificationActions(notification)}
                </div>
            </div>
        `;
    }

    getIconType(type) {
        const types = {
            'like': { icon: 'fas fa-heart', class: 'like' },
            'comment': { icon: 'fas fa-comment', class: 'comment' },
            'mention': { icon: 'fas fa-at', class: 'mention' },
            'friend_request': { icon: 'fas fa-user-plus', class: 'friend' },
            'message': { icon: 'fas fa-envelope', class: 'message' },
            'share': { icon: 'fas fa-share', class: 'share' },
            'follow': { icon: 'fas fa-user-check', class: 'follow' }
        };
        return types[type] || { icon: 'fas fa-bell', class: 'default' };
    }

    formatNotificationText(notification) {
        const senderName = `<span class="username">${escapeHtml(notification.sender_name)}</span>`;
        
        switch (notification.type) {
            case 'like':
                return `${senderName} a aimé votre publication`;
            case 'comment':
                return `${senderName} a commenté votre publication`;
            case 'mention':
                return `${senderName} vous a mentionné dans une publication`;
            case 'friend_request':
                return `${senderName} vous a envoyé une demande d'ami`;
            case 'message':
                return `${senderName} vous a envoyé un message`;
            case 'share':
                return `${senderName} a partagé votre publication`;
            case 'follow':
                return `${senderName} a commencé à vous suivre`;
            default:
                return escapeHtml(notification.message || 'Nouvelle notification');
        }
    }

    createNotificationActions(notification) {
        if (notification.type === 'friend_request' && !notification.friend_request_handled) {
            return `
                <div class="notification-actions">
                    <button type="button" class="notification-action accept" 
                            onclick="notificationManager.handleFriendRequest(${notification.sender_id}, 'accept')">
                        Accepter
                    </button>
                    <button type="button" class="notification-action decline"
                            onclick="notificationManager.handleFriendRequest(${notification.sender_id}, 'decline')">
                        Refuser
                    </button>
                </div>
            `;
        }
        return '';
    }

    async handleNotificationClick(notificationId) {
        // Marquer comme lue
        await this.markAsRead(notificationId);
        
        // Rediriger vers l'élément concerné
        const notification = this.notifications.find(n => n.id == notificationId);
        if (notification) {
            this.navigateToNotification(notification);
        }
    }

    navigateToNotification(notification) {
        switch (notification.type) {
            case 'like':
            case 'comment':
            case 'share':
                if (notification.post_id) {
                    window.location.href = `/feed.php#post-${notification.post_id}`;
                }
                break;
            case 'message':
                if (notification.conversation_id) {
                    window.location.href = `/messages.php#conversation-${notification.conversation_id}`;
                }
                break;
            case 'friend_request':
                window.location.href = '/friends.php';
                break;
            default:
                window.location.href = '/feed.php';
        }
    }

    async markAsRead(notificationId) {
        try {
            await apiRequest('/api/notifications.php', {
                method: 'PUT',
                body: JSON.stringify({
                    action: 'mark_read',
                    notification_id: notificationId
                })
            });
            
            // Mettre à jour l'interface
            const item = document.querySelector(`[data-notification-id="${notificationId}"]`);
            if (item) {
                item.classList.remove('unread');
            }
            
            this.updateUnreadCount(-1);
        } catch (error) {
            console.error('Erreur marquer comme lu:', error);
        }
    }

    async markAllAsRead() {
        try {
            const response = await apiRequest('/api/notifications.php', {
                method: 'PUT',
                body: JSON.stringify({
                    action: 'mark_all_read'
                })
            });
            
            if (response.success) {
                document.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.remove('unread');
                });
                
                document.getElementById('unread-count').textContent = '0';
                showToast('Toutes les notifications marquées comme lues', 'success');
            }
        } catch (error) {
            console.error('Erreur marquer tout comme lu:', error);
            showToast('Erreur lors de la mise à jour', 'danger');
        }
    }

    async handleFriendRequest(userId, action) {
        try {
            const response = await apiRequest('/api/friends.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: action === 'accept' ? 'accept_request' : 'decline_request',
                    user_id: userId
                })
            });
            
            if (response.success) {
                showToast(
                    action === 'accept' ? 'Demande d\'ami acceptée' : 'Demande d\'ami refusée',
                    'success'
                );
                
                // Recharger les notifications
                this.loadNotifications();
            }
        } catch (error) {
            console.error('Erreur demande ami:', error);
            showToast('Erreur lors du traitement', 'danger');
        }
    }

    switchFilter(filter) {
        this.currentFilter = filter;
        
        // Mettre à jour l'interface
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelector(`[data-filter="${filter}"]`).classList.add('active');
        
        // Recharger les notifications
        this.loadNotifications(true);
    }

    async loadMoreNotifications() {
        this.currentPage++;
        await this.loadNotifications(false);
    }

    updateStats(stats) {
        document.getElementById('unread-count').textContent = stats.unread_count || 0;
        document.getElementById('total-count').textContent = 
            `${stats.total_count || 0} notification${stats.total_count > 1 ? 's' : ''}`;
    }

    updateUnreadCount(delta) {
        const element = document.getElementById('unread-count');
        const current = parseInt(element.textContent) || 0;
        const newCount = Math.max(0, current + delta);
        element.textContent = newCount;
    }

    async setupPushNotifications() {
        if ('Notification' in window && 'serviceWorker' in navigator) {
            const permission = await Notification.requestPermission();
            if (permission === 'granted') {
                document.getElementById('push-enabled').checked = true;
            }
        }
    }

    async loadSettings() {
        try {
            const response = await apiRequest('/api/notifications.php?action=settings');
            
            if (response.success) {
                const settings = response.settings;
                
                Object.keys(settings).forEach(key => {
                    const input = document.getElementById(key.replace('_', '-'));
                    if (input) {
                        if (input.type === 'checkbox') {
                            input.checked = settings[key];
                        } else {
                            input.value = settings[key];
                        }
                    }
                });
            }
        } catch (error) {
            console.error('Erreur chargement paramètres:', error);
        }
    }

    async saveSettings() {
        const form = document.getElementById('notification-settings-form');
        const formData = new FormData(form);
        const settings = {};
        
        for (const [key, value] of formData.entries()) {
            settings[key] = value === 'on' ? true : value;
        }
        
        try {
            const response = await apiRequest('/api/notifications.php', {
                method: 'PUT',
                body: JSON.stringify({
                    action: 'update_settings',
                    settings: settings
                })
            });
            
            if (response.success) {
                showToast('Paramètres enregistrés', 'success');
                closeModal('notification-settings-modal');
            }
        } catch (error) {
            console.error('Erreur sauvegarde paramètres:', error);
            showToast('Erreur lors de la sauvegarde', 'danger');
        }
    }

    showError() {
        document.getElementById('notifications-list').innerHTML = `
            <div class="error-notifications">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Erreur lors du chargement des notifications</p>
                <button type="button" class="btn btn-primary" onclick="notificationManager.loadNotifications()">
                    Réessayer
                </button>
            </div>
        `;
    }
}

// Initialisation
let notificationManager;

document.addEventListener('DOMContentLoaded', () => {
    notificationManager = new NotificationManager();
});
</script>

<?php include 'includes/footer.php'; ?>