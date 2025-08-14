<?php
/**
 * Page de messagerie temps réel IDEM
 */

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

SessionManager::requireLogin();

$pageTitle = "Messagerie";
$pageDescription = "Communiquez avec vos amis en temps réel";
$bodyClass = "messages-page";

$db = initDatabase();
$currentUser = SessionManager::getCurrentUser();

include 'includes/header.php';
?>

<div class="messages-container">
    <div class="messages-layout">
        <!-- Liste des conversations -->
        <aside class="conversations-sidebar">
            <div class="sidebar-header">
                <h2>Messages</h2>
                <button type="button" class="btn-icon" id="new-conversation-btn" title="Nouvelle conversation">
                    <i class="fas fa-edit"></i>
                </button>
            </div>
            
            <div class="search-conversations">
                <div class="search-input-group">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Rechercher des conversations..." id="conversations-search">
                </div>
            </div>
            
            <div class="conversations-list" id="conversations-list">
                <!-- Conversations chargées dynamiquement -->
                <div class="loading-conversations">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Chargement des conversations...</p>
                </div>
            </div>
        </aside>

        <!-- Zone de conversation active -->
        <main class="conversation-main">
            <div class="conversation-empty" id="conversation-empty">
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <h3>Sélectionnez une conversation</h3>
                    <p>Choisissez une conversation existante ou commencez une nouvelle discussion</p>
                    <button type="button" class="btn btn-primary" id="start-conversation-btn">
                        Nouvelle conversation
                    </button>
                </div>
            </div>

            <div class="conversation-active" id="conversation-active" style="display: none;">
                <div class="conversation-header">
                    <div class="conversation-info">
                        <img src="" alt="Avatar" class="conversation-avatar" id="active-conversation-avatar">
                        <div class="conversation-details">
                            <h3 class="conversation-name" id="active-conversation-name"></h3>
                            <p class="conversation-status" id="active-conversation-status"></p>
                        </div>
                    </div>
                    
                    <div class="conversation-actions">
                        <button type="button" class="btn-icon" title="Appel audio" id="audio-call-btn">
                            <i class="fas fa-phone"></i>
                        </button>
                        <button type="button" class="btn-icon" title="Appel vidéo" id="video-call-btn">
                            <i class="fas fa-video"></i>
                        </button>
                        <button type="button" class="btn-icon" title="Informations" id="conversation-info-btn">
                            <i class="fas fa-info-circle"></i>
                        </button>
                        <button type="button" class="btn-icon" title="Plus d'options" id="conversation-menu-btn">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                    </div>
                </div>

                <div class="messages-container-main" id="messages-container-main">
                    <!-- Messages chargés dynamiquement -->
                </div>

                <div class="message-compose" data-drop-zone="files">
                    <div class="message-attachments" id="message-attachments" style="display: none;">
                        <!-- Pièces jointes affichées ici -->
                    </div>
                    
                    <div class="message-input-group">
                        <button type="button" class="message-attachment-btn" id="attach-files-btn" title="Joindre un fichier">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        
                        <div class="message-input-container">
                            <textarea 
                                class="message-input" 
                                id="message-input" 
                                placeholder="Tapez votre message..."
                                rows="1"></textarea>
                            <button type="button" class="emoji-btn" id="emoji-btn" title="Emoji">
                                <i class="fas fa-smile"></i>
                            </button>
                        </div>
                        
                        <button type="button" class="message-send-btn" id="send-message-btn" disabled>
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                    
                    <div class="message-actions">
                        <button type="button" class="message-action" id="voice-record-btn" title="Message vocal">
                            <i class="fas fa-microphone"></i>
                        </button>
                        <button type="button" class="message-action" id="quick-reaction-btn" title="Réaction rapide">
                            <i class="fas fa-heart"></i>
                        </button>
                    </div>
                </div>
            </div>
        </main>

        <!-- Panel d'informations (caché par défaut) -->
        <aside class="conversation-info-panel" id="conversation-info-panel" style="display: none;">
            <div class="info-panel-header">
                <h3>Informations</h3>
                <button type="button" class="btn-icon" id="close-info-panel">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="info-panel-content">
                <div class="participant-info">
                    <img src="" alt="Avatar" class="participant-avatar">
                    <h4 class="participant-name"></h4>
                    <p class="participant-status"></p>
                </div>
                
                <div class="info-section">
                    <h5>Médias partagés</h5>
                    <div class="shared-media" id="shared-media">
                        <!-- Médias partagés -->
                    </div>
                </div>
                
                <div class="info-section">
                    <h5>Actions</h5>
                    <div class="info-actions">
                        <button type="button" class="info-action" id="mute-conversation">
                            <i class="fas fa-bell-slash"></i>
                            <span>Couper les notifications</span>
                        </button>
                        <button type="button" class="info-action" id="archive-conversation" data-drop-zone="archive">
                            <i class="fas fa-archive"></i>
                            <span>Archiver la conversation</span>
                        </button>
                        <button type="button" class="info-action danger" id="delete-conversation">
                            <i class="fas fa-trash"></i>
                            <span>Supprimer la conversation</span>
                        </button>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>

