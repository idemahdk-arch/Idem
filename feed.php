<?php
/**
 * Page du fil d'actualité IDEM
 */

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

// Vérifier que l'utilisateur est connecté
SessionManager::requireLogin();

$pageTitle = "Fil d'actualité";
$pageDescription = "Découvrez les dernières publications de vos amis";
$bodyClass = "feed-page";

// Initialiser la base de données
$db = initDatabase();
$currentUser = SessionManager::getCurrentUser();

include 'includes/header.php';
?>

<div class="feed-container">
    <div class="feed-layout">
        <!-- Sidebar gauche -->
        <aside class="feed-sidebar left-sidebar">
            <div class="sidebar-section">
                <div class="user-card">
                    <img src="uploads/avatars/<?php echo $currentUser['avatar'] ?? 'default-avatar.svg'; ?>" 
                         alt="Avatar" class="user-card-avatar">
                    <div class="user-card-info">
                        <h3><?php echo sanitize($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></h3>
                        <p class="username">@<?php echo sanitize($currentUser['username']); ?></p>
                        <?php if (!empty($currentUser['bio'])): ?>
                        <p class="bio"><?php echo sanitize($currentUser['bio']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="sidebar-section">
                <h4>Raccourcis</h4>
                <nav class="sidebar-nav">
                    <a href="feed.php" class="nav-item active">
                        <i class="fas fa-home"></i>
                        <span>Fil d'actualité</span>
                    </a>
                    <a href="friends.php" class="nav-item">
                        <i class="fas fa-user-friends"></i>
                        <span>Mes amis</span>
                    </a>
                    <a href="groups.php" class="nav-item">
                        <i class="fas fa-users"></i>
                        <span>Groupes</span>
                    </a>
                    <a href="messages.php" class="nav-item">
                        <i class="fas fa-comments"></i>
                        <span>Messages</span>
                    </a>
                    <a href="profile.php" class="nav-item">
                        <i class="fas fa-user"></i>
                        <span>Mon profil</span>
                    </a>
                </nav>
            </div>
        </aside>

        <!-- Contenu principal -->
        <main class="feed-main">
            <!-- Créer un post -->
            <div class="card create-post-card">
                <div class="card-body">
                    <div class="create-post-header">
                        <img src="uploads/avatars/<?php echo $currentUser['avatar'] ?? 'default-avatar.svg'; ?>" 
                             alt="Avatar" class="post-avatar">
                        <div class="create-post-input">
                            <textarea placeholder="Que voulez-vous partager, <?php echo sanitize($currentUser['first_name']); ?> ?" 
                                    class="post-textarea" id="post-content"></textarea>
                        </div>
                    </div>
                    
                    <div class="create-post-actions">
                        <div class="post-options">
                            <button type="button" class="post-option" title="Ajouter une image">
                                <i class="fas fa-image"></i>
                                <span>Photo</span>
                            </button>
                            <button type="button" class="post-option" title="Ajouter une vidéo">
                                <i class="fas fa-video"></i>
                                <span>Vidéo</span>
                            </button>
                            <button type="button" class="post-option" title="Créer un sondage">
                                <i class="fas fa-poll"></i>
                                <span>Sondage</span>
                            </button>
                        </div>
                        
                        <div class="post-privacy">
                            <select class="privacy-select" id="post-privacy">
                                <option value="friends">Amis</option>
                                <option value="public">Public</option>
                                <option value="private">Privé</option>
                            </select>
                            <button type="button" class="btn btn-primary" id="publish-btn" disabled>
                                Publier
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stories (futures) -->
            <div class="card stories-card" style="display: none;">
                <div class="card-body">
                    <div class="stories-container">
                        <div class="story-item create-story">
                            <div class="story-preview">
                                <i class="fas fa-plus"></i>
                            </div>
                            <span>Votre story</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liste des posts -->
            <div class="posts-container" id="posts-container">
                <!-- Posts chargés dynamiquement -->
                <div class="loading-posts">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Chargement des publications...</p>
                </div>
            </div>
        </main>

        <!-- Sidebar droite -->
        <aside class="feed-sidebar right-sidebar">
            <div class="sidebar-section">
                <h4>Suggestions d'amis</h4>
                <div class="suggestions-list" id="friend-suggestions">
                    <!-- Suggestions chargées dynamiquement -->
                </div>
            </div>
            
            <div class="sidebar-section">
                <h4>Tendances</h4>
                <div class="trending-list">
                    <div class="trending-item">
                        <span class="hashtag">#TechnologieWeb</span>
                        <span class="trend-count">12 publications</span>
                    </div>
                    <div class="trending-item">
                        <span class="hashtag">#Photographie</span>
                        <span class="trend-count">8 publications</span>
                    </div>
                    <div class="trending-item">
                        <span class="hashtag">#Voyages</span>
                        <span class="trend-count">15 publications</span>
                    </div>
                </div>
            </div>
            
            <div class="sidebar-section">
                <h4>Activité récente</h4>
                <div class="activity-list" id="recent-activity">
                    <!-- Activité chargée dynamiquement -->
                </div>
            </div>
        </aside>
    </div>
</div>

<style>
/* Styles spécifiques au feed */
.feed-page .main-content {
    padding: 0;
    background: var(--bg-secondary);
}

.feed-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: var(--spacing-md);
}

.feed-layout {
    display: grid;
    grid-template-columns: 250px 1fr 300px;
    gap: var(--spacing-lg);
    align-items: start;
}

.feed-sidebar {
    position: sticky;
    top: 100px;
}

.sidebar-section {
    background: var(--bg-primary);
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
    margin-bottom: var(--spacing-md);
    border: 1px solid var(--border-color);
}

.sidebar-section h4 {
    margin-bottom: var(--spacing-md);
    font-size: var(--text-lg);
    color: var(--text-primary);
}

.user-card {
    text-align: center;
}

.user-card-avatar {
    width: 80px;
    height: 80px;
    border-radius: var(--radius-full);
    margin-bottom: var(--spacing-md);
    object-fit: cover;
}

.user-card-info h3 {
    margin-bottom: var(--spacing-xs);
    font-size: var(--text-xl);
}

.username {
    color: var(--text-secondary);
    margin-bottom: var(--spacing-sm);
}

.bio {
    font-size: var(--text-sm);
    color: var(--text-muted);
    line-height: 1.4;
}

.sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

.nav-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    padding: var(--spacing-sm) var(--spacing-md);
    border-radius: var(--radius-md);
    color: var(--text-secondary);
    text-decoration: none;
    transition: all var(--transition-fast);
}

