<?php
header('Content-Type: application/json');
session_start();
require '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

$id_enseignant = $_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true);

$titre = $data['titre'] ?? null;
$matiere = $data['matiere'] ?? null;
$type_evaluation = $data['type_evaluation'] ?? null;
$nb_questions = $data['nb_questions'] ?? null;
$mode_identification = $data['mode_identification'] ?? null;
$mode_marquage = $data['mode_marquage'] ?? null;
$option_ne_sais_pas = $data['option_ne_sais_pas'] ?? true;
$option_aucune_reponse = $data['option_aucune_reponse'] ?? true;
$questions = $data['questions'] ?? [];

if (!$titre || !$matiere || !$nb_questions || empty($questions)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Champs manquants']);
    exit;
}

$afficher_cachet = (stripos($type_evaluation, 'normale') !== false);

$pdo = getConnection();

try {
    $pdo->beginTransaction();

    // 1. Créer le QCM — statut 'brouillon' tant que le corrigé n'est pas configuré
    $stmt = $pdo->prepare("
        INSERT INTO qcms (id_enseignant, titre, matiere, type_evaluation, nb_questions, mode_identification, mode_marquage, option_ne_sais_pas, option_aucune_reponse, afficher_cachet, statut)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'brouillon')
        RETURNING id
    ");
    $stmt->execute([
        $id_enseignant, $titre, $matiere, $type_evaluation, $nb_questions,
        $mode_identification, $mode_marquage,
        $option_ne_sais_pas ? 't' : 'f',
        $option_aucune_reponse ? 't' : 'f',
        $afficher_cachet ? 't' : 'f'
    ]);
    $qcm_id = $stmt->fetchColumn();

    // 2. Créer une première version du corrigé (vide, sera remplie plus tard)
    $stmt = $pdo->prepare("
        INSERT INTO corriges (id_qcm, version, actif, id_modifie_par)
        VALUES (?, 1, true, ?)
        RETURNING id
    ");
    $stmt->execute([$qcm_id, $id_enseignant]);
    // Pas besoin de garder $corrige_id ici, il sera récupéré plus tard

    // 3. Créer chaque question avec son barème, SANS bonnes réponses
    $stmtQuestion = $pdo->prepare("
        INSERT INTO questions (id_qcm, numero_question, points_bonne_reponse, points_malus, points_ne_sais_pas, points_aucune_reponse)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($questions as $q) {
        $stmtQuestion->execute([
            $qcm_id,
            $q['numero_question'],
            $q['points_bonne_reponse'],
            $q['points_malus'],
            $q['points_ne_sais_pas'],
            $q['points_aucune_reponse'],
        ]);
    }

    // 4. Historiser
    $stmt = $pdo->prepare("
        INSERT INTO historique (id_utilisateur, action, entite_type, entite_id, details)
        VALUES (?, 'creation_qcm', 'qcm', ?, ?)
    ");
    $stmt->execute([$id_enseignant, $qcm_id, json_encode(['titre' => $titre, 'nb_questions' => $nb_questions])]);

    $pdo->commit();

    echo json_encode(['success' => true, 'qcm_id' => $qcm_id]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la création : ' . $e->getMessage()]);
}