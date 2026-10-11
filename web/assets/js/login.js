// Gestion du switch Enseignant / Admin
const roleButtons = document.querySelectorAll('.role-btn');
const roleInput = document.getElementById('role');

roleButtons.forEach(btn => {
  btn.addEventListener('click', () => {
    roleButtons.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    roleInput.value = btn.dataset.role;
  });
});

document.getElementById('login-form').addEventListener('submit', async (e) => {
  e.preventDefault();

  const role = document.getElementById('role').value;
  const email = document.getElementById('email').value.trim();
  const password = document.getElementById('password').value;
  const errorMsg = document.getElementById('error-msg');
  const submitBtn = document.getElementById('submit');

  errorMsg.textContent = '';

  if (!email || !password) {
    errorMsg.textContent = 'Veuillez remplir tous les champs.';
    return;
  }

  submitBtn.disabled = true;
  submitBtn.textContent = 'Connexion...';

  try {
    const response = await fetch('../api/auth/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ role, email, password })
    });

    const data = await response.json();

    if (data.success) {
      localStorage.setItem('token', data.token);
      localStorage.setItem('role', data.user.role);
      localStorage.setItem('nom', data.user.nom);
      localStorage.setItem('prenom', data.user.prenom);
      window.location.href = 'dashboard.php';
    } else {
      errorMsg.textContent = data.message || 'Identifiants incorrects.';
    }
  } catch (err) {
    errorMsg.textContent = 'Erreur de connexion au serveur.';
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Se connecter';
  }
});