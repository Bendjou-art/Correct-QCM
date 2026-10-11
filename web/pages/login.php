<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../assets/css/login.css">
  <title>Connexion | Tailor Correct</title>
</head>
<body>
  <div class="login-box">
    <div class="login-header">
      <header>Connexion</header>
    </div>

    <!-- Switch Enseignant / Admin -->
    <div class="role-switch">
      <button type="button" class="role-btn active" data-role="enseignant">Enseignant</button>
      <button type="button" class="role-btn" data-role="admin">Admin</button>
    </div>

    <form id="login-form">
      <input type="hidden" id="role" value="enseignant">

      <div class="input-box">
        <input type="email" class="input-field" id="email" placeholder="Email" autocomplete="off" required>
      </div>

      <div class="input-box">
        <input type="password" class="input-field" id="password" placeholder="Mot de passe" autocomplete="off" required>
      </div>

      <div class="forgot">
        <section>
          <input type="checkbox" id="check">
          <label for="check">Se souvenir de moi</label>
        </section>
        <section>
          <a href="#">Mot de passe oublié ?</a>
        </section>
      </div>

      <p id="error-msg" class="error-msg"></p>

      <div class="input-submit">
        <button type="submit" class="submit-btn" id="submit">Se connecter</button>
      </div>
    </form>
  </div>

  <script src="../assets/js/login.js"></script>
</body>
</html>