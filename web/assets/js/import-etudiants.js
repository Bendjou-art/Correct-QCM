document.getElementById('import-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const fileInput = document.getElementById('fichier-etudiants');
  const msg = document.getElementById('import-msg');
  const resultat = document.getElementById('import-resultat');

  if (!fileInput.files.length) return;

  const formData = new FormData();
  formData.append('fichier', fileInput.files[0]);
  formData.append('qcm_id', QCM_ID);

  msg.textContent = 'Import en cours...';
  msg.style.color = 'var(--muted)';
  resultat.innerHTML = '';

  try {
    const response = await fetch('../api/qcms/import-etudiants.php', {
      method: 'POST',
      body: formData
    });
    const data = await response.json();

    if (data.success) {
      msg.textContent = `${data.nb_importes} étudiant(s) importé(s) avec succès.`;
      msg.style.color = 'var(--green)';
      if (data.erreurs && data.erreurs.length > 0) {
        resultat.innerHTML = '<p style="color:var(--red);font-size:0.8rem;">Erreurs : ' + data.erreurs.join(', ') + '</p>';
      }
    } else {
      msg.textContent = data.message || 'Erreur lors de l\'import.';
      msg.style.color = 'var(--red)';
    }
  } catch (err) {
    msg.textContent = 'Erreur de connexion au serveur.';
    msg.style.color = 'var(--red)';
  }
});