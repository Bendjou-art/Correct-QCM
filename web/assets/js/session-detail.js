// Gestion des onglets
document.querySelectorAll('.tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
    tab.classList.add('active');
    const target = document.getElementById('tab-' + tab.dataset.tab);
    if (target) target.style.display = 'block';
  });
});

// Ancien bouton numériser (sécurisé, au cas où il existerait encore ailleurs)
const btnNumeriser = document.getElementById('btn-numeriser');
if (btnNumeriser) {
  btnNumeriser.addEventListener('click', () => {
    alert('Écran de numérisation à venir — upload/scan des copies.');
  });
}

// Bouton Corriger
document.querySelectorAll('.btn-corriger').forEach(btn => {
  btn.addEventListener('click', async () => {
    btn.disabled = true;
    btn.textContent = 'Correction...';
    try {
      const response = await fetch('../api/copies/corriger.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ copie_id: btn.dataset.id })
      });
      const data = await response.json();
      if (data.success) {
        alert(`Note calculée : ${data.note}/20`);
        location.reload();
      } else {
        alert('Erreur : ' + data.message);
        btn.disabled = false;
        btn.textContent = 'Corriger';
      }
    } catch (err) {
      alert('Erreur de connexion');
      btn.disabled = false;
      btn.textContent = 'Corriger';
    }
  });
});

const btnExportToggle = document.getElementById('btn-export-toggle');
const exportMenu = document.getElementById('export-menu');
if (btnExportToggle && exportMenu) {
  btnExportToggle.addEventListener('click', (e) => {
    e.stopPropagation();
    exportMenu.style.display = exportMenu.style.display === 'none' ? 'block' : 'none';
  });
  document.addEventListener('click', () => {
    exportMenu.style.display = 'none';
  });
}
