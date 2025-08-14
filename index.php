<?php
/**
 * Page d'accueil IDEM
 * Affiche la page de connexion pour les utilisateurs non connectés
 * Redirige vers le feed pour les utilisateurs connectés
 */

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

// Initialiser la session
SessionManager::start();

// Si l'utilisateur est déjà connecté, rediriger vers le feed
if (SessionManager::isLoggedIn()) {
    redirect('feed.php');
}

$pageTitle = "Bienvenue";
$pageDescription = "Connectez-vous à IDEM pour retrouver vos amis et partager vos moments";
$bodyClass = "landing-page";

include 'includes/header.php';
?>

<div class="landing-container">
    <!-- Section héro -->
    <section class="hero-section">
        <div class="hero-content">
            <div class="hero-left">
                <div class="hero-text">
                    <h1>Bienvenue sur <span class="brand">IDEM</span></h1>
                    <p class="hero-subtitle">
                        Connectez-vous avec vos amis, partagez vos moments et découvrez de nouvelles personnes dans votre communauté.
                    </p>
                    <div class="hero-features">
                        <div class="feature-item">
                            <i class="fas fa-users"></i>
                            <span>Restez connecté avec vos proches</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-share-alt"></i>
                            <span>Partagez vos moments importants</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-comments"></i>
                            <span>Messagerie instantanée sécurisée</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-shield-alt"></i>
                            <span>Confidentialité et sécurité garanties</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="hero-right">
                <!-- Formulaire de connexion -->
                <div class="auth-card">
                    <div class="auth-header">
                        <h2>Connexion</h2>
                        <p>Accédez à votre compte IDEM</p>
                    </div>
                    
                    <form action="api/auth.php" method="POST" class="auth-form" id="login-form">
                        <input type="hidden" name="action" value="login">
                        <input type="hidden" name="csrf_token" value="<?php echo SessionManager::getCsrfToken(); ?>">
                        
                        <div class="form-group">
                            <label for="login-email">Email ou nom d'utilisateur</label>
                            <input type="text" id="login-email" name="email_username" required 
                                   placeholder="votre@email.com ou nomutilisateur">
                        </div>
                        
                        <div class="form-group">
                            <label for="login-password">Mot de passe</label>
                            <div class="password-input">
                                <input type="password" id="login-password" name="password" required 
                                       placeholder="Votre mot de passe">
                                <button type="button" class="password-toggle" onclick="togglePassword('login-password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-options">
                            <label class="checkbox-label">
                                <input type="checkbox" name="remember_me" value="1">
                                <span class="checkmark"></span>
                                Se souvenir de moi
                            </label>
                            <a href="forgot-password.php" class="forgot-link">Mot de passe oublié ?</a>
                        </div>
                        
                        <button type="submit" class="btn-primary" id="login-btn">
                            <span class="btn-text">Se connecter</span>
                            <span class="btn-loader" style="display: none;">
                                <i class="fas fa-spinner fa-spin"></i>
                            </span>
                        </button>
                    </form>
                    
                    <div class="auth-divider">
                        <span>ou</span>
                    </div>
                    
                    <div class="auth-footer">
                        <p>Vous n'avez pas encore de compte ?</p>
                        <a href="register.php" class="btn-secondary">Créer un compte</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Section fonctionnalités -->
    <section class="features-section">
        <div class="container">
            <div class="section-header">
                <h2>Pourquoi choisir IDEM ?</h2>
                <p>Découvrez toutes les fonctionnalités qui rendent IDEM unique</p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3>Connexions authentiques</h3>
                    <p>Créez des liens significatifs avec des personnes qui partagent vos intérêts et vos valeurs.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h3>Partage instantané</h3>
                    <p>Partagez vos photos, vidéos et pensées en temps réel avec vos amis et votre communauté.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Groupes et communautés</h3>
                    <p>Rejoignez ou créez des groupes autour de vos passions et centres d'intérêt.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-comment-dots"></i>
                    </div>
                    <h3>Messagerie avancée</h3>
                    <p>Conversations privées et de groupe avec partage de fichiers et notifications en temps réel.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Sécurité renforcée</h3>
                    <p>Vos données sont protégées avec un chiffrement de bout en bout et des paramètres de confidentialité avancés.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3>Interface responsive</h3>
                    <p>Accédez à IDEM depuis n'importe quel appareil avec une expérience optimisée.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Section statistiques -->
    <section class="stats-section">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number">10K+</div>
                    <div class="stat-label">Utilisateurs actifs</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">50K+</div>
                    <div class="stat-label">Messages échangés</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">100+</div>
                    <div class="stat-label">Groupes créés</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">99.9%</div>
                    <div class="stat-label">Temps de disponibilité</div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Section CTA finale -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2>Prêt à rejoindre la communauté ?</h2>
                <p>Inscrivez-vous dès maintenant et commencez à vous connecter avec des personnes formidables.</p>
                <div class="cta-buttons">
                    <a href="register.php" class="btn-primary large">Créer mon compte gratuitement</a>
                    <a href="#features" class="btn-secondary large">En savoir plus</a>
                </div>
            </div>
        </div>
    </section>
