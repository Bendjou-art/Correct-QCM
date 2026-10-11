<?php
require '../includes/auth.php';
$user = requireAuth();

$qcmsListe = [
    ['id' => 1, 'titre' => 'Examen Final L3'],
    ['id' => 2, 'titre' => 'INFO301'],
    ['id' => 3, 'titre' => 'Contrôle continu L1'],
    ['id' => 5, 'titre' => 'Examen Rattrapage L2'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Statistiques — Tailor Correct</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/statistiques.css">
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
      <a class="active" href="statistiques.php"><span class="ic">📈</span> Statistiques</a>
    </nav>
    <div class="sidebar-footer">
      <a href="logout.php"><span class="ic">🚪</span> Déconnexion</a>
    </div>
  </aside>

  <main class="content">
    <header class="topbar">
      <div>
        <h1 class="page-title">Statistiques</h1>
        <p class="page-sub" id="page-sub-text">Vue détaillée par session</p>
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
      <select class="qcm-select" id="qcm-select">
        <?php foreach ($qcmsListe as $q): ?>
          <option value="<?= $q['id'] ?>"><?= htmlspecialchars($q['titre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <section class="stats-row">
      <div class="stat-card stat-blue">
        <div class="stat-top"><span class="stat-label">Copies numérisées</span><span class="stat-icon">📄</span></div>
        <div class="stat-value" id="stat-copies">—</div>
      </div>
      <div class="stat-card stat-green">
        <div class="stat-top"><span class="stat-label">Moyenne de la session</span><span class="stat-icon">🎯</span></div>
        <div class="stat-value" id="stat-moyenne">—</div>
      </div>
      <div class="stat-card stat-success">
        <div class="stat-top"><span class="stat-label">≥ 10/20 (réussite)</span><span class="stat-icon">✅</span></div>
        <div class="stat-value" id="stat-reussite">—</div>
      </div>
      <div class="stat-card stat-danger">
        <div class="stat-top"><span class="stat-label">&lt; 10/20 (échec)</span><span class="stat-icon">❌</span></div>
        <div class="stat-value" id="stat-echec">—</div>
      </div>
      <div class="stat-card stat-purple">
        <div class="stat-top"><span class="stat-label">Anomalies</span><span class="stat-icon">⚠️</span></div>
        <div class="stat-value" id="stat-anomalies">—</div>
      </div>
    </section>

    <section class="grid-row">
      <div class="panel">
        <div class="panel-header"><h3>Distribution des notes</h3></div>
        <div class="chart-wrap"><canvas id="chartDistribution"></canvas></div>
      </div>
      <div class="panel">
        <div class="panel-header"><h3>Réussite / Échec</h3></div>
        <div class="chart-wrap"><canvas id="chartReussite"></canvas></div>
      </div>
    </section>

    <section class="panel">
      <div class="panel-header"><h3>Questions les plus ratées de cette session</h3></div>
      <table class="mini-table">
        <thead>
          <tr><th>Question</th><th>Taux d'échec</th></tr>
        </thead>
        <tbody id="questions-ratees-body"></tbody>
      </table>
    </section>

  </main>
</div>

<script src="../assets/js/statistiques.js"></script>
</body>
</html>