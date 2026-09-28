<?php
/* the-abyss/logout.php — wylogowanie i pożegnanie ("Do zobaczenia!").
   Czyści sesję i ciasteczko sesji, potem pokazuje stronę z przyciskiem "Zaloguj ponownie" (index.php#zaloguj).
   Wygląd: css/start.css + css/wylogowanie.css, tło img/wylogowanie_bg.jpg. */
session_start();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>The Abyss — Do zobaczenia</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Kaushan+Script&family=IBM+Plex+Mono:wght@400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/start.css">
<link rel="stylesheet" href="css/wylogowanie.css">
</head>
<body class="wy">
<div class="st-fill"></div>
<header class="st-nav" id="stNav">
<a class="st-logo" href="index.php"><b>THE ABYSS</b><i></i></a>
<nav class="st-menu"><a href="index.php">Świat</a><a href="index.php#opowiesci">Opowieści</a><a href="index.php#spolecznosc">Społeczność</a><a href="index.php#faq">FAQ</a></nav>
<a class="st-login szklo" href="index.php#zaloguj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"></circle><path d="M4 21c1-4.5 4.5-6.5 8-6.5s7 2 8 6.5"></path></svg>Zaloguj<span class="rog"></span></a>
</header>
<main class="st-stage" id="stStage">
<div class="st-bg"></div>
<div class="st-rain"></div>
<div class="wy-tablica" id="wyTablica" aria-hidden="true">
<div><span class="m">Stockholm</span></div>
<div><span class="m">Prague</span><span class="s">Departed</span></div>
<div><span class="m">Lisbon</span><span class="s">Departed</span></div>
<div><span class="m">Vienna</span><span class="s">Departed</span></div>
<div><span class="m"></span><span class="s bo">Boarding</span></div>
</div>
<section class="wy-panel szklo">
<span class="kl lg"></span><span class="kl pg"></span><span class="kl ld"></span><span class="kl pd"></span>
<span class="wy-linia l"></span><span class="wy-linia p"></span>
<div class="wy-ikona"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M21.5 2.5c-1-1-2.8-.6-3.9.5l-3.5 3.5-9-2.6-1.7 1.7 7.3 4.3-3.6 3.6-2.6-.4-1.3 1.3 3.4 1.8 1.8 3.4 1.3-1.3-.4-2.6 3.6-3.6 4.3 7.3 1.7-1.7-2.6-9 3.5-3.5c1.1-1.1 1.5-2.9.5-3.9z"></path></svg></div>
<p class="wy-hasla">Wsiadasz do samolotu<br>i opuszczasz</p>
<h1 class="wy-tytul">THE ABYSS<em>.</em></h1>
<div class="wy-pa"><b>Do zobaczenia!</b><svg viewBox="0 0 360 70" fill="none"><path d="M0 64C90 30 210 12 352 10" stroke="#ff3d5e" stroke-width="3.5" stroke-linecap="round"></path></svg></div>
<div class="wy-msg szklo"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M21 16v-2l-8-5V3.5a1.5 1.5 0 00-3 0V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5z" transform="rotate(90 12 12)"></path></svg><p>Dziękujemy, że byłeś częścią naszego świata.<br>Do zobaczenia wkrótce – być może znowu w The Abyss...</p></div>
</section>
<div class="wy-akcja"><a class="btn gl szklo" href="index.php#zaloguj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M7 4.5v15l12-7.5z"></path></svg>Zaloguj ponownie<span class="rog"></span></a></div>
</main>
<script src="js/start.js"></script>
<script>
(function () {
  // Tablica odlotów: co kilka sekund podmienia jedno miasto
  var MIASTA = ['Stockholm', 'Prague', 'Lisbon', 'Vienna', 'Berlin', 'Paris', 'Rome', 'Oslo', 'Amsterdam', 'Warsaw', 'Tokyo', 'Kraków', 'Copenhagen', 'Madrid', 'Dublin'];
  var el = [].slice.call(document.querySelectorAll('#wyTablica .m'));
  var i = el.length, wiersz = 0;
  el[el.length - 1].textContent = MIASTA[i - 1];
  function nastepne() {
    var m = el[wiersz];
    m.classList.add('kl2');
    setTimeout(function () { m.textContent = MIASTA[i++ % MIASTA.length]; m.classList.remove('kl2'); }, 260);
    wiersz = (wiersz + 1) % el.length;
  }
  if (!matchMedia('(prefers-reduced-motion: reduce)').matches) setInterval(nastepne, 2400);
})();
</script>
</body>
</html>
