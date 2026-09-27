<?php
/* the-abyss/pages/sesje.php — stara lista sesji, wyłączona.
   game.php przekierowuje page=sesje na page=centrum (301), zanim ten plik zostanie dołączony.
   Zaślepka zostaje dla zakładek i starych linków w powiadomieniach. */
header('Location: game.php?page=centrum', true, 301);
echo "<script>window.location.href='game.php?page=centrum';</script>";
return;
