<?php
require '../../vendor/autoload.php';
session_start();
require '../../config/database.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Non authentifié');
}

$qcm_id = $_GET['qcm_id'] ?? null;
$mode = $_GET['mode'] ?? 'nominatif';

if (!$qcm_id) {
    die('qcm_id manquant');
}

$pdo = getConnection();

$stmt = $pdo->prepare("SELECT titre, matiere, type_evaluation FROM qcms WHERE id = ? AND id_enseignant = ?");
$stmt->execute([$qcm_id, $_SESSION['user_id']]);
$qcm = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$qcm) {
    die('QCM introuvable ou accès non autorisé');
}

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

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Résultats');

// En-tête du document
$sheet->setCellValue('A1', 'Université de Yaoundé I');
$sheet->setCellValue('A2', htmlspecialchars_decode($qcm['titre']) . ' - ' . $qcm['matiere']);
$sheet->setCellValue('A3', $qcm['type_evaluation'] . ' - Exporté le ' . date('d/m/Y à H:i'));
$sheet->mergeCells('A1:F1');
$sheet->mergeCells('A2:F2');
$sheet->mergeCells('A3:F3');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
$sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(9);

$ligneEntete = 5;

if ($mode === 'anonymise') {
    $entetes = ['Code anonymat', 'N° Copie', 'Statut', 'Note'];
} else {
    $entetes = ['Matricule', 'Nom', 'Prénom(s)', 'N° Copie', 'Statut', 'Note'];
}

foreach ($entetes as $i => $titre) {
    $col = chr(65 + $i); // A, B, C...
    $sheet->setCellValue($col . $ligneEntete, $titre);
}
$sheet->getStyle('A' . $ligneEntete . ':' . chr(65 + count($entetes) - 1) . $ligneEntete)
    ->getFont()->setBold(true);
$sheet->getStyle('A' . $ligneEntete . ':' . chr(65 + count($entetes) - 1) . $ligneEntete)
    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('6C5DD3');
$sheet->getStyle('A' . $ligneEntete . ':' . chr(65 + count($entetes) - 1) . $ligneEntete)
    ->getFont()->getColor()->setRGB('FFFFFF');

$ligne = $ligneEntete + 1;
foreach ($copies as $c) {
    $note = $c['note_totale'] !== null ? $c['note_totale'] : 'Non corrigée';

    if ($mode === 'anonymise') {
        $sheet->setCellValue('A' . $ligne, $c['identifiant_anonymat'] ?? '—');
        $sheet->setCellValue('B' . $ligne, $c['numero_copie']);
        $sheet->setCellValue('C' . $ligne, ucfirst($c['statut']));
        $sheet->setCellValue('D' . $ligne, $note);
    } else {
        $sheet->setCellValue('A' . $ligne, $c['matricule'] ?? '—');
        $sheet->setCellValue('B' . $ligne, $c['nom_etudiant'] ?? '—');
        $sheet->setCellValue('C' . $ligne, $c['prenom_etudiant'] ?? '—');
        $sheet->setCellValue('D' . $ligne, $c['numero_copie']);
        $sheet->setCellValue('E' . $ligne, ucfirst($c['statut']));
        $sheet->setCellValue('F' . $ligne, $note);
    }
    $ligne++;
}

// Auto-largeur des colonnes
foreach (range('A', chr(65 + count($entetes) - 1)) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Historiser
$stmtHist = $pdo->prepare("
    INSERT INTO historique (id_utilisateur, action, entite_type, entite_id, details)
    VALUES (?, 'export_resultats', 'qcm', ?, ?)
");
$stmtHist->execute([$_SESSION['user_id'], $qcm_id, json_encode(['format' => 'excel', 'mode' => $mode])]);

$nomFichier = 'resultats_' . preg_replace('/[^a-zA-Z0-9]/', '_', $qcm['titre']) . '_' . $mode . '_' . date('Ymd_His') . '.xlsx';

$stmtExport = $pdo->prepare("
    INSERT INTO exports (id_qcm, id_enseignant, format, mode, fichier_url, date_export)
    VALUES (?, ?, 'excel', ?, ?, NOW())
");
$stmtExport->execute([$qcm_id, $_SESSION['user_id'], $mode, 'uploads/export/' . $nomFichier]);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nomFichier . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');