<?php
require '../includes/auth.php';
$user = requireAuth();

// Données factices — à remplacer par une vraie requête PDO plus tard
$mesQcms = [
    ['id' => 1, 'titre' => 'Examen Final L3', 'matiere' => 'Mathématiques', 'nb_questions' => 40, 'nb_copies' => 45, 'statut' => 'pret', 'date' => '02 sept. 2026', 'couleur' => '#2E3A46'],
    ['id' => 2, 'titre' => 'INFO301', 'matiere' => 'Programmation Web', 'nb_questions' => 30, 'nb_copies' => 31, 'statut' => 'en_correction', 'date' => '28 août 2026', 'couleur' => '#1E7A5C'],
    ['id' => 3, 'titre' => 'Contrôle continu L1', 'matiere' => 'Algorithmique', 'nb_questions' => 20, 'nb_copies' => 60, 'statut' => 'cloture', 'date' => '15 août 2026', 'couleur' => '#6C5DD3'],
    ['id' => 4, 'titre' => 'TD Réseaux', 'matiere' => 'Systèmes & Réseaux', 'nb_questions' => 25, 'nb_copies' => 0, 'statut' => 'brouillon', 'date' => '01 sept. 2026', 'couleur' => '#3F8CFF'],
    ['id' => 5, 'titre' => 'Examen Rattrapage L2', 'matiere' => 'Bases de données', 'nb_questions' => 35, 'nb_copies' => 22, 'statut' => 'cloture', 'date' => '10 août 2026', 'couleur' => '#E0A94D'],
];

$badgeMap = [
    'pret' => ['class' => 'badge-active', 'label' => 'Prêt'],
    'en_correction' => ['class' => 'badge-progress', 'label' => 'En correction'],
    'cloture' => ['class' => 'badge-closed', 'label' => 'Clôturé'],
    'brouillon' => ['class' => 'badge-draft', 'label' => 'Brouillon'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mes QCM — Tailor Correct</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/sessions.css">
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
    <header class="topbar">
      <div>
        <h1 class="page-title">Mes QCM</h1>
        <p class="page-sub"><?= count($mesQcms) ?> QCM créés au total</p>
      </div>
      <div class="user-chip">
        <div class="avatar"><?= strtoupper(substr($user['prenom'],0,1) . substr($user['nom'],0,1)) ?></div>
        <div>
          <div class="user-name"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></div>
          <div class="user-role">Enseignant</div>
        </div>
      </div>
    </header>

    <div class="toolbar">
      <div class="search-box">
        <input type="text" id="search-input" placeholder="Rechercher un QCM par titre ou matière...">
      </div>
      <div class="filter-tabs">
        <button class="filter-tab active" data-filter="tous">Tous</button>
        <button class="filter-tab" data-filter="pret">Prêts</button>
        <button class="filter-tab" data-filter="en_correction">En correction</button>
        <button class="filter-tab" data-filter="cloture">Clôturés</button>
        <button class="filter-tab" data-filter="brouillon">Brouillons</button>
      </div>
      <a href="session-create.php" class="btn-primary">+ Créer un QCM</a>
    </div>

    <section class="panel">
      <table class="mini-table" id="qcm-table">
        <thead>
          <tr>
            <th>QCM</th>
            <th>Matière</th>
            <th>Questions</th>
            <th>Copies</th>
            <th>Date</th>
            <th>Statut</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($mesQcms)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:30px;">
              Aucun QCM créé pour l'instant. <a href="session-create.php" style="color:var(--purple);">Créer votre premier QCM</a>
            </td></tr>
          <?php else: ?>
            <?php foreach ($mesQcms as $qcm): ?>
              <?php $badge = $badgeMap[$qcm['statut']] ?? ['class' => 'badge-active', 'label' => $qcm['statut']]; ?>
              <tr class="qcm-row" data-statut="<?= $qcm['statut'] ?>" data-search="<?= strtolower($qcm['titre'] . ' ' . $qcm['matiere']) ?>">
                <td>
                  <div class="qcm-name">
                    <span class="qcm-dot" style="background:<?= $qcm['couleur'] ?>"></span>
                    <a href="session-detail.php?id=<?= $qcm['id'] ?>"><?= htmlspecialchars($qcm['titre']) ?></a>
                  </div>
                </td>
                <td><?= htmlspecialchars($qcm['matiere']) ?></td>
                <td><?= $qcm['nb_questions'] ?></td>
                <td><?= $qcm['nb_copies'] ?></td>
                <td><?= $qcm['date'] ?></td>
                <td><span class="badge <?= $badge['class'] ?>"><?= $badge['label'] ?></span></td>
                <td>
                  <a href="session-detail.php?id=<?= $qcm['id'] ?>" class="icon-btn">Ouvrir</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
      <p id="no-results" class="no-results" style="display:none;">Aucun QCM ne correspond à votre recherche.</p>
    </section>

  </main>
</div>

<script src="../assets/js/sessions.js"></script>
</body>
</html>