<?php
header('Content-Type: application/json');
session_start();
require '../../config/database.php';
require '../../includes/correction_service.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

$id_enseignant = $_SESSION['user_id'];
$qcm_id = $_POST['qcm_id'] ?? null;

if (!$qcm_id || empty($_FILES['copies'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

$pdo = getConnection();

// Vérifier que le QCM appartient bien à l'enseignant connecté
$stmt = $pdo->prepare("SELECT id FROM qcms WHERE id = ? AND id_enseignant = ?");
$stmt->execute([$qcm_id, $id_enseignant]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé à ce QCM']);
    exit;
}

$dossierUpload = '../../uploads/copies/' . $qcm_id . '/';
if (!is_dir($dossierUpload)) {
    mkdir($dossierUpload, 0755, true);
}

$extensionsAutorisees = ['jpg', 'jpeg', 'png', 'pdf'];
$nbAjoutees = 0;
$erreurs = [];

// Compter les copies déjà existantes pour générer un numero_copie séquentiel
$stmt = $pdo->prepare("SELECT COUNT(*) FROM copies WHERE id_qcm = ?");
$stmt->execute([$qcm_id]);
$compteurCopies = (int) $stmt->fetchColumn();

$stmtInsert = $pdo->prepare("
    INSERT INTO copies (id_qcm, numero_copie, image_url, date_numerisation, numerise_par, source_numerisation, statut)
    VALUES (?, ?, ?, NOW(), ?, 'web', 'numerisee')
    RETURNING id
");

foreach ($_FILES['copies']['tmp_name'] as $index => $tmpName) {
    $nomOriginal = $_FILES['copies']['name'][$index];
    $extension = strtolower(pathinfo($nomOriginal, PATHINFO_EXTENSION));

    if (!in_array($extension, $extensionsAutorisees)) {
        $erreurs[] = "$nomOriginal : format non supporté";
        continue;
    }

    if ($_FILES['copies']['error'][$index] !== UPLOAD_ERR_OK) {
        $erreurs[] = "$nomOriginal : erreur d'upload";
        continue;
    }

    $compteurCopies++;
    $numeroCopie = 'C' . str_pad($compteurCopies, 4, '0', STR_PAD_LEFT);
    $nomFichier = uniqid('copie_') . '.' . $extension;
    $cheminDestination = $dossierUpload . $nomFichier;

    if (move_uploaded_file($tmpName, $cheminDestination)) {
        $urlRelative = 'uploads/copies/' . $qcm_id . '/' . $nomFichier;
        $stmtInsert->execute([$qcm_id, $numeroCopie, $urlRelative, $id_enseignant]);
        $copie_id_creee = $stmtInsert->fetchColumn();
        $nbAjoutees++;
        $resultatCorrection = corrigerCopieAutomatiquement($pdo, $copie_id_creee, $id_enseignant);
        if (!$resultatCorrection['success']) {
            $erreurs[] = "$numeroCopie : correction échouée (" . $resultatCorrection['message'] . ")";
        }
    } else {
        $erreurs[] = "$nomOriginal : échec de la sauvegarde";
    }
}

if ($nbAjoutees > 0) {
    // Historiser l'action
    $stmt = $pdo->prepare("
        INSERT INTO historique (id_utilisateur, action, entite_type, entite_id, details)
        VALUES (?, 'numerisation', 'qcm', ?, ?)
    ");
    $stmt->execute([$id_enseignant, $qcm_id, json_encode(['nb_copies' => $nbAjoutees])]);
}

echo json_encode([
    'success' => $nbAjoutees > 0,
    'nb_copies_ajoutees' => $nbAjoutees,
    'erreurs' => $erreurs,
    'message' => $nbAjoutees === 0 ? 'Aucune copie n\'a pu être ajoutée' : null
]);