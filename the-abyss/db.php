<?php
/* the-abyss/db.php — połączenie z bazą.
   Dane logowania trzymaj w db_haslo.php (obok tego pliku, poza GitHubem — patrz .gitignore).
   Wzór: db_haslo.przyklad.php. Bez db_haslo.php zostają domyślne ustawienia XAMPP (root bez hasła).
   Na końcu dołącza includes/csrf.php — ochronę formularzy dla całej gry. */
$host = "localhost";
$user = "root";
$pass = "";
$db   = "the_abyss";
if (is_file(__DIR__ . '/db_haslo.php')) require __DIR__ . '/db_haslo.php';

$polaczenie = new mysqli($host, $user, $pass, $db);
if ($polaczenie->connect_error) {
    die("Krytyczny błąd systemu: Serwer bazy danych nie odpowiada.");
}
unset($pass);

require_once __DIR__ . '/includes/csrf.php';
