<?php
/* the-abyss/login.php — obsługa formularza logowania ze strony startowej (index.php#zaloguj).
   Wygląd formularza jest w index.php; ten plik tylko sprawdza dane i przekierowuje. */
session_start();
require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") { header("Location: index.php#zaloguj"); exit; }

$login = trim((string)($_POST['login'] ?? ''));
$haslo = (string)($_POST['haslo'] ?? '');

$st = $polaczenie->prepare("SELECT id, login, haslo FROM gracze WHERE login = ? LIMIT 1");
$st->bind_param('s', $login);
$st->execute();
$wiersz = $st->get_result()->fetch_assoc();

if ($wiersz && password_verify($haslo, $wiersz['haslo'])) {
    // Nowy identyfikator sesji po zalogowaniu — podrzucone ciasteczko sesji przestaje działać.
    session_regenerate_id(true);
    $_SESSION['zalogowany'] = true;
    $_SESSION['id_gracza'] = $wiersz['id'];
    $_SESSION['login'] = $wiersz['login'];
    // Zawsze do gry — game.php sam poprowadzi nową postać przez wybór pochodzenia i klasy
    header("Location: game.php");
    exit;
}

$_SESSION['start_msg'] = [
    'widok' => 'zaloguj', 'typ' => 'blad', 'login' => $login,
    'tekst' => $wiersz ? 'Nieprawidłowe hasło.' : 'Taka postać nie istnieje w Mieście.',
];
header("Location: index.php#zaloguj");
exit;
