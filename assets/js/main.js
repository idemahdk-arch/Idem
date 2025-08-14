/**
 * Script principal IDEM - Fonctionnalités globales et responsive
 */

// Configuration globale
window.app = {
    userId: window.userId || null,
    csrfToken: window.csrfToken || '',
    isLoggedIn: !!window.userId,
    currentPage: window.location.pathname,
    breakpoints: {
        mobile: 768,
        tablet: 1024,
        desktop: 1200
    }
};


// Utilities globales
window.utils = {
    // Débounce pour optimiser les performances
    debounce(func, wait, immediate) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                timeout = null;
                if (!immediate) func(...args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func(...args);
        };
    },

    // Throttle pour les événements fréquents
    throttle(func, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },

    // Détection de la taille d'écran
    isMobile() {
        return window.innerWidth <= this.breakpoints.mobile;
    },

    isTablet() {
        return window.innerWidth > this.breakpoints.mobile && window.innerWidth <= this.breakpoints.tablet;
    },

    isDesktop() {
        return window.innerWidth > this.breakpoints.tablet;
    },

    // Formatage des dates
    formatTimeAgo(dateString) {
        const now = new Date();
        const past = new Date(dateString);
        const diffInSeconds = Math.floor((now - past) / 1000);

        if (diffInSeconds < 60) return 'À l\'instant';
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h`;
        if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}j`;
        
        return past.toLocaleDateString('fr-FR', { 
            day: 'numeric', 
            month: 'short',
            year: past.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
        });
    },

    // Échapper HTML
    escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    },

    // Copier dans le presse-papiers
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            showToast('Copié dans le presse-papiers', 'success');
        } catch (err) {
            // Fallback pour les navigateurs non compatibles
            const textArea = document.createElement("textarea");
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            showToast('Copié dans le presse-papiers', 'success');
        }
    },

    // Détecter le support du glisser-déposer
    supportsDragDrop() {
        const div = document.createElement('div');
        return ('draggable' in div) || ('ondragstart' in div && 'ondrop' in div);
    },

    // Générer un ID unique
    generateId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2);
    },

    // Valider un email
    isValidEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    },

    // Limiter le texte avec ellipsis
    truncateText(text, maxLength) {
        return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
    }
};

// Gestionnaire de modales responsive
class ModalManager {
    constructor() {
        this.activeModals = new Set();
        this.setupEventListeners();
    }

    setupEventListeners() {
        // Fermer avec Échap
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeTopModal();
            }
        });

        // Fermer en cliquant à l'extérieur
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal') && !e.target.classList.contains('modal-no-close')) {
                this.closeModal(e.target);
            }
        });

        // Gérer le redimensionnement
        window.addEventListener('resize', utils.throttle(() => {
            this.adjustModalsForScreenSize();
        }, 250));
    }

    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        modal.style.display = 'block';
        this.activeModals.add(modal);
        
        // Animation d'ouverture
        setTimeout(() => {
            modal.classList.add('show');
        }, 10);

        // Bloquer le scroll du body
        document.body.style.overflow = 'hidden';

        // Focus sur le premier élément focusable
        const focusable = modal.querySelector('input, textarea, select, button');
        if (focusable) {
            setTimeout(() => focusable.focus(), 100);
        }

        this.adjustModalForScreenSize(modal);
    }

    closeModal(modal) {
        if (typeof modal === 'string') {
            modal = document.getElementById(modal);
        }
        
        if (!modal) return;

        modal.classList.remove('show');
        this.activeModals.delete(modal);

        setTimeout(() => {
            modal.style.display = 'none';
            
            // Restaurer le scroll si c'était la dernière modale
            if (this.activeModals.size === 0) {
                document.body.style.overflow = '';
            }
        }, 300);
    }

    closeTopModal() {
        if (this.activeModals.size > 0) {
            const topModal = Array.from(this.activeModals).pop();
            this.closeModal(topModal);
        }
    }

    adjustModalForScreenSize(modal) {
        const content = modal.querySelector('.modal-content');
        if (!content) return;

        if (utils.isMobile()) {
            content.style.margin = '0';
            content.style.borderRadius = '0';
            content.style.maxHeight = '100vh';
            content.style.width = '100%';
        } else {
            content.style.margin = '2rem auto';
            content.style.borderRadius = 'var(--radius-lg)';
            content.style.maxHeight = '90vh';
            content.style.width = 'auto';
        }
    }

    adjustModalsForScreenSize() {
        this.activeModals.forEach(modal => {
            this.adjustModalForScreenSize(modal);
        });
    }
}

