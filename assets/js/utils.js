/**
 * utils.js - Fonctions utilitaires pour l'application IDEM
 */

/**
 * Débounce : Limite l'exécution répétée d'une fonction
 * @param {Function} func - La fonction à débouncer
 * @param {number} wait - Temps d'attente en millisecondes
 * @param {boolean} immediate - Exécuter immédiatement ou non
 * @returns {Function} - Fonction débouncée
 */
function debounce(func, wait, immediate) {
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
}

/**
 * Throttle : Limite la fréquence d'exécution d'une fonction
 * @param {Function} func - La fonction à limiter
 * @param {number} limit - Intervalle minimum en millisecondes
 * @returns {Function} - Fonction limitée
 */
function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

/**
 * Vérifie si l'écran est en mode mobile
 * @returns {boolean} - Vrai si la largeur de l'écran est <= 768px
 */
function isMobile() {
    const breakpoints = window.app?.breakpoints || { mobile: 768 };
    return window.innerWidth <= breakpoints.mobile;
}

/**
 * Vérifie si l'écran est en mode tablette
 * @returns {boolean} - Vrai si la largeur de l'écran est entre 768px et 1024px
 */
function isTablet() {
    const breakpoints = window.app?.breakpoints || { mobile: 768, tablet: 1024 };
    return window.innerWidth > breakpoints.mobile && window.innerWidth <= breakpoints.tablet;
}

/**
 * Vérifie si l'écran est en mode bureau
 * @returns {boolean} - Vrai si la largeur de l'écran est > 1024px
 */
function isDesktop() {
    const breakpoints = window.app?.breakpoints || { tablet: 1024 };
    return window.innerWidth > breakpoints.tablet;
}

/**
 * Formate une date en temps relatif (ex. "il y a 5m")
 * @param {string} dateString - Date au format ISO ou compatible
 * @returns {string} - Temps relatif ou date formatée
 */
function formatTimeAgo(dateString) {
    const now = new Date();
    const past = new Date(dateString);
    const diffInSeconds = Math.floor((now - past) / 1000);

    if (diffInSeconds < 60) return "À l'instant";
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h`;
    if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}j`;

    return past.toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'short',
        year: past.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
    });
}

/**
 * Échappe les caractères HTML pour éviter les attaques XSS
 * @param {string} unsafe - Texte à échapper
 * @returns {string} - Texte échappé
 */
function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

/**
 * Copie du texte dans le presse-papiers
 * @param {string} text - Texte à copier
 * @returns {Promise<void>}
 */
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        window.showToast?.('Copié dans le presse-papiers', 'success');
    } catch (err) {
        // Fallback pour navigateurs non compatibles
        const textArea = document.createElement("textarea");
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        window.showToast?.('Copié dans le presse-papiers', 'success');
    }
}

/**
 * Vérifie si le glisser-déposer est supporté
 * @returns {boolean} - Vrai si supporté
 */
function supportsDragDrop() {
    const div = document.createElement('div');
    return ('draggable' in div) || ('ondragstart' in div && 'ondrop' in div);
}

/**
 * Génère un ID unique
 * @returns {string} - ID basé sur timestamp et aléatoire
 */
function generateId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
}

/**
 * Valide une adresse email
 * @param {string} email - Adresse email à valider
 * @returns {boolean} - Vrai si valide
 */
function isValidEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

/**
 * Tronque le texte avec des points de suspension
 * @param {string} text - Texte à tronquer
 * @param {number} maxLength - Longueur maximale
 * @returns {string} - Texte tronqué
 */
function truncateText(text, maxLength) {
    return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
}

// Export des utilitaires
window.utils = {
    debounce,
    throttle,
    isMobile,
    isTablet,
    isDesktop,
    formatTimeAgo,
    escapeHtml,
    copyToClipboard,
    supportsDragDrop,
    generateId,
    isValidEmail,
    truncateText
};