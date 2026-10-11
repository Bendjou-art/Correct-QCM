<?php
require '../includes/auth.php';
require '../config/database.php';
$user = requireAuth();

$qcm_id = $_GET['id'] ?? null;
if (!$qcm_id) {
    header('Location: sessions.php');
    exit;
}

$pdo = getConnection();

$stmt = $pdo->prepare("SELECT * FROM qcms WHERE id = ? AND id_enseignant = ?");
$stmt->execute([$qcm_id, $user['id']]);
$qcm = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$qcm) {
    die('QCM introuvable ou accès non autorisé.');
}

// Récupérer les questions du QCM
$stmt = $pdo->prepare("SELECT * FROM questions WHERE id_qcm = ? ORDER BY numero_question");
$stmt->execute([$qcm_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer le corrigé actif et ses réponses déjà configurées (s'il y en a)
$stmt = $pdo->prepare("SELECT id FROM corriges WHERE id_qcm = ? AND actif = true LIMIT 1");
$stmt->execute([$qcm_id]);
$corrige_id = $stmt->fetchColumn();

$reponsesExistantes = [];
if ($corrige_id) {
    $stmt = $pdo->prepare("SELECT id_question, bonnes_reponses FROM corrige_reponses WHERE id_corrige = ?");
    $stmt->execute([$corrige_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $reponsesExistantes[$r['id_question']] = json_decode($r['bonnes_reponses'], true);
    }
}

$optionsLabels = ['A', 'B', 'C', 'D', 'E'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Configurer le corrigé — Tailor Correct</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/session-detail.css">
<link rel="stylesheet" href="../assets/css/session-create.css">
</head>
<body>

<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-logo">✂️ Tailor<span>Correct</span></div>
    <nav class="sidebar-nav">
      <a href="dashboard.php"><span class="ic">📊</span> Tableau de bord</a>
      <a href="sessions.php"><span class="ic">📁</span> Mes QCM</a>
      <a href="session-create.php"><span class="ic">➕</span> Créer un QCM</a>
      <a href="copies.php"><span class="ic">📄</span> Copies & notes</a>
      <a href="anomalies.php"><span class="ic">⚠️</span> Anomalies</a>
      <a href="statistiques.php"><span class="ic">📈</span> Statistiques</a>
    </nav>
    <div class="sidebar-footer">
      <a href="logout.php"><span class="ic">🚪</span> Déconnexion</a>
    </div>
  </aside>

  <main class="content">
    <a href="session-detail.php?id=<?= $qcm_id ?>" class="back-link">← Retour au QCM</a>

    <header class="topbar">
      <div>
        <h1 class="page-title">Configurer le corrigé</h1>
        <p class="page-sub"><?= htmlspecialchars($qcm['titre']) ?> · <?= htmlspecialchars($qcm['matiere']) ?> · <?= count($questions) ?> questions</p>
      </div>
      <div class="user-chip">
        <div class="avatar"><?= strtoupper(substr($user['prenom'],0,1) . substr($user['nom'],0,1)) ?></div>
        <div>
          <div class="user-name"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></div>
          <div class="user-role">Enseignant</div>
        </div>
      </div>
    </header>

    <?php if (empty($reponsesExistantes)): ?>
      <div class="alert-banner">
        ⚠️ Ce corrigé n'a pas encore été configuré. Cochez la ou les bonnes réponses pour chaque question ci-dessous. Tant que ce n'est pas fait, les copies numérisées ne pourront pas être corrigées automatiquement.
      </div>
    <?php endif; ?>

    <form id="corrige-form">
      <input type="hidden" id="qcm_id" value="<?= $qcm_id ?>">

      <section class="panel">
        <div class="panel-header"><h3>Bonnes réponses</h3></div>
        <p class="hint">Cochez une ou plusieurs cases par question. Laissez tout décoché si aucune des réponses proposées n'est juste.</p>

        <div id="questions-container">
          <?php foreach ($questions as $q): ?>
            <?php $dejaCoche = $reponsesExistantes[$q['id']] ?? []; ?>
            <div class="question-row" data-question-id="<?= $q['id'] ?>" data-numero="<?= $q['numero_question'] ?>">
              <div class="question-row-header">
                <h4>Question <?= $q['numero_question'] ?></h4>
                <label class="option-aucune-juste">
                  <input type="checkbox" class="q-aucune-juste" <?= empty($dejaCoche) && !empty($reponsesExistantes) ? 'checked' : '' ?>>
                  Aucune des réponses proposées n'est juste
                </label>
              </div>
              <div class="options-row">
                <?php foreach ($optionsLabels as $opt): ?>
                  <label class="option-check">
                    <input type="checkbox" class="q-reponse" value="<?= $opt ?>" <?= in_array($opt, $dejaCoche) ? 'checked' : '' ?>>
                    <span><?= $opt ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <div class="form-actions">
        <button type="button" class="btn-outline" onclick="window.location.href='session-detail.php?id=<?= $qcm_id ?>'">Annuler</button>
        <button type="submit" class="btn-primary">Enregistrer le corrigé</button>
      </div>

      <p id="form-error" class="error-msg"></p>
      <p id="form-success" class="success-msg"></p>
    </form>

  </main>
</div>

<script src="../assets/js/configurer-corrige.js"></script>
</body>
</html>