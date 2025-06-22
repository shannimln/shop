<?php
try {
    $db = new PDO('mysql:host=nicolavshiro.mysql.db;dbname=nicolavshiro;charset=utf8', 'nicolavshiro', '28Avril2009');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $_SESSION['error'] = "Erreur de connexion à la base de données.";
    header("Location: register.php");
    exit();
}
?>