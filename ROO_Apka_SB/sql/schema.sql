-- Správca výdavkov – databázová schéma
-- Spustite cez setup.php alebo importujte manuálne

CREATE DATABASE IF NOT EXISTS vydavky CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE vydavky;

-- 1:N vzťah: users -> profiles (1:1 cez UNIQUE user_id)
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL,
    email         VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('user','admin') NOT NULL DEFAULT 'user',
    is_blocked    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_username (username),
    UNIQUE KEY uq_email (email)
);

-- 1:1 vzťah s users (UNIQUE user_id)
CREATE TABLE IF NOT EXISTS profiles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL UNIQUE,
    name        VARCHAR(100) NOT NULL,
    avatar_path VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 1:N vzťah: users -> categories
CREATE TABLE IF NOT EXISTS categories (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name    VARCHAR(100) NOT NULL,
    type    ENUM('vydavok','prijem') NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 1:N vzťah: users -> transactions, categories -> transactions
CREATE TABLE IF NOT EXISTS transactions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    category_id INT DEFAULT NULL,
    amount      DECIMAL(10,2) NOT NULL,
    type        ENUM('vydavok','prijem') NOT NULL,
    description VARCHAR(255) NOT NULL,
    date        DATE NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Metadáta súborov (receipts/avatary)
CREATE TABLE IF NOT EXISTS files (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT NOT NULL,
    transaction_id INT DEFAULT NULL,
    original_name  VARCHAR(255) NOT NULL,
    stored_name    VARCHAR(255) NOT NULL,
    size           INT NOT NULL,
    mime_type      VARCHAR(100) NOT NULL,
    thumbnail_path VARCHAR(255) DEFAULT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)        REFERENCES users(id)        ON DELETE CASCADE,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE
);

-- Štítky (M:N s transactions cez transaction_tags)
CREATE TABLE IF NOT EXISTS tags (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name    VARCHAR(50) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_user_tag (user_id, name)
);

-- M:N spojovacia tabuľka: transactions <-> tags
CREATE TABLE IF NOT EXISTS transaction_tags (
    transaction_id INT NOT NULL,
    tag_id         INT NOT NULL,
    PRIMARY KEY (transaction_id, tag_id),
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id)         REFERENCES tags(id)         ON DELETE CASCADE
);
