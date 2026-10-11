<?php

function corrigerCopieAutomatiquement($pdo, $copie_id, $id_enseignant) {
    $stmt = $pdo->prepare("
        SELECT c.*, q.nb_questions, q.mode_identification, q.option_ne_sais_pas, q.option_aucune_reponse
        FROM copies c
        JOIN qcms q ON q.id = c.id_qcm
        WHERE c.id = ?
    ");
    $stmt->execute([$copie_id]);
    $copie = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$copie) {
        return ['success' => false, 'message' => 'Copie introuvable'];
    }

    $optionsLabels = ['A', 'B', 'C', 'D', 'E'];
    if ($copie['option_ne_sais_pas']) $optionsLabels[] = 'ARJ';
    if ($copie['option_aucune_reponse']) $optionsLabels[] = 'JNSP';

    $cheminImage = realpath(__DIR__ . '/../' . $copie['image_url']);
    if (!$cheminImage || !file_exists($cheminImage)) {
        return ['success' => false, 'message' => 'Image introuvable'];
    }

    $ch = curl_init('http://localhost:8001/process');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'file' => new CURLFile($cheminImage),
        'nb_questions' => $copie['nb_questions'],
        'options_labels' => json_encode($optionsLabels),
        'mode_identification' => $copie['mode_identification'],
    ]);
    $reponseOCR = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || !$reponseOCR) {
        return ['success' => false, 'message' => 'Service OCR indisponible : ' . $curlError];
    }

    $resultatOCR = json_decode($reponseOCR, true);
    if (!$resultatOCR || !($resultatOCR['success'] ?? false)) {
        return ['success' => false, 'message' => 'Échec du traitement OCR'];
    }

    // Extraire et enregistrer l'identification détectée par l'OCR
    $identificationBrute = $resultatOCR['identification_brute'] ?? '';

    if ($copie['mode_identification'] === 'anonymat') {
        preg_match('/[A-Z]\d{2,4}/', $identificationBrute, $matches);
        $codeAnonymat = $matches[0] ?? null;

        if ($codeAnonymat) {
            $stmtId = $pdo->prepare("UPDATE copies SET identifiant_anonymat = ? WHERE id = ?");
            $stmtId->execute([$codeAnonymat, $copie_id]);
        }
    } else {
        preg_match('/\b[0-9A-Z]{6,9}\b/', $identificationBrute, $matchesMatricule);
        $matricule = $matchesMatricule[0] ?? null;

        // Correction heuristique : le 3ème caractère doit être une lettre (format XX-Lettre-XXXX)
        if ($matricule && strlen($matricule) >= 7 && ctype_digit($matricule[2])) {
            if ($matricule[2] === '1') {
                $matricule = substr($matricule, 0, 2) . 'T' . substr($matricule, 3);
            }
        }

        preg_match('/Nom\s*:\s*([A-ZÀ-Ü]+)/u', $identificationBrute, $matchesNom);
        $nom = $matchesNom[1] ?? null;

        if ($matricule || $nom) {
            $stmtId = $pdo->prepare("
                UPDATE copies
                SET matricule = COALESCE(?, matricule),
                    nom_etudiant = COALESCE(?, nom_etudiant)
                WHERE id = ?
            ");
            $stmtId->execute([$matricule, $nom, $copie_id]);
        }
    }

    $stmt = $pdo->prepare("SELECT id FROM corriges WHERE id_qcm = ? AND actif = true LIMIT 1");
    $stmt->execute([$copie['id_qcm']]);
    $corrige_id = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT id, numero_question, points_bonne_reponse, points_malus, points_ne_sais_pas, points_aucune_reponse FROM questions WHERE id_qcm = ?");
    $stmt->execute([$copie['id_qcm']]);
    $questionsByNum = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $q) {
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

            $stmtInsertReponse->execute([$copie_id, $question['id'], json_encode($cochees), $typeReponse, $confidence]);

            $noteTotale += $points;
            $detailCalcul[] = [
                'question' => $numQuestion,
                'reponse_donnee' => $cochees,
                'bonne_reponse' => $bonnesReponses,
                'type' => $typeReponse,
                'points' => $points
            ];
        }

        $stmt = $pdo->prepare("
            INSERT INTO notes (id_copie, id_corrige, note_totale, detail_calcul, date_calcul, statut)
            VALUES (?, ?, ?, ?, NOW(), 'calculee')
            ON CONFLICT (id_copie) DO UPDATE
            SET note_totale = EXCLUDED.note_totale, detail_calcul = EXCLUDED.detail_calcul, date_calcul = NOW(), statut = 'recalculee'
        ");
        $stmt->execute([$copie_id, $corrige_id, $noteTotale, json_encode($detailCalcul)]);

        $stmt = $pdo->prepare("UPDATE copies SET statut = 'corrigee' WHERE id = ?");
        $stmt->execute([$copie_id]);

        $stmt = $pdo->prepare("
            INSERT INTO historique (id_utilisateur, action, entite_type, entite_id, details)
            VALUES (?, 'correction_automatique', 'copie', ?, ?)
        ");
        $stmt->execute([$id_enseignant, $copie_id, json_encode(['note' => $noteTotale, 'auto' => true])]);

        $pdo->commit();

        return ['success' => true, 'note' => $noteTotale];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}