<?php
// Konfiguracja połączenia z lokalną bazą danych (XAMPP)
$host = "localhost"; // Adres serwera
$user = "root";      // Domyślny użytkownik XAMPP
$pass = "";          // Domyślnie brak hasła w XAMPP
$db   = "the_abyss"; // Nazwa naszej bazy, którą przed chwilą stworzyłaś

// Próba nawiązania połączenia
$polaczenie = new mysqli($host, $user, $pass, $db);

// Sprawdzenie, czy most działa
if ($polaczenie->connect_error) {
    die("Krytyczny błąd systemu: Serwer bazy danych nie odpowiada. " . $polaczenie->connect_error);
}
// Jeśli wszystko jest w porządku, plik ładuje się w ciszy i pozwala działać grze.
?>