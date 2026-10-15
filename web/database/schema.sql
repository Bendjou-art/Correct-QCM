-- ============================================================
--  Correct-QCM — Schéma de base de données
--  MySQL / MariaDB (utf8mb4)
--
--  Import :
--      mysql -u root -p correctqcm < web/database/schema.sql
--  PostgreSQL : voir les commentaires en fin de fichier.
-- ============================================================

SET NAMES utf8mb4;

-- ---------- Utilisateurs (enseignants / admins) ----------
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nom           VARCHAR(120) NOT NULL,
    prenom        VARCHAR(120) NOT NULL,
    email         VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('enseignant','admin') NOT NULL DEFAULT 'enseignant',
    cachet_url    VARCHAR(255) NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Sessions d'évaluation (QCM) ----------
CREATE TABLE IF NOT EXISTS qcms (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    id_enseignant        INT NOT NULL,
    titre                VARCHAR(190) NOT NULL,
    matiere              VARCHAR(120) NOT NULL,
    type_evaluation      ENUM('CC','SN') NOT NULL DEFAULT 'CC',
    nb_questions         INT NOT NULL,
    mode_identification  ENUM('matricule','anonymat') NOT NULL DEFAULT 'matricule',
    option_ne_sais_pas   TINYINT(1) NOT NULL DEFAULT 0,
    option_aucune_reponse TINYINT(1) NOT NULL DEFAULT 0,
    statut               ENUM('brouillon','pret','en_correction','cloture') NOT NULL DEFAULT 'brouillon',
    date_creation        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_qcms_user FOREIGN KEY (id_enseignant) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Questions d'un QCM ----------
CREATE TABLE IF NOT EXISTS questions (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    id_qcm                INT NOT NULL,
    numero_question       INT NOT NULL,
    points_bonne_reponse  DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    points_malus          DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    points_ne_sais_pas    DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    points_aucune_reponse DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_questions_qcm FOREIGN KEY (id_qcm) REFERENCES qcms(id) ON DELETE CASCADE,
    UNIQUE KEY uq_question (id_qcm, numero_question)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Corrigés (plusieurs versions possibles, un seul actif) ----------
CREATE TABLE IF NOT EXISTS corriges (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    id_qcm        INT NOT NULL,
    actif         TINYINT(1) NOT NULL DEFAULT 0,
    date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_corriges_qcm FOREIGN KEY (id_qcm) REFERENCES qcms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS corrige_reponses (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    id_corrige      INT NOT NULL,
    id_question     INT NOT NULL,
    bonnes_reponses JSON NOT NULL,
    CONSTRAINT fk_cr_corrige  FOREIGN KEY (id_corrige)  REFERENCES corriges(id)  ON DELETE CASCADE,
    CONSTRAINT fk_cr_question FOREIGN KEY (id_question) REFERENCES questions(id) ON DELETE CASCADE,
    UNIQUE KEY uq_cr (id_corrige, id_question)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Étudiants inscrits à un QCM ----------
CREATE TABLE IF NOT EXISTS etudiants_qcm (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    id_qcm    INT NOT NULL,
    matricule VARCHAR(50) NOT NULL,
    nom       VARCHAR(120) NOT NULL,
    prenom    VARCHAR(120) NULL,
    CONSTRAINT fk_etu_qcm FOREIGN KEY (id_qcm) REFERENCES qcms(id) ON DELETE CASCADE,
    UNIQUE KEY uq_etu (id_qcm, matricule)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Copies numérisées ----------
CREATE TABLE IF NOT EXISTS copies (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    id_qcm                INT NOT NULL,
    matricule             VARCHAR(50) NULL,
    nom_etudiant          VARCHAR(120) NULL,
    prenom_etudiant       VARCHAR(120) NULL,
    identifiant_anonymat  VARCHAR(50) NULL,
    image_url             VARCHAR(255) NULL,
    statut                ENUM('importee','en_correction','corrigee','anomalie') NOT NULL DEFAULT 'importee',
    date_import           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_copies_qcm FOREIGN KEY (id_qcm) REFERENCES qcms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Réponses détectées par l'OCR ----------
CREATE TABLE IF NOT EXISTS reponses_detectees (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    id_copie         INT NOT NULL,
    id_question      INT NOT NULL,
    reponses_cochees JSON NULL,
    type_reponse     ENUM('normale','ne_sais_pas','aucune_reponse','multiple','vide') NOT NULL DEFAULT 'normale',
    confidence_score DECIMAL(5,4) NULL,
    CONSTRAINT fk_rd_copie    FOREIGN KEY (id_copie)    REFERENCES copies(id)    ON DELETE CASCADE,
    CONSTRAINT fk_rd_question FOREIGN KEY (id_question) REFERENCES questions(id) ON DELETE CASCADE,
    UNIQUE KEY uq_rd (id_copie, id_question)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Notes ----------
CREATE TABLE IF NOT EXISTS notes (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    id_copie       INT NOT NULL,
    id_corrige     INT NOT NULL,
    note_totale    DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    detail_calcul  JSON NULL,
    date_calcul    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    statut         ENUM('auto','manuelle','validee') NOT NULL DEFAULT 'auto',
    CONSTRAINT fk_notes_copie   FOREIGN KEY (id_copie)   REFERENCES copies(id)   ON DELETE CASCADE,
    CONSTRAINT fk_notes_corrige FOREIGN KEY (id_corrige) REFERENCES corriges(id) ON DELETE CASCADE,
    UNIQUE KEY uq_note (id_copie)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Anomalies ----------
CREATE TABLE IF NOT EXISTS anomalies (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    id_copie      INT NOT NULL,
    type_anomalie VARCHAR(80) NOT NULL,
    description   TEXT NULL,
    statut        ENUM('a_traiter','traitee','ignoree') NOT NULL DEFAULT 'a_traiter',
    date_detect   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_anom_copie FOREIGN KEY (id_copie) REFERENCES copies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Exports ----------
CREATE TABLE IF NOT EXISTS exports (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    id_qcm        INT NOT NULL,
    id_enseignant INT NOT NULL,
    format        ENUM('csv','excel','pdf') NOT NULL,
    mode          VARCHAR(50) NULL,
    fichier_url   VARCHAR(255) NULL,
    date_export   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_exp_qcm  FOREIGN KEY (id_qcm)        REFERENCES qcms(id)  ON DELETE CASCADE,
    CONSTRAINT fk_exp_user FOREIGN KEY (id_enseignant) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Historique des actions ----------
CREATE TABLE IF NOT EXISTS historique (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    id_utilisateur INT NULL,
    action         VARCHAR(120) NOT NULL,
    entite_type    VARCHAR(80) NULL,
    entite_id      INT NULL,
    details        TEXT NULL,
    date_action    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hist_user FOREIGN KEY (id_utilisateur) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  Compte de démonstration (mot de passe : password)
--  À SUPPRIMER en production.
-- ============================================================
INSERT INTO users (nom, prenom, email, password_hash, role)
SELECT 'Dupont', 'Jean', 'enseignant@test.cm',
       '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1HlCS4bZJ18JuywdBv0k.xqkqKqkqKq', 'enseignant'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'enseignant@test.cm');

-- ------------------------------------------------------------
--  PostgreSQL : remplacer JSON par JSONB, TINYINT(1) par BOOLEAN,
--  ENUM(...) par des CHECK/VARCHAR, AUTO_INCREMENT par GENERATED
--  ALWAYS AS IDENTITY, ENGINE=... par rien, et TIMESTAMP par TIMESTAMPTZ.
-- ------------------------------------------------------------