<!-- Modal nouvelle conversation -->
<div class="modal" id="new-conversation-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Nouvelle conversation</h3>
            <button type="button" class="modal-close" onclick="closeModal('new-conversation-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="search-users">
                <input type="text" placeholder="Rechercher des amis..." id="search-friends" class="form-input">
            </div>
            <div class="friends-list" id="friends-to-message">
                <!-- Liste des amis à qui envoyer un message -->
            </div>
        </div>
    </div>
</div>

<!-- Modal emoji picker -->
<div class="emoji-picker" id="emoji-picker" style="display: none;">
    <div class="emoji-categories">
        <button type="button" class="emoji-category active" data-category="smileys">😀</button>
        <button type="button" class="emoji-category" data-category="people">👋</button>
        <button type="button" class="emoji-category" data-category="nature">🌟</button>
        <button type="button" class="emoji-category" data-category="food">🍕</button>
        <button type="button" class="emoji-category" data-category="activities">⚽</button>
        <button type="button" class="emoji-category" data-category="travel">✈️</button>
        <button type="button" class="emoji-category" data-category="objects">📱</button>
        <button type="button" class="emoji-category" data-category="symbols">❤️</button>
    </div>
    <div class="emoji-grid" id="emoji-grid">
        <!-- Emojis chargés dynamiquement -->
    </div>
</div>

<style>
/* Styles pour la messagerie */
.messages-page .main-content {
    padding: 0;
    height: calc(100vh - 60px);
    overflow: hidden;
}

.messages-container {
    height: 100%;
    background: var(--bg-secondary);
}

.messages-layout {
    display: grid;
    grid-template-columns: 350px 1fr;
    height: 100%;
}

.conversations-sidebar {
    background: var(--bg-primary);
    border-right: 1px solid var(--border-color);
    display: flex;
    flex-direction: column;
    height: 100%;
}

.sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--spacing-lg);
    border-bottom: 1px solid var(--border-color);
}

.sidebar-header h2 {
    margin: 0;
    font-size: var(--text-xl);
}

.search-conversations {
    padding: var(--spacing-md);
    border-bottom: 1px solid var(--border-color);
}

.search-input-group {
    position: relative;
}

.search-input-group i {
    position: absolute;
    left: var(--spacing-md);
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
}

.search-input-group input {
    width: 100%;
    padding: var(--spacing-sm) var(--spacing-md) var(--spacing-sm) 2.5rem;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-size: var(--text-sm);
}

.conversations-list {
    flex: 1;
    overflow-y: auto;
    padding: var(--spacing-sm);
}

.conversation-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    padding: var(--spacing-md);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: background var(--transition-fast);
    margin-bottom: var(--spacing-xs);
}

.conversation-item:hover,
.conversation-item.active {
    background: var(--bg-secondary);
}

.conversation-item.active {
    background: var(--primary-color);
    color: white;
}

.conversation-avatar {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-full);
    object-fit: cover;
    position: relative;
}

.conversation-info {
    flex: 1;
    min-width: 0;
}

.conversation-name {
    font-size: var(--text-base);
    font-weight: 600;
    margin: 0 0 var(--spacing-xs) 0;
    color: inherit;
}

