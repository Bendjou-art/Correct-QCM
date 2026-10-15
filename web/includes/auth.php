<?php
function requireAuth() {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    return [
        'id' => $_SESSION['user_id'],
        'role' => $_SESSION['role'],
        'nom' => $_SESSION['nom'],
        'prenom' => $_SESSION['prenom'],
    ];
}