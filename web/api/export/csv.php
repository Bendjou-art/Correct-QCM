<?php
session_start();
require '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Non authentifié');
}

$qcm_id = $_GET['qcm_id'] ?? null;
$mode = $_GET['mode'] ?? 'nominatif'; // 'nominatif' ou 'anonymise'

if (!$qcm_id) {
    die('qcm_id manquant');
}

$pdo = getConnection();

// Vérifier que le QCM appartient bien à l'enseignant connecté
$stmt = $pdo->prepare("SELECT titre FROM qcms WHERE id = ? AND id_enseignant = ?");
$stmt->execute([$qcm_id, $_SESSION['user_id']]);
$qcm = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$qcm) {
    die('QCM introuvable ou accès non autorisé');
}

// Récupérer les copies + notes
$stmt = $pdo->prepare("
    SELECT c.matricule, c.nom_etudiant, c.prenom_etudiant, c.identifiant_anonymat,
           c.numero_copie, c.statut, n.note_totale
    FROM copies c
    LEFT JOIN notes n ON n.id_copie = c.id
    WHERE c.id_qcm = ?
    ORDER BY c.numero_copie
");
$stmt->execute([$qcm_id]);
$copies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Historiser l'export
$stmtHist = $pdo->prepare("
    INSERT INTO historique (id_utilisateur, action, entite_type, entite_id, details)
    VALUES (?, 'export_resultats', 'qcm', ?, ?)
");
$stmtHist->execute([$_SESSION['user_id'], $qcm_id, json_encode(['format' => 'csv', 'mode' => $mode])]);

// Enregistrer dans la table exports
$nomFichier = 'resultats_' . preg_replace('/[^a-zA-Z0-9]/', '_', $qcm['titre']) . '_' . $mode . '_' . date('Ymd_His') . '.csv';

$stmtExport = $pdo->prepare("
    INSERT INTO exports (id_qcm, id_enseignant, format, mode, fichier_url, date_export)
    VALUES (?, ?, 'csv', ?, ?, NOW())
");
$stmtExport->execute([$qcm_id, $_SESSION['user_id'], $mode, 'uploads/export/' . $nomFichier]);

// Génération du CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nomFichier . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8 pour Excel

if ($mode === 'anonymise') {
    fputcsv($output, ['Code anonymat', 'N° Copie', 'Statut', 'Note']);
    foreach ($copies as $c) {
        fputcsv($output, [
            $c['identifiant_anonymat'] ?? '—',
            $c['numero_copie'],
            ucfirst($c['statut']),
            $c['note_totale'] !== null ? $c['note_totale'] . '/20' : 'Non corrigée'
        ]);
    }
} else {
    fputcsv($output, ['Matricule', 'Nom', 'Prénom(s)', 'N° Copie', 'Statut', 'Note']);
    foreach ($copies as $c) {
        fputcsv($output, [
            $c['matricule'] ?? '—',
            $c['nom_etudiant'] ?? '—',
            $c['prenom_etudiant'] ?? '—',
            $c['numero_copie'],
            ucfirst($c['statut']),
            $c['note_totale'] !== null ? $c['note_totale'] . '/20' : 'Non corrigée'
        ]);
    }
}

fclose($output);