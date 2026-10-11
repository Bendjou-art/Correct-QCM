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

$stmt = $pdo->prepare("SELECT * FROM questions WHERE id_qcm = ? ORDER BY numero_question");
$stmt->execute([$qcm_id]);
$questionsDB = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer le corrigé actif et ses bonnes réponses
$stmt = $pdo->prepare("SELECT id FROM corriges WHERE id_qcm = ? AND actif = true LIMIT 1");
$stmt->execute([$qcm_id]);
$corrige_id = $stmt->fetchColumn();

$questions = [];
foreach ($questionsDB as $q) {
  $stmt2 = $pdo->prepare("SELECT bonnes_reponses FROM corrige_reponses WHERE id_corrige = ? AND id_question = ?");
  $stmt2->execute([$corrige_id, $q['id']]);
  $bonnesReponses = json_decode($stmt2->fetchColumn() ?: '[]', true);

  $questions[] = [
    'numero' => $q['numero_question'],
    'enonce' => $q['enonce'] ?: '(énoncé non renseigné)',
    'bonnes_reponses' => $bonnesReponses,
    'points' => $q['points_bonne_reponse'],
  ];
}

// Copies liées à ce QCM
$stmt = $pdo->prepare("SELECT c.*, n.note_totale FROM copies c LEFT JOIN notes n ON n.id_copie = c.id WHERE c.id_qcm = ?");
$stmt->execute([$qcm_id]);
$copiesDB = $stmt->fetchAll(PDO::FETCH_ASSOC);

$copies = [];
foreach ($copiesDB as $c) {
  $identifiant = $c['matricule']
    ? $c['matricule'] . ' - ' . $c['nom_etudiant'] . ' ' . $c['prenom_etudiant']
    : ($c['identifiant_anonymat'] ?? 'Inconnu');

  $copies[] = [
    'id' => $c['id'],
    'identifiant' => $identifiant,
    'note' => $c['note_totale'],
    'statut' => $c['statut'],
    'anomalie' => false, // à connecter à la table anomalies plus tard
  ];
}

$modeMarquageLabels = [
  'case_noircie' => 'Case noircie',
  'croix' => 'Croix',
  'coche' => 'Coche',
  'autre' => 'Autre signe',
];
$modeIdentLabels = [
  'matricule' => 'Matricule + Nom',
  'anonymat' => 'Anonymat (code)',
];

$statutLabels = [
  'brouillon' => ['label' => 'Brouillon', 'class' => 'badge-draft'],
  'pret' => ['label' => 'Prêt', 'class' => 'badge-active'],
  'en_correction' => ['label' => 'En correction', 'class' => 'badge-progress'],
  'cloture' => ['label' => 'Clôturé', 'class' => 'badge-closed'],
];
$statutActuel = $statutLabels[$qcm['statut']] ?? ['label' => $qcm['statut'], 'class' => 'badge-active'];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($qcm['titre']) ?> — Tailor Correct</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <link rel="stylesheet" href="../assets/css/sessions.css">
  <link rel="stylesheet" href="../assets/css/session-detail.css">
</head>

