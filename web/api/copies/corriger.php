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
$copie_id = $data['copie_id'] ?? null;

if (!$copie_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'copie_id manquant']);
    exit;
}

$pdo = getConnection();

// 1. Récupérer la copie et son QCM
$stmt = $pdo->prepare("
    SELECT c.*, q.nb_questions, q.mode_identification, q.option_ne_sais_pas, q.option_aucune_reponse, q.id_enseignant
    FROM copies c
    JOIN qcms q ON q.id = c.id_qcm
    WHERE c.id = ?
");
$stmt->execute([$copie_id]);
$copie = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$copie || $copie['id_enseignant'] !== $_SESSION['user_id']) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Copie introuvable']);
    exit;
}

// 2. Construire la liste des options
$optionsLabels = ['A', 'B', 'C', 'D', 'E'];
if ($copie['option_ne_sais_pas']) $optionsLabels[] = 'JNSP';
if ($copie['option_aucune_reponse']) $optionsLabels[] = 'ARJ';

// 3. Appeler le microservice OCR
$cheminImage = realpath('../../' . $copie['image_url']);
if (!$cheminImage || !file_exists($cheminImage)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Image introuvable sur le serveur']);
    exit;
}

$ch = curl_init('http://localhost:8001/process');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'file' => new CURLFile($cheminImage),
    'nb_questions' => $copie['nb_questions'],
    'options_labels' => json_encode($optionsLabels),
    'mode_identification' => $copie['mode_identification'],
]);
$reponseOCR = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

file_put_contents('debug_ocr.log', $reponseOCR . "\n---\n", FILE_APPEND);

if ($curlError || !$reponseOCR) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de communication avec le service OCR : ' . $curlError]);
    exit;
}

$resultatOCR = json_decode($reponseOCR, true);
if (!$resultatOCR || !($resultatOCR['success'] ?? false)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Échec du traitement OCR']);
    exit;
}

// 4. Récupérer le corrigé actif et le barème
$stmt = $pdo->prepare("SELECT id FROM corriges WHERE id_qcm = ? AND actif = true LIMIT 1");
$stmt->execute([$copie['id_qcm']]);
$corrige_id = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT id, numero_question, points_bonne_reponse, points_malus, points_ne_sais_pas, points_aucune_reponse FROM questions WHERE id_qcm = ?");
$stmt->execute([$copie['id_qcm']]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
$questionsByNum = [];
foreach ($questions as $q) {
    $questionsByNum[$q['numero_question']] = $q;
}

$stmt = $pdo->prepare("SELECT id_question, bonnes_reponses FROM corrige_reponses WHERE id_corrige = ?");
$stmt->execute([$corrige_id]);
$corrigeByQuestionId = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $cr) {
    $corrigeByQuestionId[$cr['id_question']] = json_decode($cr['bonnes_reponses'], true);
}

try {
    $pdo->beginTransaction();

    $stmtInsertReponse = $pdo->prepare("
        INSERT INTO reponses_detectees (id_copie, id_question, reponses_cochees, type_reponse, confidence_score)
        VALUES (?, ?, ?, ?, ?)
        ON CONFLICT (id_copie, id_question) DO UPDATE
        SET reponses_cochees = EXCLUDED.reponses_cochees, type_reponse = EXCLUDED.type_reponse, confidence_score = EXCLUDED.confidence_score
    ");

    $noteTotale = 0;
    $detailCalcul = [];

    foreach ($resultatOCR['reponses'] as $rep) {
        $numQuestion = $rep['numero_question'];
        $cochees = $rep['reponses_cochees'];
        $confidence = $rep['confidence_score'] ?? 0;

        if (!isset($questionsByNum[$numQuestion])) continue;
        $question = $questionsByNum[$numQuestion];
        $bonnesReponses = $corrigeByQuestionId[$question['id']] ?? [];

        // Déterminer le type de réponse
        if (in_array('JNSP', $cochees)) {
            $typeReponse = 'ne_sais_pas';
            $points = $question['points_ne_sais_pas'];
        } elseif (in_array('ARJ', $cochees) || empty($cochees)) {
            $typeReponse = empty($cochees) ? 'vide' : 'aucune_reponse';
            $points = $question['points_aucune_reponse'];
        } else {
            $typeReponse = 'reponse';
            $reponsesLettres = array_diff($cochees, ['JNSP', 'ARJ']);
            $estCorrecte = (count($reponsesLettres) === count($bonnesReponses))
                && (count(array_diff($reponsesLettres, $bonnesReponses)) === 0);
            $points = $estCorrecte ? $question['points_bonne_reponse'] : $question['points_malus'];
        }

        $stmtInsertReponse->execute([
            $copie_id,
            $question['id'],
            json_encode($cochees),
            $typeReponse,
            $confidence
        ]);

        $noteTotale += $points;
        $detailCalcul[] = [
            'question' => $numQuestion,
            'reponse_donnee' => $cochees,
            'bonne_reponse' => $bonnesReponses,
            'type' => $typeReponse,
            'points' => $points
        ];
    }

    // 5. Enregistrer la note
    $stmt = $pdo->prepare("
        INSERT INTO notes (id_copie, id_corrige, note_totale, detail_calcul, date_calcul, statut)
        VALUES (?, ?, ?, ?, NOW(), 'calculee')
        ON CONFLICT (id_copie) DO UPDATE
        SET note_totale = EXCLUDED.note_totale, detail_calcul = EXCLUDED.detail_calcul, date_calcul = NOW(), statut = 'recalculee'
    ");
    $stmt->execute([$copie_id, $corrige_id, $noteTotale, json_encode($detailCalcul)]);

    // 6. Mettre à jour le statut de la copie
    $stmt = $pdo->prepare("UPDATE copies SET statut = 'corrigee' WHERE id = ?");
    $stmt->execute([$copie_id]);

    // 7. Historiser
    $stmt = $pdo->prepare("
        INSERT INTO historique (id_utilisateur, action, entite_type, entite_id, details)
        VALUES (?, 'correction_automatique', 'copie', ?, ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $copie_id, json_encode(['note' => $noteTotale])]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'note' => $noteTotale,
        'identification_brute' => $resultatOCR['identification_brute'] ?? ''
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}

