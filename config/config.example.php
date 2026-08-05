<?php
// MODELE - copier ce fichier en config.php et remplir les valeurs reelles.
// config.php n'est jamais commite (voir .gitignore).

if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
    // Environnement local (XAMPP)
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'nolabel');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // Environnement de production (Infomaniak)
    define('DB_HOST', 'hhva.myd.infomaniak.com');
    define('DB_NAME', 'VOTRE_BASE');
    define('DB_USER', 'VOTRE_UTILISATEUR');
    define('DB_PASS', 'VOTRE_MOT_DE_PASSE');
}

define('DEBUG', DB_HOST === 'localhost');