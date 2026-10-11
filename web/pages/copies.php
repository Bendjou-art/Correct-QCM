<?php
require '../includes/auth.php';
require '../config/database.php';
$user = requireAuth();

$pdo = getConnection();

$stmt = $pdo->prepare("
    SELECT c.id, c.matricule, c.nom_etudiant, c.prenom_etudiant, c.identifiant_anonymat,
           c.date_numerisation, c.statut,
           q.titre as qcm_titre, q.matiere,
           n.note_totale,
           EXISTS(SELECT 1 FROM anomalies a WHERE a.id_copie = c.id AND a.statut = 'signalee') as a_anomalie
    FROM copies c
    JOIN qcms q ON q.id = c.id_qcm
    LEFT JOIN notes n ON n.id_copie = c.id
    WHERE q.id_enseignant = ?
    ORDER BY c.date_numerisation DESC
");
$stmt->execute([$user['id']]);
$copiesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

$copies = [];
foreach ($copiesRaw as $c) {
    $identifiant = $c['matricule']
        ? $c['matricule'] . ' - ' . $c['nom_etudiant'] . ' ' . $c['prenom_etudiant']
        : ($c['identifiant_anonymat'] ?? 'Non identifié');

    $copies[] = [
        'id' => $c['id'],
        'identifiant' => $identifiant,
        'qcm' => $c['qcm_titre'],
        'matiere' => $c['matiere'],
        'date' => date('d/m/Y', strtotime($c['date_numerisation'])),
        'statut' => $c['statut'],
        'note' => $c['note_totale'],
        'anomalie' => (bool) $c['a_anomalie'],
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Copies & notes — Tailor Correct</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/sessions.css">
<link rel="stylesheet" href="../assets/css/session-detail.css">
<link rel="stylesheet" href="../assets/css/copies.css">
</head>
<body>

<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-logo">✂️ Tailor<span>Correct</span></div>
    <nav class="sidebar-nav">
      <a href="dashboard.php"><span class="ic">📊</span> Tableau de bord</a>
      <a href="sessions.php"><span class="ic">📁</span> Mes QCM</a>
      <a href="session-create.php"><span class="ic">➕</span> Créer un QCM</a>
      <a class="active" href="copies.php"><span class="ic">📄</span> Copies & notes</a>
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
        <h1 class="page-title">Copies & notes</h1>
        <p class="page-sub"><?= count($copies) ?> copies numérisées, tous QCM confondus</p>
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
        <input type="text" id="search-input" placeholder="Rechercher par matricule, nom ou code anonymat...">
      </div>
      <div class="filter-tabs">
        <button class="filter-tab active" data-filter="tous">Toutes</button>
        <button class="filter-tab" data-filter="corrigee">Corrigées</button>
        <button class="filter-tab" data-filter="numerisee">En attente</button>
        <button class="filter-tab" data-filter="anomalie">Avec anomalie</button>
      </div>
    </div>

    <section class="panel">
      <table class="mini-table" id="copies-table">
        <thead>
          <tr>
            <th>Copie</th><th>QCM</th><th>Matière</th><th>Date</th><th>Statut</th><th>Note</th><th>Anomalie</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($copies)): ?>
            <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:30px;">Aucune copie numérisée pour l'instant.</td></tr>
          <?php else: ?>
            <?php foreach ($copies as $copie): ?>
              <tr class="copie-row"
                  data-statut="<?= $copie['statut'] ?>"
                  data-anomalie="<?= $copie['anomalie'] ? '1' : '0' ?>"
                  data-search="<?= strtolower($copie['identifiant'] . ' ' . $copie['qcm']) ?>">
                <td><strong><?= htmlspecialchars($copie['identifiant']) ?></strong></td>
                <td><?= htmlspecialchars($copie['qcm']) ?></td>
                <td><?= htmlspecialchars($copie['matiere']) ?></td>
                <td><?= $copie['date'] ?></td>
                <td><span class="badge <?= $copie['statut'] === 'corrigee' ? 'badge-active' : 'badge-draft' ?>"><?= ucfirst($copie['statut']) ?></span></td>
                <td>
                  <?php if ($copie['note'] !== null): ?>
                    <?php $noteClass = $copie['note'] >= 14 ? 'note-bonne' : ($copie['note'] >= 10 ? 'note-moyenne' : 'note-faible'); ?>
                    <span class="note-value <?= $noteClass ?>"><?= $copie['note'] ?>/20</span>
                  <?php else: ?>
                    <span class="note-empty">—</span>
                  <?php endif; ?>
                </td>
                <td><?= $copie['anomalie'] ? '<span class="badge badge-anomalie">⚠️ Détectée</span>' : '—' ?></td>
                <td><a href="copie-detail.php?id=<?= $copie['id'] ?>" class="icon-btn">Voir</a></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
      <p id="no-results" class="no-results" style="display:none;">Aucune copie ne correspond à votre recherche.</p>
    </section>
  </main>
</div>

<script src="../assets/js/copies.js"></script>
</body>
</html>