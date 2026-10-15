<?php
header('Content-Type: application/json');
session_start();
require '../../vendor/autoload.php';
require '../../config/database.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

$qcm_id = $_POST['qcm_id'] ?? null;
if (!$qcm_id || empty($_FILES['fichier'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

$pdo = getConnection();

$stmt = $pdo->prepare("SELECT id FROM qcms WHERE id = ? AND id_enseignant = ?");
$stmt->execute([$qcm_id, $_SESSION['user_id']]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

$tmpPath = $_FILES['fichier']['tmp_name'];
$extension = strtolower(pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION));

$lignes = [];

try {
    if ($extension === 'csv') {
        $handle = fopen($tmpPath, 'r');
        fgetcsv($handle); // en-tête
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) >= 2 && trim($row[0]) !== '' && trim($row[1]) !== '') {
                $lignes[] = ['nom' => trim($row[0]), 'matricule' => trim($row[1])];
            }
        }
        fclose($handle);
    } else {
        $spreadsheet = IOFactory::load($tmpPath);
        $sheet = $spreadsheet->getActiveSheet();
        $donnees = $sheet->toArray();
        array_shift($donnees);
        foreach ($donnees as $row) {
            $nom = trim($row[0] ?? '');
            $matricule = trim($row[1] ?? '');
            if ($nom !== '' && $matricule !== '') {
                $lignes[] = ['nom' => $nom, 'matricule' => $matricule];
            }
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fichier illisible : ' . $e->getMessage()]);
    exit;
}

if (empty($lignes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Aucune donnée valide trouvée dans le fichier']);
    exit;
}

$nbImportes = 0;
$erreurs = [];

$stmtCompte = $pdo->prepare("SELECT COUNT(*) FROM etudiants_qcm WHERE id_qcm = ?");
$stmtCompte->execute([$qcm_id]);
$compteur = (int) $stmtCompte->fetchColumn();

$stmtInsert = $pdo->prepare("
    INSERT INTO etudiants_qcm (id_qcm, matricule, nom, numero_copie)
    VALUES (?, ?, ?, ?)
    ON CONFLICT (id_qcm, matricule) DO UPDATE SET nom = EXCLUDED.nom
");

foreach ($lignes as $ligne) {
    $compteur++;
    $numeroCopie = 'C' . str_pad($compteur, 4, '0', STR_PAD_LEFT);
    try {
        $stmtInsert->execute([$qcm_id, $ligne['matricule'], $ligne['nom'], $numeroCopie]);
        $nbImportes++;
    } catch (Exception $e) {
        $erreurs[] = $ligne['matricule'];
    }
}

echo json_encode([
    'success' => true,
    'nb_importes' => $nbImportes,
    'erreurs' => $erreurs
]);