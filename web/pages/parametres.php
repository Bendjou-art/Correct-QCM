<?php
require '../includes/auth.php';
require '../config/database.php';
$user = requireAuth();

$pdo = getConnection();
$stmt = $pdo->prepare("SELECT cachet_url FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$cachetActuel = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Paramètres — Tailor Correct</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/session-detail.css">
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
    <h1 class="page-title">Paramètres</h1>
    <p class="page-sub">Gérer votre cachet administratif</p>

    <section class="panel" style="max-width:500px;margin-top:20px;">
      <div class="panel-header"><h3>Cachet administratif</h3></div>
      <p style="font-size:0.85rem;color:var(--muted);margin-bottom:14px;">
        Ce cachet sera automatiquement affiché sur les formulaires en mode Session Normale. Il ne sera jamais affiché en mode Contrôle Continu.
      </p>

      <?php if ($cachetActuel): ?>
        <div style="margin-bottom:14px;">
          <img src="../<?= htmlspecialchars($cachetActuel) ?>" alt="Cachet actuel" style="max-width:150px;border:1px solid var(--border);border-radius:8px;">
        </div>
      <?php endif; ?>

      <form id="cachet-form">
        <input type="file" id="cachet-file" accept="image/png,image/jpeg" required>
        <button type="submit" class="btn-primary" style="margin-top:14px;">Enregistrer le cachet</button>
      </form>
      <p id="cachet-msg" style="margin-top:10px;font-size:0.85rem;"></p>
    </section>
  </main>
</div>

<script src="../assets/js/parametres.js"></script>
</body>
</html>