.conversation-preview {
    font-size: var(--text-sm);
    color: var(--text-secondary);
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.conversation-item.active .conversation-preview {
    color: rgba(255, 255, 255, 0.8);
}

.conversation-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: var(--spacing-xs);
}

.conversation-time {
    font-size: var(--text-xs);
    color: var(--text-muted);
}

.conversation-badge {
    background: var(--primary-color);
    color: white;
    font-size: var(--text-xs);
    padding: 2px 6px;
    border-radius: var(--radius-full);
    min-width: 18px;
    text-align: center;
}

.online-indicator {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 12px;
    height: 12px;
    background: var(--success-color);
    border: 2px solid var(--bg-primary);
    border-radius: var(--radius-full);
}

.conversation-main {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: var(--bg-primary);
}

.conversation-empty {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

.empty-state {
    text-align: center;
    max-width: 300px;
}

.empty-state i {
    font-size: 4rem;
    color: var(--text-muted);
    margin-bottom: var(--spacing-lg);
}

.empty-state h3 {
    margin-bottom: var(--spacing-md);
    color: var(--text-primary);
}

.empty-state p {
    color: var(--text-secondary);
    margin-bottom: var(--spacing-lg);
}

.conversation-active {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.conversation-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--spacing-lg);
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-primary);
}

.conversation-header .conversation-info {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
}

.conversation-header .conversation-avatar {
    width: 40px;
    height: 40px;
}

.conversation-details h3 {
    margin: 0 0 var(--spacing-xs) 0;
    font-size: var(--text-lg);
}

.conversation-status {
    margin: 0;
    font-size: var(--text-sm);
    color: var(--text-secondary);
}

.conversation-actions {
    display: flex;
    gap: var(--spacing-sm);
}

