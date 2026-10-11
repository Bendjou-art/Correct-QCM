<?php
require '../includes/auth.php';
require '../config/database.php';
$user = requireAuth();

$qcm_id = $_GET['id'] ?? null;
if (!$qcm_id) { header('Location: sessions.php'); exit; }

$pdo = getConnection();
$stmt = $pdo->prepare("SELECT * FROM qcms WHERE id = ? AND id_enseignant = ?");
$stmt->execute([$qcm_id, $user['id']]);
$qcm = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$qcm) { die('QCM introuvable.'); }

$stmt = $pdo->prepare("SELECT COUNT(*) FROM etudiants_qcm WHERE id_qcm = ?");
$stmt->execute([$qcm_id]);
$nbDejaImportes = (int) $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Importer les étudiants — Tailor Correct</title>
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
    <a href="session-detail.php?id=<?= $qcm_id ?>" class="back-link">← Retour au QCM</a>

    <header class="topbar">
      <div>
        <h1 class="page-title">Importer la liste des étudiants</h1>
        <p class="page-sub"><?= htmlspecialchars($qcm['titre']) ?> · <?= htmlspecialchars($qcm['matiere']) ?></p>
      </div>
    </header>

    <?php if ($nbDejaImportes > 0): ?>
      <div class="alert-banner">
        ℹ️ <?= $nbDejaImportes ?> étudiant(s) déjà importé(s) pour ce QCM. Un nouvel import ajoutera/mettra à jour la liste.
      </div>
    <?php endif; ?>

    <section class="panel" style="max-width:560px;">
      <div class="panel-header"><h3>1. Télécharger le modèle</h3></div>
      <p class="hint">Le fichier doit contenir exactement deux colonnes : "nom de l'élève" et "matricule".</p>
      <a href="../api/qcms/telecharger-modele.php" class="btn-outline" style="text-decoration:none;display:inline-block;margin-top:10px;">⬇ Télécharger le modèle Excel</a>
    </section>

    <section class="panel" style="max-width:560px;margin-top:20px;">
      <div class="panel-header"><h3>2. Importer le fichier rempli</h3></div>
      <form id="import-form">
        <input type="file" id="fichier-etudiants" accept=".xlsx,.csv" required>
        <div style="margin-top:16px;">
          <button type="submit" class="btn-primary">Importer la liste</button>
        </div>
      </form>
      <p id="import-msg" style="margin-top:12px;font-size:0.85rem;"></p>
      <div id="import-resultat" style="margin-top:12px;"></div>
    </section>

  </main>
</div>

<script>
const QCM_ID = "<?= $qcm_id ?>";
</script>
<script src="../assets/js/import-etudiants.js"></script>
</body>
</html>