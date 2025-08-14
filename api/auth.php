<?php
/**
 * API d'authentification pour IDEM
 * Gère l'inscription, la connexion et la déconnexion
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once '../PHPMailer/src/PHPMailer.php';
require_once "../PHPMailer/src/Exception.php";

require_once '../PHPMailer/src/SMTP.php';
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Initialiser la session et la base de données
SessionManager::start();
$db = initDatabase();

// Vérifier la méthode HTTP
//if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['verify_email']))) {
//    http_response_code(405);
//    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
//    exit;
//}
//
//// Vérifier le token CSRF pour les requêtes POST
//if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//    if (!isset($_POST['csrf_token']) || !SessionManager::validateCsrfToken($_POST['csrf_token'])) {
//        http_response_code(403);
//        echo json_encode(['success' => false, 'message' => 'Token CSRF invalide']);
//        exit;
//    }
//}

/**
 * Envoyer un email avec PHPMailer
 * @param string $to - Adresse email du destinataire
 * @param string $subject - Sujet de l'email
 * @param string $body - Corps de l'email
 * @return bool - Succès de l'envoi
 */
function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);

    try {
        // Configuration du serveur SMTP (exemple avec Gmail)
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Remplacez par votre serveur SMTP
        $mail->SMTPAuth = true;
        $mail->Username = 'idemahdk@gmail.com'; // Votre adresse email
        $mail->Password = 'pbdyppedjhobwznc'; // Mot de passe ou mot de passe d'application
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // ou 'tls' pour port 587
        $mail->Port = 465; // ou 587 pour TLS

        // Configuration de l'email
        $mail->setFrom('idemahdk@gmail.com', 'IDEM');
        $mail->addAddress($to);
        $mail->isHTML(false); // Email en texte brut
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body = $body;

        // Activer le débogage (facultatif)
        // $mail->SMTPDebug = 2; // Débogage détaillé
        // $mail->Debugoutput = 'html';

        $mail->send();
        error_log("Email envoyé à $to");
        return true;
    } catch (Exception $e) {
        error_log("Échec de l'envoi de l'email à $to: " . $mail->ErrorInfo);
        return false;
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'login':
                handleLogin();
                break;

            case 'register':
                handleRegister();
                break;

            case 'logout':
                handleLogout();
                break;

            default:
                throw new Exception('Action non reconnue');
        }
    } catch (Exception $e) {
        error_log("Erreur API auth: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Gérer la connexion
 */
function handleLogin() {
    global $db;

    $emailUsername = trim($_POST['email_username'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);

    if (empty($emailUsername) || empty($password)) {
        throw new Exception('Email/nom d\'utilisateur et mot de passe requis');
    }

    // Chercher l'utilisateur par email ou nom d'utilisateur avec un seul placeholder
    $user = $db->fetchOne(
        "SELECT id, username, email, password, first_name, last_name, is_verified, is_active 
         FROM users 
         WHERE (email = :credential OR username = :credentiale) 
         AND is_active = true",
        [':credential' => $emailUsername,':credentiale' => $emailUsername]
    );

    if (!$user) {
        usleep(500000); // 0.5 seconde pour éviter les attaques par force brute
        throw new Exception('Identifiants incorrects');
    }

    if (!verifyPassword($password, $user['password'])) {
        error_log("Tentative de connexion échouée pour: " . $emailUsername);
        usleep(500000);
        throw new Exception('Identifiants incorrects');
    }

    if (!$user['is_verified']) {
        throw new Exception('Veuillez vérifier votre email avant de vous connecter');
    }

    SessionManager::login($user);

    if ($rememberMe) {
        // TODO: Implémenter les cookies de longue durée
    }

    error_log("Connexion réussie pour: " . $user['username']);

    echo json_encode([
        'success' => true,
        'message' => 'Connexion réussie',
        'redirect' => $_POST['redirect'] ?? '/feed.php',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'first_name' => $user['first_name']
        ]
    ]);
}

/**
 * Gérer l'inscription
 */
function handleRegister() {
    global $db;

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $birthDate = $_POST['birth_date'] ?? null;
    $acceptTerms = isset($_POST['accept_terms']);
    $newsletter = isset($_POST['newsletter']);

    if (empty($firstName) || empty($lastName) || empty($username) || empty($email) || empty($password)) {
        throw new Exception('Tous les champs obligatoires doivent être remplis');
    }

    if (!$acceptTerms) {
        throw new Exception('Vous devez accepter les conditions d\'utilisation');
    }

    if (!validateEmail($email)) {
        throw new Exception('Adresse email invalide');
    }

    if (!validateUsername($username)) {
        throw new Exception('Nom d\'utilisateur invalide (3-30 caractères, lettres, chiffres et _ uniquement)');
    }

    if (!validatePassword($password)) {
        throw new Exception('Le mot de passe doit contenir au moins 8 caractères avec une majuscule, une minuscule et un chiffre');
    }

    if ($password !== $confirmPassword) {
        throw new Exception('Les mots de passe ne correspondent pas');
    }

    if ($birthDate && !empty($birthDate)) {
        $age = (new DateTime())->diff(new DateTime($birthDate))->y;
        if ($age < 13) {
            throw new Exception('Vous devez avoir au moins 13 ans pour créer un compte');
        }
    }

    $existingUser = $db->fetchOne(
        "SELECT id FROM users WHERE email = :email OR username = :username",
        ['email' => $email, 'username' => $username]
    );

    if ($existingUser) {
        $existingEmail = $db->fetchOne("SELECT id FROM users WHERE email = :email", ['email' => $email]);
        $existingUsername = $db->fetchOne("SELECT id FROM users WHERE username = :username", ['username' => $username]);

        if ($existingEmail) {
            throw new Exception('Cette adresse email est déjà utilisée');
        }
        if ($existingUsername) {
            throw new Exception('Ce nom d\'utilisateur est déjà pris');
        }
    }

    $db->beginTransaction();

    try {
        $hashedPassword = hashPassword($password);

        $userId = $db->insert('users', [
            'username' => $username,
            'email' => $email,
            'password' => $hashedPassword,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'birth_date' => $birthDate ?: null,
            'is_verified' => false,
            'is_active' => true
        ]);

        $verificationToken = generateEmailToken($userId, 'verification');

        if (!$verificationToken) {
            throw new Exception('Erreur lors de la génération du token de vérification');
        }

        $verificationLink = "https://" . $_SERVER['HTTP_HOST'] . "/Idems/Idem%203.0/IdemNet/verify-email.php?token=" . urlencode($verificationToken);
        $emailSent = sendEmail(
            $email,
            "Vérifiez votre compte IDEM",
            "Bonjour $firstName,\n\nMerci de vous être inscrit sur IDEM !\n\nPour activer votre compte, cliquez sur ce lien :\n$verificationLink\n\nCe lien expire dans 24 heures.\n\nÀ bientôt sur IDEM !"
        );

        if (!$emailSent) {
            throw new Exception('Échec de l\'envoi de l\'email de vérification');
        }

        $db->commit();

        error_log("Nouveau compte créé: $username ($email)");

        echo json_encode([
            'success' => true,
            'message' => 'Compte créé avec succès ! Vérifiez votre email pour activer votre compte.',
            'user_id' => $userId,
            'email_sent' => $emailSent
        ]);

    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

/**
 * Gérer la déconnexion
 */
function handleLogout() {
    if (!SessionManager::isLoggedIn()) {
        throw new Exception('Aucune session active');
    }

    $username = SessionManager::getUsername();
    SessionManager::logout();

    error_log("Déconnexion: $username");

    echo json_encode([
        'success' => true,
        'message' => 'Déconnexion réussie',
        'redirect' => '/'
    ]);
}

/**
 * Vérification d'email (endpoint séparé)
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['verify_email'])) {
    $token = $_GET['token'] ?? '';

    if (empty($token)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Token manquant']);
        exit;
    }

    try {
        $db = initDatabase();

        $tokenData = $db->fetchOne(
            "SELECT user_id, expires_at FROM email_tokens 
             WHERE token = :token AND type = 'verification'",
            ['token' => $token]
        );

        if (!$tokenData) {
            throw new Exception('Token invalide ou expiré');
        }

        if (strtotime($tokenData['expires_at']) < time()) {
            $db->delete('email_tokens', 'token = :token', ['token' => $token]);
            throw new Exception('Le lien de vérification a expiré');
        }

        $db->beginTransaction();

        $db->update('users',
            ['is_verified' => true],
            'id = :id',
            ['id' => $tokenData['user_id']]
        );

        $db->delete('email_tokens', 'token = :token', ['token' => $token]);

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Email vérifié avec succès ! Vous pouvez maintenant vous connecter.'
        ]);

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
?>