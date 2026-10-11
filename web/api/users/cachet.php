<?php
header('Content-Type: application/json');
session_start();
require '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

if (empty($_FILES['cachet'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Aucun fichier reçu']);
    exit;
}

$extensionsAutorisees = ['jpg', 'jpeg', 'png'];
$extension = strtolower(pathinfo($_FILES['cachet']['name'], PATHINFO_EXTENSION));

if (!in_array($extension, $extensionsAutorisees)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Format non supporté (JPG/PNG uniquement)']);
    exit;
}

$dossier = '../../uploads/cachets/';
if (!is_dir($dossier)) {
    mkdir($dossier, 0755, true);
}

$nomFichier = 'cachet_' . $_SESSION['user_id'] . '.' . $extension;
$cheminDestination = $dossier . $nomFichier;

if (move_uploaded_file($_FILES['cachet']['tmp_name'], $cheminDestination)) {
    $urlRelative = 'uploads/cachets/' . $nomFichier;

    $pdo = getConnection();
    $stmt = $pdo->prepare("UPDATE users SET cachet_url = ? WHERE id = ?");
    $stmt->execute([$urlRelative, $_SESSION['user_id']]);

    echo json_encode(['success' => true, 'cachet_url' => $urlRelative]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Échec de l\'enregistrement']);
}