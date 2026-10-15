<?php
require '../includes/auth.php';
$user = requireAuth();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Créer un QCM — Tailor Correct</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/session-create.css">
</head>
<body>

<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-logo">✂️ Tailor<span>Correct</span></div>
    <nav class="sidebar-nav">
      <a href="dashboard.php"><span class="ic">📊</span> Tableau de bord</a>
      <a href="sessions.php"><span class="ic">📁</span> Mes QCM</a>
      <a class="active" href="session-create.php"><span class="ic">➕</span> Créer un QCM</a>
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
        <h1 class="page-title">Créer un QCM</h1>
        <p class="page-sub">Configurez le format du QCM — vous définirez les bonnes réponses ensuite</p>
      </div>
      <div class="user-chip">
        <div class="avatar"><?= strtoupper(substr($user['prenom'],0,1) . substr($user['nom'],0,1)) ?></div>
        <div>
          <div class="user-name"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></div>
          <div class="user-role">Enseignant</div>
        </div>
      </div>
    </header>

    <form id="qcm-form">

      <section class="panel">
        <div class="panel-header"><h3>Informations générales</h3></div>
        <div class="form-grid">
          <div class="field">
            <label>Titre du QCM</label>
            <input type="text" id="titre" placeholder="Ex. Examen Final L3" required>
          </div>
          <div class="field">
            <label>Matière</label>
            <input type="text" id="matiere" placeholder="Ex. Mathématiques" required>
          </div>
          <div class="field">
            <label>Type d'évaluation</label>
            <select id="type_evaluation" required>
              <option value="Contrôle continu">Contrôle continu (CC)</option>
              <option value="Session normale">Session normale (SN)</option>
            </select>
          </div>
          <div class="field">
            <label>Nombre de questions</label>
            <input type="number" id="nb_questions" min="1" max="100" value="10" required>
          </div>
          <div class="field">
            <label>Mode d'identification</label>
            <select id="mode_identification" required>
              <option value="matricule">Matricule + Nom</option>
              <option value="anonymat">Anonymat (code)</option>
            </select>
          </div>
          <div class="field">
            <label>Mode de marquage imposé</label>
            <select id="mode_marquage" required>
              <option value="case_noircie">Case noircie</option>
              <option value="croix">Croix</option>
              <option value="coche">Coche</option>
              <option value="autre">Autre signe</option>
            </select>
          </div>
          <div class="field field-checkbox">
            <label>
              <input type="checkbox" id="option_ne_sais_pas" checked>
              Activer l'option "Je ne sais pas" (Z)
            </label>
          </div>
          <div class="field field-checkbox">
            <label>
              <input type="checkbox" id="option_aucune_reponse" checked>
              Activer l'option "Aucune réponse" (Y)
            </label>
          </div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-header">
          <h3>Barème général</h3>
          <label class="toggle-label">
            <input type="checkbox" id="bareme_individuel">
            Personnaliser par question (configurable à l'étape suivante)
          </label>
        </div>
        <div class="form-grid" id="bareme-general">
          <div class="field">
            <label>Points bonne réponse</label>
            <input type="number" step="0.25" id="pts_bonne" value="1">
          </div>
          <div class="field">
            <label>Malus mauvaise réponse</label>
            <input type="number" step="0.25" id="pts_malus" value="0">
          </div>
          <div class="field">
            <label>Points "Je ne sais pas"</label>
            <input type="number" step="0.25" id="pts_ne_sais_pas" value="0">
          </div>
          <div class="field">
            <label>Points aucune réponse</label>
            <input type="number" step="0.25" id="pts_aucune" value="0">
          </div>
        </div>
        <p class="hint">Le corrigé (bonnes réponses) sera à configurer séparément, après la création du QCM.</p>
      </section>

      <div class="form-actions">
        <button type="button" class="btn-outline" onclick="window.location.href='sessions.php'">Annuler</button>
        <button type="submit" class="btn-primary">Créer le QCM</button>
      </div>

      <p id="form-error" class="error-msg"></p>
      <p id="form-success" class="success-msg"></p>
    </form>

  </main>
</div>

<script src="../assets/js/session-create.js"></script>
</body>
</html>