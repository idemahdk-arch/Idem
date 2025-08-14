<?php
/**
 * Configuration de la base de données pour IDEM
 * Utilise MySQL via PDO dans XAMPP
 */

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            // Paramètres de connexion pour MySQL dans XAMPP
            $host = 'localhost';
            $port = 3306; // Port par défaut de MySQL
            $dbname = 'idem_social'; // Nom de la base de données
            $username = 'root'; // Utilisateur par défaut dans XAMPP
            $password = ''; // Mot de passe vide

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

            $this->connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

        } catch (PDOException $e) {
            die("Erreur de connexion à la base de données : " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Erreur SQL: " . $e->getMessage());
            throw $e;
        }
    }

    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";

        $stmt = $this->query($sql, $data);
        return $this->connection->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach (array_keys($data) as $column) {
            $set[] = "{$column} = :{$column}";
        }
        $setClause = implode(', ', $set);

        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";

        $params = array_merge($data, $whereParams);
        return $this->query($sql, $params);
    }

    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params);
    }

    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    public function commit() {
        return $this->connection->commit();
    }

    public function rollback() {
        return $this->connection->rollBack();
    }
}

// Initialisation de la base de données
function initDatabase() {
    $db = Database::getInstance();

    // Vérifier si les tables existent, sinon les créer
    try {
        $result = $db->fetchOne("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = 'idem_social' AND table_name = 'users'");

        if ($result['count'] == 0) {
            // Lire et exécuter le schéma SQL
            $schema = file_get_contents(__DIR__ . '/../sql/schema.sql');
            $db->getConnection()->exec($schema);
            echo "Base de données initialisée avec succès !<br>";
        }
    } catch (Exception $e) {
        error_log("Erreur lors de l'initialisation : " . $e->getMessage());
    }

    return $db;
}
?>