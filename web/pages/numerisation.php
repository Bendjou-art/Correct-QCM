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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Numériser des copies — Tailor Correct</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/session-detail.css">
<link rel="stylesheet" href="../assets/css/numerisation.css">
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
    <a href="session-detail.php?id=<?= $qcm['id'] ?>" class="back-link">← Retour au QCM</a>

    <header class="topbar">
      <div>
        <h1 class="page-title">Numériser des copies</h1>
        <p class="page-sub"><?= htmlspecialchars($qcm['titre']) ?> · <?= htmlspecialchars($qcm['matiere']) ?></p>
      </div>
      <div class="user-chip">
        <div class="avatar"><?= strtoupper(substr($user['prenom'],0,1) . substr($user['nom'],0,1)) ?></div>
        <div>
          <div class="user-name"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></div>
          <div class="user-role">Enseignant</div>
        </div>
      </div>
    </header>

    <section class="panel">
      <div class="panel-header"><h3>Importer des copies scannées</h3></div>

      <div class="dropzone" id="dropzone">
        <div class="dropzone-icon">📁</div>
        <p class="dropzone-text">Glissez-déposez vos images ici, ou</p>
        <label for="file-input" class="btn-primary" style="display:inline-block;cursor:pointer;">Choisir des fichiers</label>
        <input type="file" id="file-input" accept="image/*,.pdf" multiple hidden>
        <p class="dropzone-hint">Formats acceptés : JPG, PNG, PDF — plusieurs fichiers à la fois</p>
      </div>

      <div id="preview-list" class="preview-list"></div>

      <div class="upload-actions">
        <button class="btn-outline" id="btn-clear" style="display:none;">Tout retirer</button>
        <button class="btn-primary" id="btn-upload" style="display:none;">📤 Envoyer les copies</button>
      </div>

      <p id="upload-status" class="upload-status"></p>
    </section>

  </main>
</div>

<script>
  const QCM_ID = "<?= $qcm['id'] ?>";
</script>
<script src="../assets/js/numerisation.js"></script>
</body>
</html>