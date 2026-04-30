-- StagIA - Base de données complète
-- Encodage: UTF-8

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

CREATE DATABASE IF NOT EXISTS stagia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE stagia;

-- =============================================
-- TABLES DE RÉFÉRENCE
-- =============================================

CREATE TABLE IF NOT EXISTS directions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(150) NOT NULL,
    abreviation VARCHAR(20),
    actif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS domaines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(150) NOT NULL,
    actif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- UTILISATEURS
-- =============================================

CREATE TABLE IF NOT EXISTS utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('administrateur','habilite','superviseur','directeur','stagiaire') NOT NULL DEFAULT 'stagiaire',
    actif TINYINT(1) DEFAULT 1,
    civilite ENUM('M.','Mme') DEFAULT 'M.',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- STAGIAIRES
-- =============================================

CREATE TABLE IF NOT EXISTS stagiaires (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    niveau_etude ENUM('bac','bac+2','bac+3','bac+4','bac+5','doctorat') DEFAULT 'bac+3',
    telephone VARCHAR(30),
    adresse TEXT,
    ville VARCHAR(100),
    nationalite VARCHAR(100) DEFAULT 'Guinéenne',
    date_naissance DATE,
    sexe ENUM('M','F') DEFAULT 'M',
    domaine_principal_id INT,
    date_inscription TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cv_fichier VARCHAR(255),
    cv_analyse JSON,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (domaine_principal_id) REFERENCES domaines(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS formations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stagiaire_id INT NOT NULL,
    diplome VARCHAR(150),
    specialite VARCHAR(150),
    etablissement VARCHAR(200),
    annee_fin YEAR,
    FOREIGN KEY (stagiaire_id) REFERENCES stagiaires(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS competences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stagiaire_id INT NOT NULL,
    libelle VARCHAR(150) NOT NULL,
    FOREIGN KEY (stagiaire_id) REFERENCES stagiaires(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS experiences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stagiaire_id INT NOT NULL,
    poste VARCHAR(150),
    entreprise VARCHAR(200),
    date_debut DATE,
    date_fin DATE,
    description TEXT,
    FOREIGN KEY (stagiaire_id) REFERENCES stagiaires(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- OFFRES DE STAGE
-- =============================================

CREATE TABLE IF NOT EXISTS offres_stage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(30) UNIQUE NOT NULL,
    titre VARCHAR(200) NOT NULL,
    description TEXT,
    profil_recherche TEXT,
    direction_id INT,
    domaine_id INT,
    niveau_minimum ENUM('bac','bac+2','bac+3','bac+4','bac+5','doctorat') DEFAULT 'bac+3',
    experience_minimale INT DEFAULT 0 COMMENT 'en mois',
    nb_places INT DEFAULT 1,
    date_publication DATE,
    date_limite DATE,
    statut ENUM('ouverte','fermee','archivee') DEFAULT 'ouverte',
    creee_par INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (direction_id) REFERENCES directions(id) ON DELETE SET NULL,
    FOREIGN KEY (domaine_id) REFERENCES domaines(id) ON DELETE SET NULL,
    FOREIGN KEY (creee_par) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =============================================
-- CANDIDATURES
-- =============================================

CREATE TABLE IF NOT EXISTS candidatures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(30) UNIQUE NOT NULL,
    stagiaire_id INT NOT NULL,
    offre_id INT,
    type_candidature ENUM('offre','spontanee') DEFAULT 'spontanee',
    domaine_id INT,
    direction_id INT,
    score_tri DECIMAL(5,2) DEFAULT 0,
    statut_global ENUM('soumise','niv1_en_cours','niv1_valide','validee','niv1_rejete','rejetee','complement') DEFAULT 'soumise',
    motivation TEXT,
    date_candidature TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stagiaire_id) REFERENCES stagiaires(id) ON DELETE CASCADE,
    FOREIGN KEY (offre_id) REFERENCES offres_stage(id) ON DELETE SET NULL,
    FOREIGN KEY (domaine_id) REFERENCES domaines(id) ON DELETE SET NULL,
    FOREIGN KEY (direction_id) REFERENCES directions(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS validations_candidature (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidature_id INT NOT NULL,
    niveau_validation ENUM('niv1','niv2') NOT NULL,
    validateur_id INT,
    statut ENUM('en_attente','valide','rejete','complement') DEFAULT 'en_attente',
    commentaire TEXT,
    date_decision TIMESTAMP NULL,
    FOREIGN KEY (candidature_id) REFERENCES candidatures(id) ON DELETE CASCADE,
    FOREIGN KEY (validateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =============================================
-- STAGES
-- =============================================

CREATE TABLE IF NOT EXISTS stages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(30) UNIQUE NOT NULL,
    candidature_id INT,
    stagiaire_id INT NOT NULL,
    direction_id INT,
    domaine_id INT,
    encadrant_id INT,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    duree_initiale_mois INT DEFAULT 3,
    duree_totale_mois INT DEFAULT 3,
    nb_renouvellements INT DEFAULT 0,
    remboursement_transport TINYINT(1) DEFAULT 0,
    montant_transport DECIMAL(10,2) DEFAULT 0,
    statut ENUM('preparation','en_cours','renouvele','termine','interrompu','annule') DEFAULT 'preparation',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidature_id) REFERENCES candidatures(id) ON DELETE SET NULL,
    FOREIGN KEY (stagiaire_id) REFERENCES stagiaires(id) ON DELETE CASCADE,
    FOREIGN KEY (direction_id) REFERENCES directions(id) ON DELETE SET NULL,
    FOREIGN KEY (domaine_id) REFERENCES domaines(id) ON DELETE SET NULL,
    FOREIGN KEY (encadrant_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS renouvellements_stage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stage_id INT NOT NULL,
    numero_renouvellement INT DEFAULT 1,
    date_debut_proposee DATE,
    date_fin_proposee DATE,
    duree_ajoutee_mois INT DEFAULT 1,
    motif_demande TEXT,
    statut ENUM('en_attente','valide','rejete','precisions') DEFAULT 'en_attente',
    traite_par INT,
    commentaire_decision TEXT,
    date_decision TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stage_id) REFERENCES stages(id) ON DELETE CASCADE,
    FOREIGN KEY (traite_par) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =============================================
-- NOTIFICATIONS
-- =============================================

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    type_notification VARCHAR(50),
    objet VARCHAR(200),
    message TEXT,
    statut_lecture ENUM('non_lu','lu') DEFAULT 'non_lu',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- DONNÉES DE TEST
-- =============================================

-- Directions
INSERT INTO directions (libelle, abreviation) VALUES
('Direction des Ressources Humaines', 'DRH'),
('Direction Administrative et Financière', 'DAF'),
('Direction des Systèmes d\'Information', 'DSI'),
('Direction Technique', 'DT'),
('Direction Commerciale', 'DC');

-- Domaines
INSERT INTO domaines (libelle) VALUES
('Informatique'),
('Finance'),
('Ressources Humaines'),
('Marketing'),
('Juridique');

-- Utilisateurs (mot de passe: password => $2y$12$...)
-- Hash de 'password'
INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role, civilite) VALUES
('Admin', 'StagIA', 'admin@stagia.org', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'administrateur', 'M.'),
('Diallo', 'Mamadou', 'm.diallo@stagia.org', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'habilite', 'M.'),
('Camara', 'Ibrahima', 'i.camara@stagia.org', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superviseur', 'M.'),
('Barry', 'Fatoumata', 'f.barry@stagia.org', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'directeur', 'Mme'),
('Sow', 'Ibrahim', 'ibrahim.sow@email.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'stagiaire', 'M.');

-- Stagiaire
INSERT INTO stagiaires (utilisateur_id, niveau_etude, telephone, adresse, ville, date_naissance, sexe, domaine_principal_id) VALUES
(5, 'bac+3', '+224 620 000 001', 'Quartier Ratoma, Rue KA-032', 'Conakry', '2000-05-15', 'M', 1);

-- Formation du stagiaire
INSERT INTO formations (stagiaire_id, diplome, specialite, etablissement, annee_fin) VALUES
(1, 'Licence', 'Informatique de Gestion', 'Université Gamal Abdel Nasser de Conakry', 2023);

-- Competences
INSERT INTO competences (stagiaire_id, libelle) VALUES
(1, 'PHP/MySQL'),
(1, 'JavaScript'),
(1, 'Python'),
(1, 'Microsoft Office');

-- Offres de stage
INSERT INTO offres_stage (reference, titre, description, profil_recherche, direction_id, domaine_id, niveau_minimum, nb_places, date_publication, date_limite, statut, creee_par) VALUES
('OFF-2025-001', 'Stage Développement Web', 'Stage en développement d\'applications web internes. Le stagiaire participera à la conception et au développement de solutions numériques.', 'Etudiant en informatique, bac+3 minimum, maîtrise de PHP et JavaScript', 3, 1, 'bac+3', 2, '2025-01-15', '2025-06-30', 'ouverte', 1),
('OFF-2025-002', 'Stage Gestion RH', 'Stage en gestion des ressources humaines. Participation aux processus de recrutement et de gestion administrative du personnel.', 'Etudiant en RH ou gestion, bac+2 minimum, bonne communication', 1, 3, 'bac+2', 1, '2025-02-01', '2025-07-31', 'ouverte', 1),
('OFF-2025-003', 'Stage Finance et Comptabilité', 'Stage au sein de la direction financière pour appuyer les équipes comptables et d\'analyse financière.', 'Etudiant en finance/comptabilité, bac+3 minimum, maîtrise d\'Excel', 2, 2, 'bac+3', 1, '2025-02-15', '2025-08-31', 'ouverte', 1);

-- Candidatures
INSERT INTO candidatures (reference, stagiaire_id, offre_id, type_candidature, domaine_id, direction_id, score_tri, statut_global, motivation, date_candidature) VALUES
('CAND-2025-001', 1, 1, 'offre', 1, 3, 78.50, 'niv1_valide', 'Passionné par le développement web, je souhaite mettre en pratique mes connaissances acquises à l\'université tout en apportant ma contribution à votre direction.', '2025-03-01 10:30:00'),
('CAND-2025-002', 1, NULL, 'spontanee', 1, 3, 65.00, 'soumise', 'Je souhaite effectuer un stage dans le domaine informatique afin de compléter ma formation théorique par une expérience professionnelle enrichissante.', '2025-03-15 14:00:00');

-- Validations candidature
INSERT INTO validations_candidature (candidature_id, niveau_validation, validateur_id, statut, commentaire, date_decision) VALUES
(1, 'niv1', 2, 'valide', 'Profil correspondant aux attentes. Bonne maîtrise technique.', '2025-03-05 09:00:00'),
(1, 'niv2', 3, 'valide', 'Candidature validée. Stage approuvé.', '2025-03-10 11:00:00');

-- Stage actif
INSERT INTO stages (reference, candidature_id, stagiaire_id, direction_id, domaine_id, encadrant_id, date_debut, date_fin, duree_initiale_mois, duree_totale_mois, remboursement_transport, montant_transport, statut) VALUES
('STG-2025-001', 1, 1, 3, 1, 2, '2025-04-01', '2025-06-30', 3, 3, 1, 50000.00, 'en_cours');

-- Notifications
INSERT INTO notifications (utilisateur_id, type_notification, objet, message) VALUES
(5, 'candidature', 'Candidature validée', 'Votre candidature CAND-2025-001 a été validée. Un stage vous sera attribué prochainement.'),
(5, 'stage', 'Stage démarré', 'Votre stage STG-2025-001 a démarré le 01/04/2025 à la Direction des Systèmes d\'Information.');

SET foreign_key_checks = 1;
