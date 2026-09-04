<?php
/* the-abyss/config/mg.php
   Loginy Mistrzów Gry. Panel MG i ręczne nadawanie tytułów sprawdzają
   tę listę — nie ma flagi w bazie, więc awans na MG to edycja pliku.
   Porównanie bez wielkości liter. */

$MISTRZOWIE_GRY = [
    'StormChaser92',
];

function czy_mg(?string $login): bool {
    global $MISTRZOWIE_GRY;
    if (!$login) return false;
    foreach ($MISTRZOWIE_GRY as $mg)
        if (strcasecmp($mg, $login) === 0) return true;
    return false;
}