.btn-icon {
    width: 40px;
    height: 40px;
    border: none;
    background: none;
    color: var(--text-secondary);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all var(--transition-fast);
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-icon:hover {
    background: var(--bg-secondary);
    color: var(--primary-color);
}

.messages-container-main {
    flex: 1;
    overflow-y: auto;
    padding: var(--spacing-lg);
    background: var(--bg-secondary);
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.message {
    display: flex;
    gap: var(--spacing-sm);
    max-width: 70%;
}

.message.sent {
    align-self: flex-end;
    flex-direction: row-reverse;
}

.message.received {
    align-self: flex-start;
}

.message-avatar {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-full);
    object-fit: cover;
    flex-shrink: 0;
}

.message.sent .message-avatar {
    display: none;
}

.message-content {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

.message-bubble {
    padding: var(--spacing-sm) var(--spacing-md);
    border-radius: var(--radius-lg);
    word-wrap: break-word;
    position: relative;
}

.message.received .message-bubble {
    background: var(--bg-primary);
    border-bottom-left-radius: var(--radius-sm);
}

.message.sent .message-bubble {
    background: var(--primary-color);
    color: white;
    border-bottom-right-radius: var(--radius-sm);
}

.message-time {
    font-size: var(--text-xs);
    color: var(--text-muted);
    align-self: flex-end;
}

.message.sent .message-time {
    align-self: flex-start;
}

.message-status {
    font-size: var(--text-xs);
    color: var(--text-muted);
}

.message-compose {
    border-top: 1px solid var(--border-color);
    background: var(--bg-primary);
    padding: var(--spacing-md);
}

.message-attachments {
    padding: var(--spacing-md);
    border-bottom: 1px solid var(--border-color);
    margin-bottom: var(--spacing-md);
}

.message-input-group {
    display: flex;
    align-items: flex-end;
    gap: var(--spacing-sm);
}

.message-attachment-btn {
    width: 40px;
    height: 40px;
    border: none;
    background: var(--bg-secondary);
    color: var(--text-secondary);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all var(--transition-fast);
    display: flex;
    align-items: center;
    justify-content: center;
}

.message-attachment-btn:hover {
    background: var(--primary-color);
    color: white;
}

.message-input-container {
    flex: 1;
    position: relative;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: var(--spacing-sm) var(--spacing-md);
    display: flex;
    align-items: flex-end;
    gap: var(--spacing-sm);
}

.message-input {
    flex: 1;
    border: none;
    background: transparent;
    color: var(--text-primary);
    resize: none;
    max-height: 120px;
    min-height: 20px;
    font-family: inherit;
    font-size: var(--text-base);
    line-height: 1.4;
}

.message-input:focus {
    outline: none;
}

.emoji-btn {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: var(--text-lg);
    padding: 4px;
    border-radius: var(--radius-sm);
    transition: all var(--transition-fast);
}

.emoji-btn:hover {
    color: var(--primary-color);
    background: var(--bg-tertiary);
}

.message-send-btn {
    width: 40px;
    height: 40px;
    border: none;
    background: var(--primary-color);
    color: white;
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all var(--transition-fast);
    display: flex;
    align-items: center;
    justify-content: center;
}

.message-send-btn:disabled {
    background: var(--bg-secondary);
    color: var(--text-muted);
    cursor: not-allowed;
}

.message-send-btn:not(:disabled):hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.message-actions {
    display: flex;
    gap: var(--spacing-sm);
    margin-top: var(--spacing-sm);
    justify-content: center;
}

.message-action {
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: var(--spacing-sm);
    border-radius: var(--radius-md);
    transition: all var(--transition-fast);
}

.message-action:hover {
    color: var(--primary-color);
    background: var(--bg-secondary);
}

.emoji-picker {
    position: absolute;
    bottom: 100%;
    right: 0;
    width: 300px;
    height: 300px;
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 1000;
}

.emoji-categories {
    display: flex;
    border-bottom: 1px solid var(--border-color);
    padding: var(--spacing-sm);
}

.emoji-category {
    background: none;
    border: none;
    padding: var(--spacing-sm);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: background var(--transition-fast);
}

.emoji-category.active,
.emoji-category:hover {
    background: var(--bg-secondary);
}

.emoji-grid {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: var(--spacing-xs);
    padding: var(--spacing-md);
    height: 240px;
    overflow-y: auto;
}

.emoji-item {
    background: none;
    border: none;
    font-size: var(--text-lg);
    padding: var(--spacing-xs);
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: background var(--transition-fast);
}

.emoji-item:hover {
    background: var(--bg-secondary);
}

/* Responsive pour messages */
@media (max-width: 768px) {
    .messages-layout {
        grid-template-columns: 1fr;
    }
    
    .conversations-sidebar {
        display: none;
    }
    
    .conversations-sidebar.mobile-active {
        display: flex;
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 1000;
    }
    
    .message {
        max-width: 85%;
    }
    
    .message-input-group {
        flex-wrap: wrap;
    }
    
    .message-actions {
        margin-top: var(--spacing-xs);
    }
}

.loading-conversations,
.loading-messages {
    text-align: center;
    padding: var(--spacing-2xl);
    color: var(--text-muted);
}

.loading-conversations i,
.loading-messages i {
    font-size: var(--text-xl);
    margin-bottom: var(--spacing-md);
}

/* Animations */
@keyframes messageSlideIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.message {
    animation: messageSlideIn 0.3s ease;
}

/* Typing indicator */
.typing-indicator {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    padding: var(--spacing-md);
    font-style: italic;
    color: var(--text-muted);
}

.typing-dots {
    display: flex;
    gap: 2px;
}

.typing-dot {
    width: 4px;
    height: 4px;
    background: var(--text-muted);
    border-radius: var(--radius-full);
    animation: typingDot 1.4s infinite ease-in-out;
}

.typing-dot:nth-child(2) {
    animation-delay: 0.2s;
}

.typing-dot:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes typingDot {
    0%, 60%, 100% {
        opacity: 0.3;
        transform: scale(0.8);
    }
    30% {
        opacity: 1;
        transform: scale(1);
    }
}
</style>

<script src="/assets/js/dragdrop.js"></script>
<script>
// Gestionnaire de messagerie
class MessagingSystem {
    constructor() {
        this.currentConversationId = null;
        this.websocket = null;
        this.conversations = new Map();
        this.lastMessageId = 0;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadConversations();
        this.connectWebSocket();
        this.setupEmojiPicker();
    }

    setupEventListeners() {
        // Boutons principaux
        document.getElementById('new-conversation-btn').addEventListener('click', () => {
            this.openNewConversationModal();
        });

        document.getElementById('start-conversation-btn').addEventListener('click', () => {
            this.openNewConversationModal();
        });

        // Input message
        const messageInput = document.getElementById('message-input');
        messageInput.addEventListener('input', () => {
            this.handleMessageInput();
        });

        messageInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Bouton envoyer
        document.getElementById('send-message-btn').addEventListener('click', () => {
            this.sendMessage();
        });

        // Emoji picker
        document.getElementById('emoji-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleEmojiPicker();
        });

        // Recherche conversations
        document.getElementById('conversations-search').addEventListener('input', (e) => {
            this.filterConversations(e.target.value);
        });

        // Fichiers joints
        document.getElementById('attach-files-btn').addEventListener('click', () => {
            this.openFileSelector();
        });
    }

    async loadConversations() {
        try {
            const response = await apiRequest('/api/conversations.php?action=list');
            
            if (response.success) {
                this.renderConversations(response.conversations);
            }
        } catch (error) {
            console.error('Erreur chargement conversations:', error);
        }
    }

    renderConversations(conversations) {
        const container = document.getElementById('conversations-list');
        
        if (conversations.length === 0) {
            container.innerHTML = `
                <div class="empty-conversations">
                    <p>Aucune conversation</p>
                    <button type="button" class="btn btn-primary btn-sm" onclick="messagingSystem.openNewConversationModal()">
                        Commencer une conversation
                    </button>
                </div>
            `;
            return;
        }

        container.innerHTML = conversations.map(conv => this.createConversationHTML(conv)).join('');
        
        // Ajouter les event listeners
        container.querySelectorAll('.conversation-item').forEach(item => {
            item.addEventListener('click', () => {
                this.selectConversation(item.dataset.conversationId);
            });
        });
    }

    createConversationHTML(conversation) {
        const isOnline = conversation.participant_status === 'online';
        const unreadBadge = conversation.unread_count > 0 ? 
            `<span class="conversation-badge">${conversation.unread_count}</span>` : '';
        
        return `
            <div class="conversation-item" 
                 data-conversation-id="${conversation.id}"
                 data-drag-type="conversation"
                 draggable="true">
                <div class="conversation-avatar-container">
                    <img src="/uploads/avatars/${conversation.participant_avatar || 'default-avatar.svg'}" 
                         alt="${conversation.participant_name}" 
                         class="conversation-avatar">
                    ${isOnline ? '<div class="online-indicator"></div>' : ''}
                </div>
                <div class="conversation-info">
                    <h4 class="conversation-name">${escapeHtml(conversation.participant_name)}</h4>
                    <p class="conversation-preview">${this.formatLastMessage(conversation.last_message)}</p>
                </div>
                <div class="conversation-meta">
                    <span class="conversation-time">${formatTimeAgo(conversation.last_message_time)}</span>
                    ${unreadBadge}
                </div>
            </div>
        `;
    }

    formatLastMessage(message) {
        if (!message) return 'Aucun message';
        
        if (message.type === 'text') {
            return escapeHtml(message.content.substring(0, 50) + (message.content.length > 50 ? '...' : ''));
        } else if (message.type === 'image') {
            return '📷 Image';
        } else if (message.type === 'video') {
            return '🎥 Vidéo';
        } else if (message.type === 'file') {
            return '📎 Fichier';
        }
        
        return message.content || 'Message';
    }

    async selectConversation(conversationId) {
        this.currentConversationId = conversationId;
        
        // Marquer comme active
        document.querySelectorAll('.conversation-item').forEach(item => {
            item.classList.remove('active');
        });
        document.querySelector(`[data-conversation-id="${conversationId}"]`).classList.add('active');
        
        // Afficher la zone de conversation
        document.getElementById('conversation-empty').style.display = 'none';
        document.getElementById('conversation-active').style.display = 'flex';
        
        // Charger les détails de la conversation
        await this.loadConversationDetails(conversationId);
        
        // Charger les messages
        await this.loadMessages(conversationId);
        
        // Marquer comme lu
        this.markConversationAsRead(conversationId);
    }

    async loadConversationDetails(conversationId) {
        try {
            const response = await apiRequest(`/api/conversations.php?action=details&id=${conversationId}`);
            
            if (response.success) {
                const conv = response.conversation;
                document.getElementById('active-conversation-avatar').src = 
                    `/uploads/avatars/${conv.participant_avatar || 'default-avatar.svg'}`;
                document.getElementById('active-conversation-name').textContent = conv.participant_name;
                document.getElementById('active-conversation-status').textContent = 
                    conv.participant_status === 'online' ? 'En ligne' : `Vu ${formatTimeAgo(conv.participant_last_seen)}`;
            }
        } catch (error) {
            console.error('Erreur détails conversation:', error);
        }
    }

    async loadMessages(conversationId, page = 1) {
        try {
            const response = await apiRequest(`/api/messages.php?action=list&conversation_id=${conversationId}&page=${page}`);
            
            if (response.success) {
                this.renderMessages(response.messages);
                this.scrollToBottom();
            }
        } catch (error) {
            console.error('Erreur chargement messages:', error);
        }
    }

    renderMessages(messages) {
        const container = document.getElementById('messages-container-main');
        container.innerHTML = messages.map(msg => this.createMessageHTML(msg)).join('');
    }

    createMessageHTML(message) {
        const isSent = message.sender_id == currentUser.id;
        const messageClass = isSent ? 'sent' : 'received';
        
        return `
            <div class="message ${messageClass}" data-message-id="${message.id}">
                ${!isSent ? `<img src="/uploads/avatars/${message.sender_avatar || 'default-avatar.svg'}" 
                           alt="${message.sender_name}" class="message-avatar">` : ''}
                <div class="message-content">
                    <div class="message-bubble">
                        ${this.formatMessageContent(message)}
                    </div>
                    <div class="message-time">
                        ${formatTime(message.created_at)}
                        ${isSent ? `<span class="message-status">${this.getMessageStatus(message)}</span>` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    formatMessageContent(message) {
        switch (message.type) {
            case 'text':
                return this.processTextMessage(message.content);
            case 'image':
                return `<img src="${message.file_url}" alt="Image" class="message-image" onclick="openImageModal('${message.file_url}')">`;
            case 'video':
                return `<video src="${message.file_url}" controls class="message-video"></video>`;
            case 'file':
                return `<div class="message-file">
                    <i class="fas fa-file"></i>
                    <a href="${message.file_url}" download="${message.original_filename}">${message.original_filename}</a>
                </div>`;
            default:
                return escapeHtml(message.content);
        }
    }

    processTextMessage(content) {
        return escapeHtml(content)
            .replace(/\n/g, '<br>')
            .replace(/:([a-z_]+):/g, '<span class="emoji">$&</span>') // Emoji codes
            .replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>'); // Links
    }

    getMessageStatus(message) {
        if (message.read_at) return '✓✓';
        if (message.delivered_at) return '✓';
        return '○';
    }

    handleMessageInput() {
        const input = document.getElementById('message-input');
        const sendBtn = document.getElementById('send-message-btn');
        
        // Auto-resize
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 120) + 'px';
        
        // Enable/disable send button
        sendBtn.disabled = input.value.trim().length === 0;
        
        // Send typing indicator
        if (this.currentConversationId && input.value.trim().length > 0) {
            this.sendTypingIndicator();
        }
    }

    async sendMessage() {
        const input = document.getElementById('message-input');
        const content = input.value.trim();
        
        if (!content || !this.currentConversationId) return;
        
        try {
            const response = await apiRequest('/api/messages.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'send',
                    conversation_id: this.currentConversationId,
                    content: content,
                    type: 'text'
                })
            });
            
            if (response.success) {
                input.value = '';
                input.style.height = 'auto';
                document.getElementById('send-message-btn').disabled = true;
                
                // Ajouter le message à l'interface
                this.addMessageToInterface(response.message);
                this.scrollToBottom();
            }
        } catch (error) {
            console.error('Erreur envoi message:', error);
            showToast('Erreur lors de l\'envoi du message', 'danger');
        }
    }

    addMessageToInterface(message) {
        const container = document.getElementById('messages-container-main');
        container.insertAdjacentHTML('beforeend', this.createMessageHTML(message));
    }

    scrollToBottom() {
        const container = document.getElementById('messages-container-main');
        container.scrollTop = container.scrollHeight;
    }

    connectWebSocket() {
        const protocol = window.location.protocol === "https:" ? "wss:" : "ws:";
        const wsUrl = `${protocol}//${window.location.host}/ws`;
        
        this.websocket = new WebSocket(wsUrl);
        
        this.websocket.onopen = () => {
            console.log('WebSocket connecté');
        };
        
        this.websocket.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.handleWebSocketMessage(data);
        };
        
        this.websocket.onclose = () => {
            console.log('WebSocket fermé, reconnexion...');
            setTimeout(() => this.connectWebSocket(), 3000);
        };
    }

    handleWebSocketMessage(data) {
        switch (data.type) {
            case 'new_message':
                if (data.conversation_id === this.currentConversationId) {
                    this.addMessageToInterface(data.message);
                    this.scrollToBottom();
                }
                this.updateConversationPreview(data.conversation_id, data.message);
                break;
            case 'typing':
                this.showTypingIndicator(data.user_id, data.conversation_id);
                break;
            case 'stop_typing':
                this.hideTypingIndicator(data.user_id);
                break;
            case 'user_online':
                this.updateUserStatus(data.user_id, 'online');
                break;
            case 'user_offline':
                this.updateUserStatus(data.user_id, 'offline');
                break;
        }
    }

    async openNewConversationModal() {
        document.getElementById('new-conversation-modal').style.display = 'block';
        await this.loadFriendsForMessaging();
    }

    async loadFriendsForMessaging() {
        try {
            const response = await apiRequest('/api/friends.php?action=list');
            
            if (response.success) {
                const container = document.getElementById('friends-to-message');
                container.innerHTML = response.friends.map(friend => `
                    <div class="friend-item" data-user-id="${friend.id}">
                        <img src="/uploads/avatars/${friend.avatar || 'default-avatar.svg'}" 
                             alt="${friend.username}" class="friend-avatar">
                        <div class="friend-info">
                            <h4>${escapeHtml(friend.first_name)} ${escapeHtml(friend.last_name)}</h4>
                            <p>@${escapeHtml(friend.username)}</p>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm" onclick="messagingSystem.startConversation(${friend.id})">
                            Message
                        </button>
                    </div>
                `).join('');
            }
        } catch (error) {
            console.error('Erreur chargement amis:', error);
        }
    }

    async startConversation(userId) {
        try {
            const response = await apiRequest('/api/conversations.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'create',
                    participant_id: userId
                })
            });
            
            if (response.success) {
                closeModal('new-conversation-modal');
                await this.loadConversations();
                this.selectConversation(response.conversation_id);
            }
        } catch (error) {
            console.error('Erreur création conversation:', error);
            showToast('Erreur lors de la création de la conversation', 'danger');
        }
    }

    setupEmojiPicker() {
        const emojiData = {
            smileys: ['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '🙃', '😉', '😌', '😍', '🥰'],
            people: ['👋', '🤚', '🖐️', '✋', '🖖', '👌', '🤏', '✌️', '🤞', '🤟', '🤘', '🤙', '👈', '👉', '👆', '🖕'],
            nature: ['🌟', '⭐', '🌙', '☀️', '⛅', '🌤️', '⛈️', '🌦️', '🌧️', '⚡', '❄️', '☃️', '⛄', '🌊', '💧', '🔥'],
            food: ['🍕', '🍔', '🍟', '🌭', '🥪', '🌮', '🌯', '🥙', '🧆', '🥚', '🍳', '🥘', '🍲', '🥗', '🍿', '🧈'],
            activities: ['⚽', '🏀', '🏈', '⚾', '🥎', '🏐', '🏉', '🎾', '🥏', '🎱', '🪀', '🏓', '🏸', '🏒', '🏑', '🥍'],
            travel: ['✈️', '🛫', '🛬', '🛩️', '💺', '🚁', '🚟', '🚠', '🚡', '🛰️', '🚀', '🛸', '🚗', '🚕', '🚙', '🚌'],
            objects: ['📱', '💻', '🖥️', '⌨️', '🖱️', '🖨️', '📞', '☎️', '📠', '📺', '📻', '🎙️', '🎚️', '🎛️', '🧭', '⏱️'],
            symbols: ['❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '❣️', '💕', '💞', '💓', '💗', '💖']
        };

        // Initialiser avec les smileys
        this.renderEmojiCategory('smileys', emojiData.smileys);

        // Event listeners pour les catégories
        document.querySelectorAll('.emoji-category').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.emoji-category').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                
                const category = e.target.dataset.category;
                this.renderEmojiCategory(category, emojiData[category]);
            });
        });

        // Clic en dehors pour fermer
        document.addEventListener('click', (e) => {
            const picker = document.getElementById('emoji-picker');
            const btn = document.getElementById('emoji-btn');
            
            if (!picker.contains(e.target) && !btn.contains(e.target)) {
                picker.style.display = 'none';
            }
        });
    }

    renderEmojiCategory(category, emojis) {
        const grid = document.getElementById('emoji-grid');
        grid.innerHTML = emojis.map(emoji => `
            <button type="button" class="emoji-item" onclick="messagingSystem.insertEmoji('${emoji}')">
                ${emoji}
            </button>
        `).join('');
    }

    toggleEmojiPicker() {
        const picker = document.getElementById('emoji-picker');
        picker.style.display = picker.style.display === 'none' ? 'block' : 'none';
    }

    insertEmoji(emoji) {
        const input = document.getElementById('message-input');
        const start = input.selectionStart;
        const end = input.selectionEnd;
        const value = input.value;
        
        input.value = value.substring(0, start) + emoji + value.substring(end);
        input.selectionStart = input.selectionEnd = start + emoji.length;
        input.focus();
        
        this.handleMessageInput();
    }

    openFileSelector() {
        const input = document.createElement('input');
        input.type = 'file';
        input.multiple = true;
        input.accept = 'image/*,video/*,audio/*,.pdf,.doc,.docx,.txt';
        
        input.onchange = (e) => {
            const files = Array.from(e.target.files);
            this.handleFileSelection(files);
        };
        
        input.click();
    }

    async handleFileSelection(files) {
        const validFiles = files.filter(file => file.size <= 10 * 1024 * 1024); // 10MB max
        
        if (validFiles.length === 0) {
            showToast('Aucun fichier valide sélectionné', 'warning');
            return;
        }

        const attachmentsContainer = document.getElementById('message-attachments');
        attachmentsContainer.style.display = 'block';

        for (const file of validFiles) {
            try {
                const uploadResult = await this.uploadFile(file);
                if (uploadResult.success) {
                    this.addFileAttachment(uploadResult, file);
                }
            } catch (error) {
                console.error('Erreur upload:', error);
                showToast(`Erreur upload ${file.name}`, 'danger');
            }
        }
    }

    async uploadFile(file) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('type', this.getFileType(file.type));

        const response = await fetch('/api/upload.php', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': getCSRFToken()
            },
            body: formData
        });

        return await response.json();
    }

    getFileType(mimeType) {
        if (mimeType.startsWith('image/')) return 'image';
        if (mimeType.startsWith('video/')) return 'video';
        if (mimeType.startsWith('audio/')) return 'audio';
        return 'file';
    }

    sendTypingIndicator() {
        if (this.websocket && this.websocket.readyState === WebSocket.OPEN) {
            this.websocket.send(JSON.stringify({
                type: 'typing',
                conversation_id: this.currentConversationId
            }));
        }
    }

    async markConversationAsRead(conversationId) {
        try {
            await apiRequest('/api/conversations.php', {
                method: 'PUT',
                body: JSON.stringify({
                    action: 'mark_read',
                    conversation_id: conversationId
                })
            });
        } catch (error) {
            console.error('Erreur marquer comme lu:', error);
        }
    }
}

// Variables globales
let messagingSystem;
const currentUser = <?php echo json_encode($currentUser); ?>;

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    messagingSystem = new MessagingSystem();
});
</script>

<?php include 'includes/footer.php'; ?>