<?php
/* the-abyss/register.php — obsługa formularza rejestracji ze strony startowej (index.php#rejestracja).
   Wygląd formularza jest w index.php; ten plik tylko zapisuje gracza i przekierowuje. */
session_start();
require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") { header("Location: index.php#rejestracja"); exit; }

$login = trim((string)($_POST['login'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$haslo = (string)($_POST['haslo'] ?? '');
$wroc = function (string $widok, string $typ, string $tekst) use ($login) {
    $_SESSION['start_msg'] = ['widok' => $widok, 'typ' => $typ, 'tekst' => $tekst, 'login' => $login];
    header("Location: index.php#$widok");
    exit;
};

// Login ląduje w powiadomieniach, czacie i HTML-u wielu stron — tylko litery, cyfry, spacja, _ . -
if (!preg_match('/^[\p{L}\p{N}][\p{L}\p{N} _.\-]{2,23}$/u', $login)) $wroc('rejestracja', 'blad', 'Imię postaci: 3–24 znaki, litery, cyfry, spacja, _ . -');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $wroc('rejestracja', 'blad', 'Nieprawidłowy adres e-mail.');

$zaszyfrowane_haslo = password_hash($haslo, PASSWORD_DEFAULT);
$st = $polaczenie->prepare("INSERT INTO gracze (login, email, haslo) VALUES (?, ?, ?)");
$st->bind_param('sss', $login, $email, $zaszyfrowane_haslo);
try { $ok = $st->execute(); $kod = $ok ? 0 : $st->errno; }
catch (mysqli_sql_exception $e) { $ok = false; $kod = $e->getCode(); }

if ($ok) $wroc('zaloguj', 'ok', 'Obywatel zarejestrowany. Witamy w The Abyss — zaloguj się.');
$wroc('rejestracja', 'blad', $kod == 1062 ? 'To imię albo e-mail jest już zajęte.' : 'Błąd centrali. Spróbuj ponownie.');
