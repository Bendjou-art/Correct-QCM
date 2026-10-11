<?php
require '../includes/auth.php';
require '../config/database.php';
$user = requireAuth();

$pdo = getConnection();

$stmt = $pdo->prepare("
    SELECT q.*, COUNT(c.id) as nb_copies
    FROM qcms q
    LEFT JOIN copies c ON c.id_qcm = q.id
    WHERE q.id_enseignant = ?
    GROUP BY q.id
    ORDER BY q.created_at DESC
");
$stmt->execute([$user['id']]);
$mesQcms = $stmt->fetchAll(PDO::FETCH_ASSOC);

$couleurs = ['#2E3A46', '#1E7A5C', '#6C5DD3', '#3F8CFF', '#E0A94D', '#D9645A'];

$statutLabels = [
    'brouillon' => ['label' => 'Brouillon', 'class' => 'badge-draft'],
    'pret' => ['label' => 'Prêt', 'class' => 'badge-active'],
    'en_correction' => ['label' => 'En correction', 'class' => 'badge-progress'],
    'cloture' => ['label' => 'Clôturé', 'class' => 'badge-closed'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tableau de bord — Tailor Correct</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>

<div class="layout">

  <aside class="sidebar">
    <div class="sidebar-logo">✂️ Tailor<span>Correct</span></div>
    <nav class="sidebar-nav">
      <a class="active" href="dashboard.php"><span class="ic">📊</span> Tableau de bord</a>
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

    <header class="topbar">
      <div class="search-box">
        <input type="text" placeholder="Rechercher un QCM, une copie...">
      </div>
      <div class="topbar-right">
        <a href="session-create.php" class="btn-add" title="Créer un QCM">+</a>
        <div class="user-chip">
          <div class="avatar"><?= strtoupper(substr($user['prenom'],0,1) . substr($user['nom'],0,1)) ?></div>
        </div>
      </div>
    </header>

    <div class="section-title-row">
      <h2 class="section-title">Mes QCM</h2>
      <a href="session-create.php" class="link-add">+ Créer un QCM</a>
    </div>

    <section class="course-grid">
      <?php if (empty($mesQcms)): ?>
        <p style="color:var(--muted);">Aucun QCM créé pour l'instant.</p>
      <?php else: ?>
        <?php foreach ($mesQcms as $index => $qcm): ?>
          <?php
            $couleur = $couleurs[$index % count($couleurs)];
            $statut = $statutLabels[$qcm['statut']] ?? ['label' => $qcm['statut'], 'class' => 'badge-active'];
          ?>
          <a class="course-card" href="session-detail.php?id=<?= $qcm['id'] ?>">
            <div class="course-banner" style="background:<?= $couleur ?>">
              <div class="course-title"><?= htmlspecialchars($qcm['titre']) ?></div>
              <div class="course-subject"><?= htmlspecialchars($qcm['matiere']) ?></div>
              <div class="course-avatar">👤</div>
            </div>
            <div class="course-body">
              <div class="course-meta">
                <span><?= $qcm['nb_copies'] ?> copie<?= $qcm['nb_copies'] > 1 ? 's' : '' ?></span>
                <span class="badge <?= $statut['class'] ?>"><?= $statut['label'] ?></span>
              </div>
            </div>
            <div class="course-footer">
              <span class="course-icon" title="Copies & notes">📄</span>
              <span class="course-icon" title="Statistiques">📈</span>
              <span class="course-icon" title="Plus d'options">⋮</span>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>

      <a class="course-card course-card-add" href="session-create.php">
        <div class="add-icon">＋</div>
        <div class="add-label">Créer un nouveau QCM</div>
      </a>
    </section>

  </main>
</div>

</body>
</html>