</div>

<style>
/* Styles spécifiques à la page d'accueil */
.landing-page .main-content {
    padding: 0;
}

.landing-container {
    min-height: 100vh;
}

.hero-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 60px 20px;
    min-height: 100vh;
    display: flex;
    align-items: center;
}

.hero-content {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 60px;
    align-items: center;
}

.hero-left h1 {
    font-size: 3.5rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    line-height: 1.2;
}

.brand {
    color: #ffd700;
}

.hero-subtitle {
    font-size: 1.25rem;
    margin-bottom: 2rem;
    opacity: 0.9;
    line-height: 1.6;
}

.hero-features {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.feature-item i {
    width: 24px;
    color: #ffd700;
}

.auth-card {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    color: #333;
}

.auth-header {
    text-align: center;
    margin-bottom: 2rem;
}

.auth-header h2 {
    font-size: 1.75rem;
    margin-bottom: 0.5rem;
    color: #333;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #555;
}

.form-group input {
    width: 100%;
    padding: 12px;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s;
}

.form-group input:focus {
    outline: none;
    border-color: #667eea;
}

.password-input {
    position: relative;
}

.password-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #999;
    cursor: pointer;
}

.form-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
}

.checkbox-label {
    display: flex;
    align-items: center;
    cursor: pointer;
}

.forgot-link {
    color: #667eea;
    text-decoration: none;
}

.btn-primary, .btn-secondary {
    width: 100%;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: transparent;
    color: #667eea;
    border: 2px solid #667eea;
}

.auth-divider {
    text-align: center;
    margin: 1.5rem 0;
    position: relative;
    color: #999;
}

.auth-divider::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: #e1e5e9;
    z-index: 1;
}

.auth-divider span {
    background: white;
    padding: 0 1rem;
    position: relative;
    z-index: 2;
}

.features-section {
    padding: 80px 20px;
    background: #f8f9fa;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
}

.section-header {
    text-align: center;
    margin-bottom: 4rem;
}

.section-header h2 {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    color: #333;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.feature-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: transform 0.3s;
}

.feature-card:hover {
    transform: translateY(-5px);
}

.feature-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    color: white;
    font-size: 1.5rem;
}

.stats-section {
    padding: 60px 20px;
    background: #333;
    color: white;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 2rem;
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 3rem;
    font-weight: 700;
    color: #ffd700;
    margin-bottom: 0.5rem;
}

.cta-section {
    padding: 80px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    text-align: center;
}

.cta-content h2 {
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

.cta-buttons {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 2rem;
}

.large {
    padding: 16px 32px;
    font-size: 1.1rem;
}

/* Responsive */
@media (max-width: 768px) {
    .hero-content {
        grid-template-columns: 1fr;
        gap: 2rem;
        text-align: center;
    }
    
    .hero-left h1 {
        font-size: 2.5rem;
    }
    
    .cta-buttons {
        flex-direction: column;
        align-items: center;
    }
    
    .cta-buttons a {
        max-width: 300px;
    }
}
</style>

<script>
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.parentElement.querySelector('.password-toggle i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// Gestion du formulaire de connexion
document.getElementById('login-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('login-btn');
    const btnText = btn.querySelector('.btn-text');
    const btnLoader = btn.querySelector('.btn-loader');
    
    // Afficher le loader
    btnText.style.display = 'none';
    btnLoader.style.display = 'inline-block';
    btn.disabled = true;
    
    // Envoyer le formulaire
    const formData = new FormData(this);
    
    fetch('api/auth.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = data.redirect || 'feed.php';
        } else {
            alert(data.message || 'Erreur de connexion');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Une erreur est survenue');
    })
    .finally(() => {
        // Masquer le loader
        btnText.style.display = 'inline-block';
        btnLoader.style.display = 'none';
        btn.disabled = false;
    });
});
</script>

<?php include 'includes/footer.php'; ?>