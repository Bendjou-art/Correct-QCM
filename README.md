# Correct-QCM

Application de correction automatique de QCM (Université de Yaoundé I).
L'enseignant crée une session, génère les formulaires de réponses, fait scanner
les copies, et l'application détecte les cases cochées, compare avec le corrigé
et attribue la note automatiquement.

## Fonctionnalités

- Création de sessions d'évaluation (Contrôle continu et Session normale)
- Configuration du corrigé et recalcul des notes
- Import de la liste des étudiants (Excel/CSV)
- Génération du formulaire PDF (en-tête, grille de réponses, cachet, repères de calibration)
- Numérisation des copies et détection des cases cochées (OCR / vision par ordinateur)
- Gestion des anomalies (copies illisibles, doubles réponses, etc.)
- Consultation et correction manuelle des copies
- Export des résultats (CSV, Excel, procès-verbal PDF)
- Historique des actions
- Application mobile (Flutter)

## Architecture

```
Projet-arited-corretion-des-copies/
├── web/            Application web PHP (interface + API REST)
│   ├── api/        Endpoints (auth, qcms, copies, anomalies, export...)
│   ├── pages/      Pages de l'interface
│   ├── includes/   Services métier (correction, export, anomalies...)
│   ├── assets/     CSS, JS, images
│   └── config/     Configuration (database.php non versionné)
├── ocr-service/    Service Python FastAPI (analyse des copies scannées)
├── mobile/         Application Flutter
└── test-simulation/ Scripts et images de test
```

| Composant | Technologies |
|---|---|
| Web | PHP 8+, TCPDF, PhpSpreadsheet, Composer |
| Service OCR | Python 3.10+, FastAPI, OpenCV, Tesseract |
| Base de données | <PostgreSQL ou MySQL> |
| Mobile | Flutter |

## Prérequis

### Système (Ubuntu/Debian)

```bash
sudo apt update
sudo apt install -y git php php-cli php-gd php-mbstring php-xml php-zip \
    php-curl composer python3 python3-venv python3-pip \
    tesseract-ocr tesseract-ocr-fra
# Selon ta base de données :
sudo apt install -y php-pgsql postgresql      # PostgreSQL
# sudo apt install -y php-mysql mariadb-server  # MySQL / MariaDB
```

## Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/Bendjou-art/Correct-QCM.git
cd Correct-QCM
```

### 2. Base de données

Crée la base et importe le schéma :

```bash
# PostgreSQL
sudo -u postgres createdb correctqcm
sudo -u postgres psql -d correctqcm -f web/database/schema.sql

# MySQL
# mysql -u root -p -e "CREATE DATABASE correctqcm CHARACTER SET utf8mb4"
# mysql -u root -p correctqcm < web/database/schema.sql
```

### 3. Application web

```bash
cd web
composer install
cp config/database.example.php config/database.php
```

Édite `config/database.php` avec tes identifiants de base de données.

Lance le serveur de développement :

```bash
php -S localhost:8080
```

L'application est disponible sur http://localhost:8080.

### 4. Service OCR

```bash
cd ocr-service
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
uvicorn main:app --reload --port 8000
```

Le service tourne sur http://localhost:8000.
La documentation interactive est sur http://localhost:8000/docs.

### 5. Application mobile (optionnel)

```bash
cd mobile
flutter pub get
flutter run
```

## Utilisation

1. Connecte-toi avec un compte enseignant.
2. Crée une session (QCM) : matière, nombre de questions, type d'évaluation.
3. Configure le corrigé.
4. Importe la liste des étudiants (colonnes : `matricule`, `nom`, `prenom`).
   Un modèle est téléchargeable depuis la page d'import.
5. Génère le formulaire PDF et imprime-le.
6. Après l'épreuve, numérise les copies depuis la page Numérisation.
7. Traite les anomalies éventuelles, puis exporte les résultats.

## Types d'évaluation

| | Contrôle continu (CC) | Session normale (SN) |
|---|---|---|
| Identité sur le formulaire | Matricule, nom, prénom préimprimés | Tous les champs d'en-tête préimprimés |
| Identification à la correction | Matricule lu sur la copie | Code d'anonymat |
| Cadre « Ne rien écrire » | Présent | Présent, avec cachet |

## API

Les endpoints sont dans `web/api/`. Authentification par session PHP
(`api/auth/login.php`). Une collection Postman est disponible dans `postman/`
(<à ajouter>).

| Groupe | Endpoints |
|---|---|
| Auth | `auth/login.php`, `auth/logout.php` |
| QCM | `qcms/create.php`, `list.php`, `detail.php`, `update-corrige.php`, `recalculer.php`, `formulaire-pdf.php`, `import-etudiants.php`, `telecharger-modele.php` |
| Copies | `copies/submit.php`, `list.php`, `detail.php`, `search.php`, `corriger.php` |
| Anomalies | `anomalies/list.php`, `traiter.php` |
| Export | `export/csv.php`, `excel.php`, `pv.php` |
| Autres | `historique/list.php`, `users/cachet.php` |

## Sécurité

Ne jamais versionner :
- `web/config/database.php` (identifiants de base de données)
- les fichiers `.env`
- les cachets et données réelles d'étudiants
