q<?php
/**
 * Spustite tento súbor RAZ na inicializáciu databázy.
 * Po úspešnom nastavení ho ZMAŽTE alebo premajte na .bak
 *
 * Štandardné prihlasovacie údaje admina:
 *   Email:    admin@example.com
 *   Heslo:    admin123
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'vydavky');

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . DB_NAME . '`');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            username     VARCHAR(50)  NOT NULL,
            email        VARCHAR(100) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role         ENUM('user','admin') NOT NULL DEFAULT 'user',
            is_blocked   TINYINT(1)   NOT NULL DEFAULT 0,
            created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_username (username),
            UNIQUE KEY uq_email (email)
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS profiles (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            user_id     INT NOT NULL UNIQUE,
            name        VARCHAR(100) NOT NULL,
            avatar_path VARCHAR(255) DEFAULT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id      INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name    VARCHAR(100) NOT NULL,
            type    ENUM('vydavok','prijem') NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transactions (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            user_id     INT NOT NULL,
            category_id INT DEFAULT NULL,
            amount      DECIMAL(10,2) NOT NULL,
            type        ENUM('vydavok','prijem') NOT NULL,
            description VARCHAR(255) NOT NULL,
            date        DATE NOT NULL,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id)     REFERENCES users(id)       ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES categories(id)  ON DELETE SET NULL
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS files (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            user_id         INT NOT NULL,
            transaction_id  INT DEFAULT NULL,
            original_name   VARCHAR(255) NOT NULL,
            stored_name     VARCHAR(255) NOT NULL,
            size            INT NOT NULL,
            mime_type       VARCHAR(100) NOT NULL,
            thumbnail_path  VARCHAR(255) DEFAULT NULL,
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id)        REFERENCES users(id)        ON DELETE CASCADE,
            FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tags (
            id      INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name    VARCHAR(50) NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY uq_user_tag (user_id, name)
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transaction_tags (
            transaction_id INT NOT NULL,
            tag_id         INT NOT NULL,
            PRIMARY KEY (transaction_id, tag_id),
            FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
            FOREIGN KEY (tag_id)         REFERENCES tags(id)         ON DELETE CASCADE
        )
    ");

    // Create admin account (INSERT IGNORE = safe to re-run)
    $adminHash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT IGNORE INTO users (username, email, password_hash, role) VALUES (?,?,?,?)');
    $stmt->execute(['admin', 'admin@example.com', $adminHash, 'admin']);
    $adminId = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM users WHERE username='admin'")->fetchColumn();

    $pdo->prepare('INSERT IGNORE INTO profiles (user_id, name) VALUES (?,?)')->execute([$adminId, 'Administrator']);

    // Create upload directories
    foreach ([__DIR__ . '/uploads', __DIR__ . '/uploads/thumbs'] as $dir) {
        if (!is_dir($dir)) mkdir($dir, 0755, true);
    }

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Setup</title>
    <style>body{font-family:system-ui;max-width:500px;margin:60px auto;padding:20px}
    .ok{background:#d4edda;padding:15px;border-radius:6px;color:#155724}</style></head><body>';
    echo '<div class="ok"><h2>✓ Nastavenie úspešné!</h2>
    <p>Databáza a tabuľky boli vytvorené.</p>
    <p><strong>Admin účet:</strong><br>
    Email: <code>admin@example.com</code><br>
    Heslo: <code>admin123</code></p>
    <p><strong>Zmažte tento súbor</strong> po prihlásení!</p>
    <p><a href="login.php">Prejsť na prihlásenie →</a></p></div>';

} catch (PDOException $e) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Setup Error</title>
    <style>body{font-family:system-ui;max-width:500px;margin:60px auto;padding:20px}
    .err{background:#f8d7da;padding:15px;border-radius:6px;color:#721c24}</style></head><body>';
    echo '<div class="err"><h2>Chyba</h2><p>' . htmlspecialchars($e->getMessage()) . '</p>
    <p>Skontrolujte údaje v <code>setup.php</code> (DB_HOST, DB_USER, DB_PASS).</p></div>';
}
?>
