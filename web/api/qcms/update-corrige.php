<?php
header('Content-Type: application/json');
session_start();
require '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$qcm_id = $data['qcm_id'] ?? null;
$questions = $data['questions'] ?? [];

if (!$qcm_id || empty($questions)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

$pdo = getConnection();

// Vérifier que le QCM appartient bien à l'enseignant connecté
$stmt = $pdo->prepare("SELECT id, statut FROM qcms WHERE id = ? AND id_enseignant = ?");
$stmt->execute([$qcm_id, $_SESSION['user_id']]);
$qcm = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$qcm) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Récupérer le corrigé actif
    $stmt = $pdo->prepare("SELECT id FROM corriges WHERE id_qcm = ? AND actif = true LIMIT 1");
    $stmt->execute([$qcm_id]);
    $corrige_id = $stmt->fetchColumn();

    $estPremiereConfiguration = ($qcm['statut'] === 'brouillon');

    if (!$estPremiereConfiguration) {
        // Ce n'est pas la première fois : on versionne le corrigé (historique)
        $stmt = $pdo->prepare("UPDATE corriges SET actif = false WHERE id = ?");
        $stmt->execute([$corrige_id]);

        $stmt = $pdo->prepare("SELECT MAX(version) FROM corriges WHERE id_qcm = ?");
        $stmt->execute([$qcm_id]);
        $derniereVersion = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO corriges (id_qcm, version, actif, id_modifie_par, motif_modification)
            VALUES (?, ?, true, ?, 'Modification du corrigé')
            RETURNING id
        ");
        $stmt->execute([$qcm_id, $derniereVersion + 1, $_SESSION['user_id']]);
        $corrige_id = $stmt->fetchColumn();
    }

    // Insérer les nouvelles réponses du corrigé
    $stmtInsert = $pdo->prepare("
        INSERT INTO corrige_reponses (id_corrige, id_question, bonnes_reponses)
        VALUES (?, ?, ?)
    ");

    foreach ($questions as $q) {
        $stmtInsert->execute([
            $corrige_id,
            $q['id_question'],
            json_encode($q['bonnes_reponses'])
        ]);
    }

    // Passer le QCM au statut 'pret'
    $stmt = $pdo->prepare("UPDATE qcms SET statut = 'pret' WHERE id = ?");
    $stmt->execute([$qcm_id]);

    // Historiser
    $stmt = $pdo->prepare("
        INSERT INTO historique (id_utilisateur, action, entite_type, entite_id, details)
        VALUES (?, ?, 'qcm', ?, ?)
    ");
    $action = $estPremiereConfiguration ? 'configuration_corrige' : 'modification_corrige';
    $stmt->execute([$_SESSION['user_id'], $action, $qcm_id, json_encode(['nb_questions' => count($questions)])]);

    // Si ce n'était pas la première configuration, il faudrait recalculer les notes existantes
    // (fonctionnalité de recalcul à implémenter séparément)

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}