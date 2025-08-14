<?php
/**
 * Page de déconnexion IDEM
 */

require_once 'config/session.php';

// Déconnecter l'utilisateur
SessionManager::logout();

// Rediriger vers la page d'accueil
header('Location: /');
exit;
?>