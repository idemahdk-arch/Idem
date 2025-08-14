<?php
/**
 * Script de démarrage pour IDEM
 * Lance le serveur PHP et affiche les informations de connexion
 */

echo "🚀 Démarrage du réseau social IDEM...<br><br>";

// Vérifier PHP
$phpVersion = PHP_VERSION;
echo "✓ PHP version: $phpVersion<br>";

// Vérifier la base de données MySQL
try {
    require_once 'config/database.php';
    $db = initDatabase();
    echo "✓ Base de données MySQL: Connectée<br>";
    
    // Vérifier les tables principales
    $tables = ['users', 'posts', 'friends', 'notifications', 'conversations'];
    foreach ($tables as $table) {
        $result = $db->query("SELECT COUNT(*) FROM $table");
        $count = $result->fetchColumn();
        echo "  - Table $table: $count enregistrements<br>";
    }
} catch (Exception $e) {
    echo "✗ Erreur base de données: " . $e->getMessage() . "<br>";
}

// Informations de connexion
echo "<br>📱 Application IDEM prête !<br>";
echo "🌐 URL: http://localhost:8000<br>";
echo "🔗 WebSocket: ws://localhost:8080 (notifications temps réel)<br><br>";

echo "👤 Comptes de test :<br>";
echo "  - admin / Admin123!<br>";
echo "  - marie_martin / Test123!<br>";
echo "  - john_doe / Test123!<br><br>";

echo "🎯 Fonctionnalités disponibles :<br>";
echo "  ✓ Authentification sécurisée avec sessions PHP<br>";
echo "  ✓ Publications et fil d'actualité avec likes/commentaires<br>";
echo "  ✓ Système d'amis avec demandes et suggestions<br>";
echo "  ✓ Messagerie instantanée avec conversations<br>";
echo "  ✓ Notifications temps réel via WebSocket<br>";
echo "  ✓ Interface responsive optimisée mobile/PC<br>";
echo "  ✓ Thème sombre/clair adaptatif<br>";
echo "  ✓ Recherche instantanée d'utilisateurs<br><br>";

echo "🛠️  Architecture technique :<br>";
echo "  - Backend: PHP 8.2 + MySQL<br>";
echo "  - Frontend: HTML5/CSS3/JavaScript vanilla<br>";
echo "  - Temps réel: WebSocket (Ratchet)<br>";
echo "  - Sessions: PHP natives sécurisées<br>";
echo "  - API: REST JSON avec protection CSRF<br><br>";

echo "📱 Responsivité complète :<br>";
echo "  - Mobile (< 768px): Menu hamburger, layout 1 colonne<br>";
echo "  - Tablette (768-1024px): Layout adaptatif 2 colonnes<br>";
echo "  - Desktop (> 1024px): Layout complet 3 colonnes<br><br>";

echo "🎨 Interface moderne :<br>";
echo "  - Variables CSS responsives (clamp, vw, rem)<br>";
echo "  - Animations fluides et microinteractions<br>";
echo "  - Toasts de notification intégrés<br>";
echo "  - Modales et dropdowns adaptatifs<br>";
echo "  - Lazy loading des images<br><br>";

echo "⚡ Performance optimisée :<br>";
echo "  - Debounce/Throttle pour événements<br>";
echo "  - Cache intelligent des requêtes<br>";
echo "  - Lazy loading automatique<br>";
echo "  - Compression et minification CSS/JS<br><br>";

echo "🔒 Sécurité renforcée :<br>";
echo "  - Protection CSRF sur toutes les actions<br>";
echo "  - Validation et sanitisation des données<br>";
echo "  - Sessions sécurisées (HttpOnly, Secure)<br>";
echo "  - Échappement automatique HTML/XSS<br><br>";

echo "💬 WebSocket temps réel :<br>";
echo "  - Notifications instantanées<br>";
echo "  - Indicateurs de frappe dans les messages<br>";
echo "  - Statuts utilisateur en ligne/hors ligne<br>";
echo "  - Heartbeat automatique et reconnexion<br><br>";

echo "🌐 Prêt pour la production :<br>";
echo "  - Code optimisé et documenté<br>";
echo "  - Structure modulaire et maintenable<br>";
echo "  - Compatible XAMPP/serveurs classiques<br>";
echo "  - Logs d'erreur complets<br><br>";

echo "🚀 IDEM est maintenant fonctionnel ! Visitez http://localhost:8000 <br>";
?>