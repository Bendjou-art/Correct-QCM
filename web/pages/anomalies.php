<?php
require '../includes/auth.php';
$user = requireAuth();

// Données factices — à remplacer par une vraie requête PDO plus tard
$anomalies = [
    [
        'id' => 1,
        'copie_id' => 2,
        'copie' => '21T1198 - ETOUNDI Paul',
        'qcm' => 'Examen Final L3',
        'question' => 5,
        'type' => 'marquage_non_conforme',
        'description' => 'Croix détectée au lieu d\'une case noircie',
        'statut' => 'signalee',
        'date' => '02 sept. 2026, 14:35',
    ],
    [
        'id' => 2,
        'copie_id' => 5,
        'copie' => '20T0765 - BELLA Ruth',
        'qcm' => 'INFO301',
        'question' => 12,
        'type' => 'signe_distinctif',
        'description' => 'Annotation manuscrite détectée près de la case B',
        'statut' => 'signalee',
        'date' => '28 août 2026, 09:12',
    ],
    [
        'id' => 3,
        'copie_id' => 5,
        'copie' => '20T0765 - BELLA Ruth',
        'qcm' => 'INFO301',
        'question' => 18,
        'type' => 'marques_multiples_non_prevues',
        'description' => '3 cases cochées alors que la question n\'accepte qu\'une seule réponse',
        'statut' => 'examinee',
        'date' => '28 août 2026, 09:12',
    ],
    [
        'id' => 4,
        'copie_id' => 1,
        'copie' => '21T1234 - MBALLA Sarah',
        'qcm' => 'Examen Final L3',
        'question' => 7,
        'type' => 'reponse_illisible',
        'description' => 'Zone de réponse partiellement effacée ou tachée',
        'statut' => 'ignoree',
        'date' => '02 sept. 2026, 14:30',
    ],
];

$typeLabels = [
    'marquage_non_conforme' => 'Marquage non conforme',
    'reponse_illisible' => 'Réponse illisible',
    'signe_distinctif' => 'Signe distinctif',
    'marques_multiples_non_prevues' => 'Marques multiples',
    'suspicion_fraude' => 'Suspicion de fraude',
];

$statutLabels = [
    'signalee' => ['label' => 'À examiner', 'class' => 'st-signalee'],
    'examinee' => ['label' => 'Examinée', 'class' => 'st-examinee'],
    'ignoree' => ['label' => 'Ignorée', 'class' => 'st-ignoree'],
];

$nbSignalees = count(array_filter($anomalies, fn($a) => $a['statut'] === 'signalee'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Anomalies — Tailor Correct</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/sessions.css">
<link rel="stylesheet" href="../assets/css/anomalies.css">
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
      <a class="active" href="anomalies.php"><span class="ic">⚠️</span> Anomalies</a>
      <a href="statistiques.php"><span class="ic">📈</span> Statistiques</a>
    </nav>
    <div class="sidebar-footer">
      <a href="logout.php"><span class="ic">🚪</span> Déconnexion</a>
    </div>
  </aside>

  <main class="content">
    <header class="topbar">
      <div>
        <h1 class="page-title">Anomalies détectées</h1>
        <p class="page-sub"><?= $nbSignalees ?> anomalie<?= $nbSignalees > 1 ? 's' : '' ?> à examiner sur <?= count($anomalies) ?> au total</p>
      </div>
      <div class="user-chip">
        <div class="avatar"><?= strtoupper(substr($user['prenom'],0,1) . substr($user['nom'],0,1)) ?></div>
        <div>
          <div class="user-name"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></div>
          <div class="user-role">Enseignant</div>
        </div>
      </div>
    </header>

    <?php if ($nbSignalees > 0): ?>
      <div class="alert-banner">
        ⚠️ <strong><?= $nbSignalees ?> anomalie<?= $nbSignalees > 1 ? 's' : '' ?></strong> nécessite<?= $nbSignalees > 1 ? 'nt' : '' ?> votre attention. Ces signalements ne sanctionnent pas automatiquement l'étudiant — examinez chaque cas avant de décider.
      </div>
    <?php endif; ?>

    <div class="toolbar">
      <div class="search-box">
        <input type="text" id="search-input" placeholder="Rechercher par copie, QCM ou question...">
      </div>
      <div class="filter-tabs">
        <button class="filter-tab active" data-filter="tous">Toutes</button>
        <button class="filter-tab" data-filter="signalee">À examiner</button>
        <button class="filter-tab" data-filter="examinee">Examinées</button>
        <button class="filter-tab" data-filter="ignoree">Ignorées</button>
      </div>
    </div>

    <section class="panel">
      <table class="mini-table" id="anomalies-table">
        <thead>
          <tr>
            <th>Copie</th>
            <th>QCM</th>
            <th>Question</th>
            <th>Type</th>
            <th>Description</th>
            <th>Date</th>
            <th>Statut</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($anomalies)): ?>
            <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:30px;">
              Aucune anomalie détectée pour l'instant. 🎉
            </td></tr>
          <?php else: ?>
            <?php foreach ($anomalies as $a): ?>
              <?php $st = $statutLabels[$a['statut']]; ?>
              <tr class="anomalie-row"
                  data-statut="<?= $a['statut'] ?>"
                  data-search="<?= strtolower($a['copie'] . ' ' . $a['qcm']) ?>">
                <td><a href="copie-detail.php?id=<?= $a['copie_id'] ?>" class="copie-link"><?= htmlspecialchars($a['copie']) ?></a></td>
                <td><?= htmlspecialchars($a['qcm']) ?></td>
                <td><strong>Q<?= $a['question'] ?></strong></td>
                <td><span class="type-badge"><?= $typeLabels[$a['type']] ?? $a['type'] ?></span></td>
                <td class="desc-cell"><?= htmlspecialchars($a['description']) ?></td>
                <td class="date-cell"><?= $a['date'] ?></td>
                <td><span class="st-badge <?= $st['class'] ?>"><?= $st['label'] ?></span></td>
                <td>
                  <?php if ($a['statut'] === 'signalee'): ?>
                    <div class="row-actions">
                      <button class="btn-mini" data-action="examiner" data-id="<?= $a['id'] ?>">Examiner</button>
                      <button class="btn-mini btn-mini-ghost" data-action="ignorer" data-id="<?= $a['id'] ?>">Ignorer</button>
                    </div>
                  <?php else: ?>
                    <a href="copie-detail.php?id=<?= $a['copie_id'] ?>" class="icon-btn">Voir</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
      <p id="no-results" class="no-results" style="display:none;">Aucune anomalie ne correspond à votre recherche.</p>
    </section>

  </main>
</div>

<script src="../assets/js/anomalies.js"></script>
</body>
</html>