// Gestionnaire de navigation responsive
class NavigationManager {
    constructor() {
        this.mobileMenuOpen = false;
        this.setupEventListeners();
        this.setupSearchDropdown();
    }

    setupEventListeners() {
        // Menu mobile
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileOverlay = document.getElementById('mobile-overlay');
        const navMenu = document.querySelector('.nav-menu');

        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', () => {
                this.toggleMobileMenu();
            });
        }

        if (mobileOverlay) {
            mobileOverlay.addEventListener('click', () => {
                this.closeMobileMenu();
            });
        }

        // Dropdown utilisateur
        const userMenuBtn = document.getElementById('user-menu-btn');
        const userDropdown = document.getElementById('user-dropdown');

        if (userMenuBtn && userDropdown) {
            userMenuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                userDropdown.classList.toggle('show');
            });

            document.addEventListener('click', () => {
                userDropdown.classList.remove('show');
            });
        }

        // Fermer le menu mobile lors du redimensionnement
        window.addEventListener('resize', () => {
            if (window.innerWidth > utils.breakpoints.mobile && this.mobileMenuOpen) {
                this.closeMobileMenu();
            }
        });
    }

    toggleMobileMenu() {
        const navMenu = document.querySelector('.nav-menu');
        const overlay = document.getElementById('mobile-overlay');
        
        this.mobileMenuOpen = !this.mobileMenuOpen;
        
        if (navMenu) {
            navMenu.classList.toggle('show', this.mobileMenuOpen);
        }
        
        if (overlay) {
            overlay.classList.toggle('show', this.mobileMenuOpen);
        }
        
        document.body.style.overflow = this.mobileMenuOpen ? 'hidden' : '';
    }

    closeMobileMenu() {
        this.mobileMenuOpen = false;
        
        const navMenu = document.querySelector('.nav-menu');
        const overlay = document.getElementById('mobile-overlay');
        
        if (navMenu) {
            navMenu.classList.remove('show');
        }
        
        if (overlay) {
            overlay.classList.remove('show');
        }
        
        document.body.style.overflow = '';
    }

    setupSearchDropdown() {
        const searchInput = document.getElementById('global-search');
        const searchDropdown = document.getElementById('search-results');
        
        if (!searchInput || !searchDropdown) return;

        let searchTimeout;
        
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim();
            
            if (query.length < 2) {
                searchDropdown.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                this.performSearch(query);
            }, 300);
        });

        searchInput.addEventListener('focus', () => {
            if (searchInput.value.trim().length >= 2) {
                searchDropdown.style.display = 'block';
            }
        });

        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !searchDropdown.contains(e.target)) {
                searchDropdown.style.display = 'none';
            }
        });
    }

    async performSearch(query) {
        const searchDropdown = document.getElementById('search-results');
        if (!searchDropdown) return;

        try {
            searchDropdown.innerHTML = '<div class="search-loading">Recherche...</div>';
            searchDropdown.style.display = 'block';

            const response = await apiRequest(`/api/search.php?q=${encodeURIComponent(query)}&limit=5`);
            
            if (response.success) {
                this.renderSearchResults(response.results);
            } else {
                searchDropdown.innerHTML = '<div class="search-error">Erreur de recherche</div>';
            }
        } catch (error) {
            console.error('Erreur recherche:', error);
            searchDropdown.innerHTML = '<div class="search-error">Erreur de recherche</div>';
        }
    }

    renderSearchResults(results) {
        const searchDropdown = document.getElementById('search-results');
        if (!searchDropdown) return;

        if (results.length === 0) {
            searchDropdown.innerHTML = '<div class="search-empty">Aucun résultat trouvé</div>';
            return;
        }

        const html = results.map(result => `
            <a href="${result.url}" class="search-result-item">
                <img src="${result.avatar || '/assets/images/default-avatar.svg'}" 
                     alt="${result.title}" class="search-result-avatar">
                <div class="search-result-content">
                    <div class="search-result-title">${utils.escapeHtml(result.title)}</div>
                    <div class="search-result-subtitle">${utils.escapeHtml(result.subtitle || '')}</div>
                </div>
                <div class="search-result-type">
                    <i class="fas ${this.getSearchResultIcon(result.type)}"></i>
                </div>
            </a>
        `).join('');

        searchDropdown.innerHTML = html;
    }

    getSearchResultIcon(type) {
        const icons = {
            'user': 'fa-user',
            'post': 'fa-file-text',
            'group': 'fa-users',
            'page': 'fa-file'
        };
        return icons[type] || 'fa-search';
    }
}

