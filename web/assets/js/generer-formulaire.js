document.getElementById('btn-sans-cachet').addEventListener('click', () => {
  window.location.href = `../api/qcms/formulaire-pdf.php?id=${QCM_ID}`;
});

document.getElementById('cachet-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const fileInput = document.getElementById('cachet-file');
  const msg = document.getElementById('cachet-msg');

  if (!fileInput.files.length) {
    // Aucun fichier choisi : générer directement avec le cachet déjà en base (s'il existe)
    window.location.href = `../api/qcms/formulaire-pdf.php?id=${QCM_ID}`;
    return;
  }

  const formData = new FormData();
  formData.append('cachet', fileInput.files[0]);

  msg.textContent = 'Envoi du cachet en cours...';

  try {
    const response = await fetch('../api/users/cachet.php', {
      method: 'POST',
      body: formData
    });
    const data = await response.json();

    if (data.success) {
      window.location.href = `../api/qcms/formulaire-pdf.php?id=${QCM_ID}`;
    } else {
      msg.textContent = data.message || 'Erreur lors de l\'upload du cachet.';
      msg.style.color = 'var(--red)';
    }
  } catch (err) {
    msg.textContent = 'Erreur de connexion.';
    msg.style.color = 'var(--red)';
  }
});