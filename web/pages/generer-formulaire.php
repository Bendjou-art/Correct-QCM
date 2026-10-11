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
$stmt = $pdo->prepare("SELECT titre, matiere, cachet_url FROM qcms q JOIN users u ON u.id = q.id_enseignant WHERE q.id = ? AND q.id_enseignant = ?");
$stmt->execute([$qcm_id, $user['id']]);
$qcm = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$qcm) {
    die('QCM introuvable');
}

$stmt = $pdo->prepare("SELECT cachet_url FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$cachetExistant = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Générer le formulaire — Tailor Correct</title>
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
    <a href="session-detail.php?id=<?= $qcm_id ?>" class="back-link">← Retour au QCM</a>

    <h1 class="page-title">Générer le formulaire</h1>
    <p class="page-sub"><?= htmlspecialchars($qcm['titre']) ?> · <?= htmlspecialchars($qcm['matiere']) ?></p>

    <section class="panel" style="max-width:500px;margin-top:20px;">
      <div class="panel-header"><h3>Cachet administratif (optionnel)</h3></div>
      <p style="font-size:0.85rem;color:var(--muted);margin-bottom:14px;">
        Ce cachet sera inséré dans le cadre "Ne rien écrire" du formulaire, si le mode d'évaluation est Session Normale.
      </p>

      <?php if ($cachetExistant): ?>
        <div style="margin-bottom:14px;">
          <p style="font-size:0.8rem;color:var(--muted);margin-bottom:6px;">Cachet actuellement enregistré :</p>
          <img src="../<?= htmlspecialchars($cachetExistant) ?>" alt="Cachet" style="max-width:120px;border:1px solid var(--border);border-radius:8px;">
        </div>
      <?php endif; ?>

      <form id="cachet-form">
        <input type="file" id="cachet-file" accept="image/png,image/jpeg">
        <p style="font-size:0.75rem;color:var(--muted);margin-top:6px;">Laissez vide pour ne pas insérer de cachet, ou pour réutiliser celui déjà enregistré.</p>

        <div style="display:flex;gap:10px;margin-top:16px;">
          <button type="button" class="btn-outline" id="btn-sans-cachet">Générer sans changer</button>
          <button type="submit" class="btn-primary">Uploader et générer</button>
        </div>
      </form>
      <p id="cachet-msg" style="margin-top:10px;font-size:0.85rem;"></p>
    </section>
  </main>
</div>

<script>
  const QCM_ID = "<?= $qcm_id ?>";
</script>
<script src="../assets/js/generer-formulaire.js"></script>
</body>
</html>