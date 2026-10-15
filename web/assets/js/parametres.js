document.getElementById('cachet-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const fileInput = document.getElementById('cachet-file');
  const msg = document.getElementById('cachet-msg');

  if (!fileInput.files.length) return;

  const formData = new FormData();
  formData.append('cachet', fileInput.files[0]);

  msg.textContent = 'Envoi en cours...';

  try {
    const response = await fetch('../api/users/cachet.php', {
      method: 'POST',
      body: formData
    });
    const data = await response.json();

    if (data.success) {
      msg.textContent = 'Cachet enregistré avec succès.';
      msg.style.color = 'var(--green)';
      setTimeout(() => location.reload(), 1000);
    } else {
      msg.textContent = data.message || 'Erreur.';
      msg.style.color = 'var(--red)';
    }
  } catch (err) {
    msg.textContent = 'Erreur de connexion.';
    msg.style.color = 'var(--red)';
  }
});