.nav-item:hover,
.nav-item.active {
    background: var(--primary-color);
    color: white;
}

.nav-item i {
    width: 20px;
    text-align: center;
}

.feed-main {
    max-width: 600px;
}

.create-post-card {
    margin-bottom: var(--spacing-lg);
}

.create-post-header {
    display: flex;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-md);
}

.post-avatar {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-full);
    object-fit: cover;
    flex-shrink: 0;
}

.create-post-input {
    flex: 1;
}

.post-textarea {
    width: 100%;
    min-height: 100px;
    border: none;
    resize: vertical;
    font-size: var(--text-base);
    font-family: inherit;
    background: transparent;
    color: var(--text-primary);
}

.post-textarea:focus {
    outline: none;
}

.post-textarea::placeholder {
    color: var(--text-muted);
}

.create-post-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: var(--spacing-md);
    border-top: 1px solid var(--border-color);
}

.post-options {
    display: flex;
    gap: var(--spacing-md);
}

.post-option {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    padding: var(--spacing-sm) var(--spacing-md);
    background: transparent;
    border: none;
    border-radius: var(--radius-md);
    color: var(--text-secondary);
    cursor: pointer;
    transition: all var(--transition-fast);
}

.post-option:hover {
    background: var(--bg-secondary);
    color: var(--primary-color);
}

.post-privacy {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
}

.privacy-select {
    padding: var(--spacing-sm) var(--spacing-md);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    background: var(--bg-primary);
    color: var(--text-primary);
}

.stories-card {
    margin-bottom: var(--spacing-lg);
}

.stories-container {
    display: flex;
    gap: var(--spacing-md);
    overflow-x: auto;
    padding-bottom: var(--spacing-sm);
}

.story-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--spacing-xs);
    min-width: 80px;
    cursor: pointer;
}

.story-preview {
    width: 60px;
    height: 60px;
    border-radius: var(--radius-full);
    background: var(--border-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--text-lg);
    color: var(--text-muted);
}

.create-story .story-preview {
    background: var(--primary-color);
    color: white;
}

.posts-container {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-lg);
}

.loading-posts {
    text-align: center;
    padding: var(--spacing-2xl);
    color: var(--text-muted);
}

