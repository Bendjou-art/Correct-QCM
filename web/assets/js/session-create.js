document.getElementById('bareme_individuel').addEventListener('change', toggleBaremeIndividuel);

function toggleBaremeIndividuel() {
  const individuel = document.getElementById('bareme_individuel').checked;
  document.getElementById('bareme-general').style.opacity = individuel ? '0.4' : '1';
  document.querySelectorAll('#bareme-general input').forEach(input => {
    input.disabled = individuel;
  });
}

let soumissionEnCours = false;

document.getElementById('qcm-form').addEventListener('submit', async (e) => {
  e.preventDefault();

  if (soumissionEnCours) return;
  soumissionEnCours = true;

  const submitBtn = e.target.querySelector('button[type="submit"]');
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.textContent = 'Création en cours...';
  }

  const errorMsg = document.getElementById('form-error');
  const successMsg = document.getElementById('form-success');
  errorMsg.textContent = '';
  successMsg.textContent = '';

  const nbQuestions = parseInt(document.getElementById('nb_questions').value);

  if (!nbQuestions || nbQuestions < 1 || nbQuestions > 100) {
    errorMsg.textContent = 'Le nombre de questions doit être entre 1 et 100.';
    soumissionEnCours = false;
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Créer le QCM';
    }
    return;
  }

  const baremeGlobal = {
    points_bonne_reponse: parseFloat(document.getElementById('pts_bonne').value),
    points_malus: parseFloat(document.getElementById('pts_malus').value),
    points_ne_sais_pas: parseFloat(document.getElementById('pts_ne_sais_pas').value),
    points_aucune_reponse: parseFloat(document.getElementById('pts_aucune').value),
  };

  // Génère la structure des questions (numéro + barème), sans bonnes réponses
  const questions = [];
  for (let i = 1; i <= nbQuestions; i++) {
    questions.push({
      numero_question: i,
      ...baremeGlobal
    });
  }

  const payload = {
    titre: document.getElementById('titre').value,
    matiere: document.getElementById('matiere').value,
    type_evaluation: document.getElementById('type_evaluation').value,
    nb_questions: nbQuestions,
    mode_identification: document.getElementById('mode_identification').value,
    mode_marquage: document.getElementById('mode_marquage').value,
    option_ne_sais_pas: document.getElementById('option_ne_sais_pas').checked,
    option_aucune_reponse: document.getElementById('option_aucune_reponse').checked,
    questions: questions
  };

  try {
    const response = await fetch('../api/qcms/create.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await response.json();

    if (data.success) {
      successMsg.textContent = 'QCM créé avec succès ! Redirection...';
      setTimeout(() => {
        window.location.href = `session-detail.php?id=${data.qcm_id}`;
      }, 1000);
    } else {
      errorMsg.textContent = data.message || 'Erreur lors de la création du QCM.';
      soumissionEnCours = false;
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Créer le QCM';
      }
    }
  } catch (err) {
    errorMsg.textContent = 'Erreur de connexion au serveur.';
    soumissionEnCours = false;
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Créer le QCM';
    }
  }
});