<?php
require '../includes/auth.php';
require '../config/database.php';
$user = requireAuth();

$copie_id = $_GET['id'] ?? null;
if (!$copie_id) {
    header('Location: copies.php');
    exit;
}

$pdo = getConnection();

// Récupérer la copie + le QCM associé (vérifie que ça appartient bien à l'enseignant connecté)
$stmt = $pdo->prepare("
    SELECT c.*, q.titre as qcm_titre, q.matiere, q.id_enseignant
    FROM copies c
    JOIN qcms q ON q.id = c.id_qcm
    WHERE c.id = ?
");
$stmt->execute([$copie_id]);
$copieRaw = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$copieRaw || $copieRaw['id_enseignant'] !== $user['id']) {
    die('Copie introuvable ou accès non autorisé.');
}

$identifiant = $copieRaw['matricule']
    ? $copieRaw['matricule'] . ' - ' . $copieRaw['nom_etudiant'] . ' ' . $copieRaw['prenom_etudiant']
    : ($copieRaw['identifiant_anonymat'] ?? 'Non identifié');

$copie = [
    'id' => $copieRaw['id'],
    'identifiant' => $identifiant,
    'matricule' => $copieRaw['matricule'],
    'qcm' => $copieRaw['qcm_titre'],
    'matiere' => $copieRaw['matiere'],
    'date_numerisation' => date('d/m/Y à H:i', strtotime($copieRaw['date_numerisation'])),
    'statut' => $copieRaw['statut'],
    'image_url' => $copieRaw['image_url'],
];

// Récupérer la note et le détail de calcul
$stmt = $pdo->prepare("SELECT * FROM notes WHERE id_copie = ?");
$stmt->execute([$copie_id]);
$noteRaw = $stmt->fetch(PDO::FETCH_ASSOC);

$copie['note'] = $noteRaw ? $noteRaw['note_totale'] : null;
$copie['note_max'] = 20; // à ajuster si vous voulez calculer le vrai max selon le barème

$reponses = [];
if ($noteRaw) {
    $detailCalcul = json_decode($noteRaw['detail_calcul'], true);
    foreach ($detailCalcul as $d) {
        $reponses[] = [
            'numero' => $d['question'],
            'enonce' => '', // pas d'énoncé dans votre système simplifié
            'bonnes_reponses' => $d['bonne_reponse'],
            'reponse_donnee' => $d['reponse_donnee'],
            'points' => $d['points'],
            'resultat' => match(true) {
                $d['type'] === 'ne_sais_pas' => 'sans_reponse',
                $d['type'] === 'vide' || $d['type'] === 'aucune_reponse' => 'sans_reponse',
                $d['points'] > 0 => 'correcte',
                default => 'incorrecte',
            },
            'anomalie' => null,
        ];
    }
}

$resultatLabels = [
    'correcte' => ['label' => 'Correcte', 'class' => 'res-correcte'],
    'incorrecte' => ['label' => 'Incorrecte', 'class' => 'res-incorrecte'],
    'partielle' => ['label' => 'Partielle', 'class' => 'res-partielle'],
    'sans_reponse' => ['label' => 'Sans réponse', 'class' => 'res-vide'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Copie — <?= htmlspecialchars($copie['identifiant']) ?> — Tailor Correct</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/session-detail.css">
<link rel="stylesheet" href="../assets/css/copie-detail.css">
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
    <a href="copies.php" class="back-link">← Retour à Copies & notes</a>

    <header class="topbar">
      <div>
        <h1 class="page-title"><?= htmlspecialchars($copie['identifiant']) ?></h1>
        <p class="page-sub"><?= htmlspecialchars($copie['qcm']) ?> · <?= htmlspecialchars($copie['matiere']) ?> · Numérisée le <?= $copie['date_numerisation'] ?></p>
      </div>
      <div class="user-chip">
        <div class="avatar"><?= strtoupper(substr($user['prenom'],0,1) . substr($user['nom'],0,1)) ?></div>
        <div>
          <div class="user-name"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></div>
          <div class="user-role">Enseignant</div>
        </div>
      </div>
    </header>

    <div class="detail-grid">

      <div class="panel image-panel">
        <div class="panel-header"><h3>Copie scannée</h3></div>
        <?php if ($copie['image_url']): ?>
          <img src="../<?= htmlspecialchars($copie['image_url']) ?>" alt="Copie" style="width:100%;border-radius:10px;border:1px solid var(--border);">
        <?php else: ?>
          <div class="copie-image-placeholder">🖼️ Image non disponible</div>
        <?php endif; ?>
      </div>

      <div class="right-col">
        <div class="panel note-panel">
          <?php if ($copie['note'] !== null): ?>
            <?php $noteClass = $copie['note'] >= 14 ? 'note-bonne' : ($copie['note'] >= 10 ? 'note-moyenne' : 'note-faible'); ?>
            <div class="note-big">
              <span class="note-number <?= $noteClass ?>"><?= $copie['note'] ?></span>
              <span class="note-total">/ <?= $copie['note_max'] ?></span>
            </div>
          <?php else: ?>
            <p style="color:var(--muted);">Copie pas encore corrigée.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if (!empty($reponses)): ?>
    <section class="panel">
      <div class="panel-header"><h3>Détail de la correction</h3></div>
      <table class="mini-table">
        <thead>
          <tr><th>Q</th><th>Réponse donnée</th><th>Bonne réponse</th><th>Résultat</th><th>Points</th></tr>
        </thead>
        <tbody>
          <?php foreach ($reponses as $r): ?>
            <?php $res = $resultatLabels[$r['resultat']] ?? $resultatLabels['incorrecte']; ?>
            <tr>
              <td><strong>Q<?= $r['numero'] ?></strong></td>
              <td><?= empty($r['reponse_donnee']) ? '<span class="opt-badge opt-vide">Aucune</span>' : implode(' ', array_map(fn($x) => "<span class='opt-badge'>$x</span>", $r['reponse_donnee'])) ?></td>
              <td><?= implode(' ', array_map(fn($x) => "<span class='opt-badge opt-correct'>$x</span>", $r['bonnes_reponses'])) ?></td>
              <td><span class="res-badge <?= $res['class'] ?>"><?= $res['label'] ?></span></td>
              <td><?= $r['points'] ?> pt</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
    <?php endif; ?>

  </main>
</div>

</body>
</html>