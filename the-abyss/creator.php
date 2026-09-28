<?php
/* the-abyss/creator.php — stary kreator (wybór Szabrownik/Inżynier/Egzekutor), wyłączony.
   Postać tworzy się teraz w grze: game.php wymusza wybór pochodzenia (page=wybor_pochodzenia),
   a potem gracz wybiera klasę. Zaślepka zostaje dla starych linków i zakładek. */
session_start();
header("Location: " . (empty($_SESSION['zalogowany']) ? "index.php" : "game.php"));
exit;
