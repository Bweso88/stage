-- Base de données : formation
-- Schéma complet – Espace Formation MUCODEC

CREATE DATABASE IF NOT EXISTS formation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE formation;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id         INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(60)     NOT NULL UNIQUE,
    password   VARCHAR(255)    NOT NULL,
    fullname   VARCHAR(120)    NOT NULL,
    role       ENUM('ADMIN','FORMATEUR') NOT NULL DEFAULT 'FORMATEUR',
    active     TINYINT(1)      NOT NULL DEFAULT 1,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table des journaux de connexion
CREATE TABLE IF NOT EXISTS user_logins (
    id         INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED    NOT NULL,
    login_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Compte administrateur par défaut (mot de passe : Admin1234)
-- CHANGEZ ce mot de passe dès la première connexion !
INSERT IGNORE INTO users (username, password, fullname, role, active)
VALUES (
    'admin',
    '$2y$12$eImiTXuWVxfM37uY4JANjOaVAcMjU4YWz2eTMqb0hHjBM10fNxdLW',
    'Administrateur',
    'ADMIN',
    1
);