.loading-posts i {
    font-size: var(--text-2xl);
    margin-bottom: var(--spacing-md);
}

.suggestions-list,
.activity-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.suggestion-item,
.activity-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    padding: var(--spacing-sm);
    border-radius: var(--radius-md);
    transition: background var(--transition-fast);
}

.suggestion-item:hover,
.activity-item:hover {
    background: var(--bg-secondary);
}

.suggestion-avatar,
.activity-avatar {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-full);
    object-fit: cover;
}

.suggestion-info,
.activity-info {
    flex: 1;
}

.suggestion-name,
.activity-title {
    font-weight: 500;
    font-size: var(--text-sm);
}

.suggestion-mutual,
.activity-time {
    font-size: var(--text-xs);
    color: var(--text-muted);
}

.trending-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.trending-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-sm);
    border-radius: var(--radius-md);
    transition: background var(--transition-fast);
    cursor: pointer;
}

.trending-item:hover {
    background: var(--bg-secondary);
}

.hashtag {
    color: var(--primary-color);
    font-weight: 500;
}

.trend-count {
    font-size: var(--text-xs);
    color: var(--text-muted);
}

/* Responsive */
@media (max-width: 1024px) {
    .feed-layout {
        grid-template-columns: 1fr;
    }
    
    .feed-sidebar {
        display: none;
    }
}

@media (max-width: 768px) {
    .feed-container {
        padding: var(--spacing-sm);
    }
    
    .create-post-header {
        gap: var(--spacing-sm);
    }
    
    .post-avatar {
        width: 40px;
        height: 40px;
    }
    
    .create-post-actions {
        flex-direction: column;
        gap: var(--spacing-md);
        align-items: stretch;
    }
    
    .post-options {
        justify-content: space-around;
    }
}
</style>

<script>
// Gestion du feed
document.addEventListener('DOMContentLoaded', function() {
    initializeFeed();
});

function initializeFeed() {
    const postTextarea = document.getElementById('post-content');
    const publishBtn = document.getElementById('publish-btn');
    
    // Activer/désactiver le bouton publier
    postTextarea.addEventListener('input', function() {
        publishBtn.disabled = this.value.trim().length === 0;
    });
    
    // Publier un post
    publishBtn.addEventListener('click', publishPost);
    
    // Charger les posts
    loadPosts();
    
    // Charger les suggestions d'amis
    loadFriendSuggestions();
    
    // Charger l'activité récente
    loadRecentActivity();
}

async function publishPost() {
    const content = document.getElementById('post-content').value.trim();
    const privacy = document.getElementById('post-privacy').value;
    const publishBtn = document.getElementById('publish-btn');
    
    if (!content) return;
    
    publishBtn.disabled = true;
    publishBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publication...';
    
    try {
        const response = await apiRequest('api/posts.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'create',
                content: content,
                privacy: privacy
            })
        });
        
        if (response.success) {
            document.getElementById('post-content').value = '';
            showToast('Publication créée avec succès !', 'success');
            loadPosts(); // Recharger les posts
        } else {
            showToast(response.message || 'Erreur lors de la publication', 'danger');
        }
    } catch (error) {
        console.error('Erreur publication:', error);
        showToast('Erreur lors de la publication', 'danger');
    } finally {
        publishBtn.disabled = false;
        publishBtn.innerHTML = 'Publier';
    }
}

async function loadPosts() {
    const postsContainer = document.getElementById('posts-container');
    
    try {
        const response = await apiRequest('api/posts.php?action=feed');
        
        if (response.success && response.posts) {
            if (response.posts.length === 0) {
                postsContainer.innerHTML = `
                    <div class="empty-feed">
                        <i class="fas fa-newspaper"></i>
                        <h3>Aucune publication pour le moment</h3>
                        <p>Commencez à suivre des amis ou créez votre première publication !</p>
                    </div>
                `;
            } else {
                postsContainer.innerHTML = response.posts.map(post => createPostHTML(post)).join('');
            }
        }
    } catch (error) {
        console.error('Erreur chargement posts:', error);
        postsContainer.innerHTML = `
            <div class="error-feed">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Erreur lors du chargement des publications</p>
            </div>
        `;
    }
}

