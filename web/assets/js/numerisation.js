const dropzone = document.getElementById('dropzone');
const fileInput = document.getElementById('file-input');
const previewList = document.getElementById('preview-list');
const btnClear = document.getElementById('btn-clear');
const btnUpload = document.getElementById('btn-upload');
const uploadStatus = document.getElementById('upload-status');

let fichiersSelectionnes = [];

// Ouvrir le sélecteur de fichiers en cliquant sur la zone
dropzone.addEventListener('click', (e) => {
  if (e.target.tagName !== 'LABEL' && e.target.tagName !== 'INPUT') {
    fileInput.click();
  }
});

fileInput.addEventListener('change', (e) => {
  ajouterFichiers(e.target.files);
});

// Drag & drop
['dragenter', 'dragover'].forEach(evt => {
  dropzone.addEventListener(evt, (e) => {
    e.preventDefault();
    dropzone.classList.add('dragover');
  });
});
['dragleave', 'drop'].forEach(evt => {
  dropzone.addEventListener(evt, (e) => {
    e.preventDefault();
    dropzone.classList.remove('dragover');
  });
});
dropzone.addEventListener('drop', (e) => {
  ajouterFichiers(e.dataTransfer.files);
});

function ajouterFichiers(fileList) {
  Array.from(fileList).forEach(file => {
    fichiersSelectionnes.push(file);
  });
  afficherApercus();
}

function afficherApercus() {
  previewList.innerHTML = '';

  fichiersSelectionnes.forEach((file, index) => {
    const item = document.createElement('div');
    item.className = 'preview-item';

    const isImage = file.type.startsWith('image/');
    const imgSrc = isImage ? URL.createObjectURL(file) : null;

    item.innerHTML = `
      ${isImage
        ? `<img src="${imgSrc}" alt="${file.name}">`
        : `<div style="height:140px;display:flex;align-items:center;justify-content:center;font-size:2rem;">📄</div>`
      }
      <div class="file-name">${file.name}</div>
      <button class="remove-btn" data-index="${index}">✕</button>
    `;
    previewList.appendChild(item);
  });

  document.querySelectorAll('.remove-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const idx = parseInt(btn.dataset.index);
      fichiersSelectionnes.splice(idx, 1);
      afficherApercus();
    });
  });

  const hasFiles = fichiersSelectionnes.length > 0;
  btnClear.style.display = hasFiles ? 'inline-block' : 'none';
  btnUpload.style.display = hasFiles ? 'inline-block' : 'none';
}

btnClear.addEventListener('click', () => {
  fichiersSelectionnes = [];
  afficherApercus();
});

btnUpload.addEventListener('click', async () => {
  if (fichiersSelectionnes.length === 0) return;

  btnUpload.disabled = true;
  btnUpload.textContent = 'Envoi en cours...';
  uploadStatus.textContent = '';
  uploadStatus.className = 'upload-status';

  const formData = new FormData();
  formData.append('qcm_id', QCM_ID);
  fichiersSelectionnes.forEach(file => {
    formData.append('copies[]', file);
  });

  try {
    const response = await fetch('../api/copies/submit.php', {
      method: 'POST',
      body: formData
    });
    const data = await response.json();

    if (data.success) {
      uploadStatus.textContent = `${data.nb_copies_ajoutees} copie(s) envoyée(s) avec succès !`;
      uploadStatus.classList.add('success');
      fichiersSelectionnes = [];
      afficherApercus();
    } else {
      uploadStatus.textContent = data.message || 'Erreur lors de l\'envoi.';
      uploadStatus.classList.add('error');
    }
  } catch (err) {
    uploadStatus.textContent = 'Erreur de connexion au serveur.';
    uploadStatus.classList.add('error');
  } finally {
    btnUpload.disabled = false;
    btnUpload.textContent = '📤 Envoyer les copies';
  }
});