<body>

  <div class="layout">
    <aside class="sidebar">
      <div class="sidebar-logo">✂️ Tailor<span>Correct</span></div>
      <nav class="sidebar-nav">
        <a href="dashboard.php"><span class="ic">📊</span> Tableau de bord</a>
        <a class="active" href="sessions.php"><span class="ic">📁</span> Mes QCM</a>
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
      <a href="sessions.php" class="back-link">← Retour à Mes QCM</a>

      <header class="topbar">
        <div>
          <h1 class="page-title"><?= htmlspecialchars($qcm['titre']) ?></h1>
          <p class="page-sub"><?= htmlspecialchars($qcm['matiere']) ?> · Créé le
            <?= date('d/m/Y', strtotime($qcm['created_at'])) ?>
          </p>
        </div>
        <div class="user-chip">
          <div class="avatar"><?= strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1)) ?></div>
          <div>
            <div class="user-name"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></div>
            <div class="user-role">Enseignant</div>
          </div>
        </div>
      </header>

      <!-- Infos rapides -->
      <section class="info-row">
        <div class="info-chip"><span class="info-label">Statut</span><span
            class="badge <?= $statutActuel['class'] ?>"><?= $statutActuel['label'] ?></span></div>
        <div class="info-chip"><span class="info-label">Questions</span><span
            class="info-value"><?= $qcm['nb_questions'] ?></span></div>
        <div class="info-chip"><span class="info-label">Identification</span><span
            class="info-value"><?= $modeIdentLabels[$qcm['mode_identification']] ?? $qcm['mode_identification'] ?></span>
        </div>
        <div class="info-chip"><span class="info-label">Marquage</span><span
            class="info-value"><?= $modeMarquageLabels[$qcm['mode_marquage']] ?? '—' ?></span></div>
        <div class="info-chip"><span class="info-label">Copies reçues</span><span
            class="info-value"><?= count($copies) ?></span></div>
      </section>

      <!-- Actions principales -->
      <section class="actions-row">
        <a href="numerisation.php?id=<?= $qcm['id'] ?>" class="btn-primary"
          style="text-decoration:none;display:inline-block;">📷 Numériser des copies</a>
        <a href="../api/qcms/formulaire-pdf.php?id=<?= $qcm['id'] ?>" target="_blank" class="btn-outline"
          style="text-decoration:none;display:inline-block;">📄 Générer le formulaire (PDF)</a>

        <?php if (stripos($qcm['type_evaluation'] ?? '', 'continu') !== false): ?>
          <a href="import-etudiants.php?id=<?= $qcm['id'] ?>" class="btn-outline"
            style="text-decoration:none;display:inline-block;">👥 Importer la liste des étudiants</a>
        <?php endif; ?>
        
        <?php if ($qcm['statut'] === 'brouillon'): ?>
          <a href="configurer-corrige.php?id=<?= $qcm['id'] ?>" class="btn-primary"
            style="text-decoration:none;display:inline-block;background:var(--yellow);">⚠️ Configurer le corrigé</a>
        <?php else: ?>
          <a href="configurer-corrige.php?id=<?= $qcm['id'] ?>" class="btn-outline"
            style="text-decoration:none;display:inline-block;">✏️ Modifier le corrigé</a>
        <?php endif; ?>

        <div class="dropdown-export">
          <button class="btn-outline" id="btn-export-toggle">⬇ Exporter les résultats</button>
          <div class="dropdown-menu" id="export-menu" style="display:none;">
            <a href="../api/export/csv.php?qcm_id=<?= $qcm['id'] ?>&mode=nominatif">CSV — Nominatif</a>
            <a href="../api/export/csv.php?qcm_id=<?= $qcm['id'] ?>&mode=anonymise">CSV — Anonymisé</a>
            <a href="../api/export/excel.php?qcm_id=<?= $qcm['id'] ?>&mode=nominatif">Excel — Nominatif</a>
            <a href="../api/export/excel.php?qcm_id=<?= $qcm['id'] ?>&mode=anonymise">Excel — Anonymisé</a>
          </div>
        </div>
      </section>

      <!-- Onglets -->
      <div class="tabs">
        <button class="tab active" data-tab="questions">Questions & corrigé</button>
        <button class="tab" data-tab="copies">Copies numérisées (<?= count($copies) ?>)</button>
      </div>

      <!-- Onglet Questions -->
      <section class="panel tab-content" id="tab-questions">
        <?php if (empty($questions)): ?>
          <p style="text-align:center;color:var(--muted);padding:30px;">Aucune question trouvée pour ce QCM.</p>
        <?php else: ?>
          <?php foreach ($questions as $q): ?>
            <div class="question-view">
              <div class="question-view-header">
                <span class="question-number">Q<?= $q['numero'] ?></span>
                <span class="question-text"><?= htmlspecialchars($q['enonce']) ?></span>
                <span class="question-points"><?= $q['points'] ?> pt<?= $q['points'] > 1 ? 's' : '' ?></span>
              </div>
              <div class="question-options">
                <?php foreach (['A', 'B', 'C', 'D', 'E'] as $opt): ?>
                  <span class="opt-badge <?= in_array($opt, $q['bonnes_reponses']) ? 'opt-correct' : '' ?>">
                    <?= $opt ?>      <?= in_array($opt, $q['bonnes_reponses']) ? ' ✓' : '' ?>
                  </span>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>

      <!-- Onglet Copies -->
      <section class="panel tab-content" id="tab-copies" style="display:none;">
        <table class="mini-table" id="copies-table">
          <thead>
            <tr>
              <th>Copie</th>
              <th>Statut</th>
              <th>Note</th>
              <th>Anomalie</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($copies)): ?>
              <tr>
                <td colspan="5" style="text-align:center;color:var(--muted);padding:30px;">
                  Aucune copie numérisée pour l'instant.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($copies as $copie): ?>
                <tr>
                  <td><?= htmlspecialchars($copie['identifiant']) ?></td>
                  <td>
                    <?php if ($copie['statut'] !== 'corrigee'): ?>
                      <button class="icon-btn btn-corriger" data-id="<?= $copie['id'] ?>">Corriger</button>
                    <?php endif; ?>
                  </td>
                  <td><?= $copie['note'] !== null ? $copie['note'] . '/20' : '—' ?></td>
                  <td><?= $copie['anomalie'] ? '<span class="badge badge-anomalie">⚠️ Détectée</span>' : '—' ?></td>
                  <td><a href="copie-detail.php?id=<?= $copie['id'] ?>" class="icon-btn">Voir</a></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </section>

    </main>
  </div>

  <script src="../assets/js/session-detail.js"></script>
</body>

</html>