function createPostHTML(post) {
    return `
        <div class="card post-card" data-post-id="${post.id}">
            <div class="card-body">
                <div class="post-header">
                    <img src="uploads/avatars/${post.avatar || 'default-avatar.svg'}" 
                         alt="${post.username}" class="post-avatar">
                    <div class="post-info">
                        <h4 class="post-author">${escapeHtml(post.first_name)} ${escapeHtml(post.last_name)}</h4>
                        <p class="post-meta">@${escapeHtml(post.username)} · ${formatTimeAgo(post.created_at)}</p>
                    </div>
                    <div class="post-menu">
                        <button class="btn-ghost btn-sm">
                            <i class="fas fa-ellipsis-h"></i>
                        </button>
                    </div>
                </div>
                
                <div class="post-content">
                    ${processPostContent(post.content)}
                </div>
                
                ${post.image ? `<div class="post-image">
                    <img src="uploads/images/${post.image}" alt="Publication">
                </div>` : ''}
                
                <div class="post-actions">
                    <button class="post-action like-btn" data-post-id="${post.id}">
                        <i class="fas fa-heart"></i>
                        <span>${post.likes_count || 0}</span>
                    </button>
                    <button class="post-action comment-btn" data-post-id="${post.id}">
                        <i class="fas fa-comment"></i>
                        <span>${post.comments_count || 0}</span>
                    </button>
                    <button class="post-action share-btn" data-post-id="${post.id}">
                        <i class="fas fa-share"></i>
                        <span>${post.shares_count || 0}</span>
                    </button>
                </div>
            </div>
        </div>
    `;
}

function processPostContent(content) {
    // Traiter les hashtags et mentions (version simplifiée)
    return escapeHtml(content)
        .replace(/#(\w+)/g, '<span class="hashtag">#$1</span>')
        .replace(/@(\w+)/g, '<span class="mention">@$1</span>')
        .replace(/\n/g, '<br>');
}

async function loadFriendSuggestions() {
    const suggestionsContainer = document.getElementById('friend-suggestions');
    
    try {
        const response = await apiRequest('api/friends.php?action=suggestions');
        
        if (response.success && response.suggestions) {
            if (response.suggestions.length === 0) {
                suggestionsContainer.innerHTML = '<p class="text-muted">Aucune suggestion pour le moment</p>';
            } else {
                suggestionsContainer.innerHTML = response.suggestions.map(user => `
                    <div class="suggestion-item">
                        <img src="uploads/avatars/${user.avatar || 'default-avatar.svg'}" 
                             alt="${user.username}" class="suggestion-avatar">
                        <div class="suggestion-info">
                            <div class="suggestion-name">${escapeHtml(user.first_name)} ${escapeHtml(user.last_name)}</div>
                            <div class="suggestion-mutual">${user.mutual_friends || 0} amis en commun</div>
                        </div>
                        <button class="btn btn-primary btn-sm" onclick="sendFriendRequest(${user.id})">
                            Ajouter
                        </button>
                    </div>
                `).join('');
            }
        }
    } catch (error) {
        console.error('Erreur suggestions:', error);
        suggestionsContainer.innerHTML = '<p class="text-muted">Erreur de chargement</p>';
    }
}

async function loadRecentActivity() {
    const activityContainer = document.getElementById('recent-activity');
    
    // Simulation d'activité récente
    activityContainer.innerHTML = `
        <div class="activity-item">
            <img src="uploads/avatars/default-avatar.svg" alt="User" class="activity-avatar">
            <div class="activity-info">
                <div class="activity-title">Marie a aimé votre publication</div>
                <div class="activity-time">il y a 2 heures</div>
            </div>
        </div>
        <div class="activity-item">
            <img src="uploads/avatars/default-avatar.svg" alt="User" class="activity-avatar">
            <div class="activity-info">
                <div class="activity-title">Thomas a commenté votre photo</div>
                <div class="activity-time">il y a 4 heures</div>
            </div>
        </div>
    `;
}

async function sendFriendRequest(userId) {
    try {
        const response = await apiRequest('api/friends.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'send_request',
                user_id: userId
            })
        });
        
        if (response.success) {
            showToast('Demande d\'ami envoyée !', 'success');
            loadFriendSuggestions(); // Recharger les suggestions
        } else {
            showToast(response.message || 'Erreur lors de l\'envoi', 'danger');
        }
    } catch (error) {
        console.error('Erreur demande ami:', error);
        showToast('Erreur lors de l\'envoi', 'danger');
    }
}
</script>

<?php include 'includes/footer.php'; ?>