// Gestionnaire de thème
class ThemeManager {
    constructor() {
        this.currentTheme = localStorage.getItem('theme') || 'light';
        this.setupEventListeners();
        this.applyTheme(this.currentTheme);
    }

    setupEventListeners() {
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                this.toggleTheme();
            });
        }

        // Détecter les préférences système
        if (window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            mediaQuery.addEventListener('change', (e) => {
                if (!localStorage.getItem('theme')) {
                    this.applyTheme(e.matches ? 'dark' : 'light');
                }
            });
        }
    }

    toggleTheme() {
        const newTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        this.applyTheme(newTheme);
        localStorage.setItem('theme', newTheme);
    }

    applyTheme(theme) {
        this.currentTheme = theme;
        document.documentElement.setAttribute('data-theme', theme);
        
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            const icon = themeToggle.querySelector('i');
            if (icon) {
                icon.className = theme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
            }
        }
    }
}

// Fonctions globales pour l'API
window.apiRequest = async function(url, options = {}) {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.app.csrfToken
        }
    };

    const config = { ...defaultOptions, ...options };
    
    if (config.headers['X-CSRF-Token'] && !config.headers['X-CSRF-Token']) {
        delete config.headers['X-CSRF-Token'];
    }

    try {
        const response = await fetch(url, config);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return await response.json();
    } catch (error) {
        console.error('Erreur API:', error);
        throw error;
    }
};

// Fonction globale pour les toasts
window.showToast = function(message, type = 'info', duration = 4000) {
    const container = document.getElementById('toast-container') || createToastContainer();
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <i class="fas ${getToastIcon(type)}"></i>
            <span>${utils.escapeHtml(message)}</span>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(toast);
    
    // Animation d'entrée
    setTimeout(() => toast.classList.add('show'), 10);
    
    // Auto-suppression
    setTimeout(() => {
        toast.classList.add('fade-out');
        setTimeout(() => toast.remove(), 300);
    }, duration);
    
    return toast;
};

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container';
    document.body.appendChild(container);
    return container;
}

function getToastIcon(type) {
    const icons = {
        'success': 'fa-check-circle',
        'error': 'fa-exclamation-circle',
        'warning': 'fa-exclamation-triangle',
        'info': 'fa-info-circle'
    };
    return icons[type] || icons.info;
}

// Fonction globale pour fermer les modales
window.closeModal = function(modalId) {
    window.modalManager.closeModal(modalId);
};

// Gestionnaire d'images avec lazy loading
class ImageManager {
    constructor() {
        this.setupLazyLoading();
        this.setupImageModal();
    }

    setupLazyLoading() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        observer.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    }

    setupImageModal() {
        document.addEventListener('click', (e) => {
            if (e.target.matches('img[data-expandable]')) {
                this.openImageModal(e.target.src, e.target.alt);
            }
        });
    }

    openImageModal(src, alt) {
        const modal = document.createElement('div');
        modal.className = 'image-modal';
        modal.innerHTML = `
            <div class="image-modal-content">
                <img src="${src}" alt="${alt}" class="image-modal-img">
                <button class="image-modal-close" onclick="this.closest('.image-modal').remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        setTimeout(() => modal.classList.add('show'), 10);
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }
}

// Initialisation de tous les gestionnaires
document.addEventListener('DOMContentLoaded', () => {
    window.modalManager = new ModalManager();
    window.navigationManager = new NavigationManager();
    window.themeManager = new ThemeManager();
    window.imageManager = new ImageManager();
    
    // Initialiser les toasts flash
    initFlashMessages();
    
    // Mettre à jour les timestamps régulièrement
    startTimeUpdates();
    
    console.log('IDEM Application initialisée');
});

function initFlashMessages() {
    document.querySelectorAll('.alert .close-alert').forEach(button => {
        button.addEventListener('click', () => {
            button.closest('.alert').style.display = 'none';
        });
    });
    
    // Auto-fermeture après 5 secondes
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.display = 'none';
        });
    }, 5000);
}

function startTimeUpdates() {
    // Mettre à jour les timestamps toutes les minutes
    setInterval(() => {
        document.querySelectorAll('[data-time]').forEach(element => {
            const timestamp = element.dataset.time;
            element.textContent = utils.formatTimeAgo(timestamp);
        });
    }, 60000);
}

// Export pour les modules
window.IDEM = {
    utils: window.utils,
    app: window.app,
    apiRequest: window.apiRequest,
    showToast: window.showToast,
    closeModal: window.closeModal
};