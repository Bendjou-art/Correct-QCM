// Gérer la case "Aucune des réponses proposées n'est juste" par question
document.querySelectorAll('.question-row').forEach(row => {
  const checkboxAucune = row.querySelector('.q-aucune-juste');
  const checkboxesReponses = row.querySelectorAll('.q-reponse');

  checkboxAucune.addEventListener('change', () => {
    if (checkboxAucune.checked) {
      checkboxesReponses.forEach(cb => { cb.checked = false; cb.disabled = true; });
    } else {
      checkboxesReponses.forEach(cb => { cb.disabled = false; });
    }
  });

  // Si des réponses sont déjà cochées au chargement, s'assurer que "aucune juste" est décoché
  const auMoinsUneCochee = Array.from(checkboxesReponses).some(cb => cb.checked);
  if (auMoinsUneCochee) {
    checkboxAucune.checked = false;
  }
});

let soumissionEnCours = false;

document.getElementById('corrige-form').addEventListener('submit', async (e) => {
  e.preventDefault();

  if (soumissionEnCours) return;
  soumissionEnCours = true;

  const submitBtn = e.target.querySelector('button[type="submit"]');
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.textContent = 'Enregistrement...';
  }

  const errorMsg = document.getElementById('form-error');
  const successMsg = document.getElementById('form-success');
  errorMsg.textContent = '';
  successMsg.textContent = '';

  const questions = [];

  document.querySelectorAll('.question-row').forEach(row => {
    const questionId = row.dataset.questionId;
    const aucuneJuste = row.querySelector('.q-aucune-juste').checked;
    const checked = aucuneJuste
      ? []
      : Array.from(row.querySelectorAll('.q-reponse:checked')).map(cb => cb.value);

    questions.push({
      id_question: questionId,
      bonnes_reponses: checked
    });
  });

  const payload = {
    qcm_id: document.getElementById('qcm_id').value,
    questions: questions
  };

  try {
    const response = await fetch('../api/qcms/update-corrige.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await response.json();

    if (data.success) {
      successMsg.textContent = 'Corrigé enregistré avec succès ! Redirection...';
      setTimeout(() => {
        window.location.href = `session-detail.php?id=${payload.qcm_id}`;
      }, 1000);
    } else {
      errorMsg.textContent = data.message || 'Erreur lors de l\'enregistrement.';
      soumissionEnCours = false;
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Enregistrer le corrigé';
      }
    }
  } catch (err) {
    errorMsg.textContent = 'Erreur de connexion au serveur.';
    soumissionEnCours = false;
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Enregistrer le corrigé';
    }
  }
});