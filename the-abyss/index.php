<?php
/* the-abyss/index.php — strona startowa: logowanie i rejestracja w szklanym panelu.
   Formularze wysyłają do login.php / register.php, które wracają tu z komunikatem ($_SESSION['start_msg']).
   Wygląd: css/start.css, js/start.js, tło img/start_bg.jpg (1672×941). */
session_start();
require_once "db.php";

function st_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function st_msg($m, $gdzie) { if ($m && ($m['widok'] ?? '') === $gdzie) echo '<div class="st-msg ' . ($m['typ'] === 'ok' ? 'ok' : 'blad') . '">' . st_h($m['tekst']) . '</div>'; }
function st_licz($db, $sql) { try { $w = $db->query($sql); return $w ? (int)($w->fetch_row()[0] ?? 0) : 0; } catch (Throwable $e) { return 0; } }

$zalogowany = !empty($_SESSION['zalogowany']);
$msg = $_SESSION['start_msg'] ?? null;
unset($_SESSION['start_msg']);
$widok = $msg['widok'] ?? 'start';

$st_online = number_format(st_licz($polaczenie, "SELECT COUNT(*) FROM gracze WHERE ostatnia_aktywnosc >= NOW() - INTERVAL 15 MINUTE"), 0, ',', ' ');
$st_gracze = number_format(st_licz($polaczenie, "SELECT COUNT(*) FROM gracze"), 0, ',', ' ');
// Nowe opowieści: zaakceptowane, niezakończone, z ruchem w ostatnich 7 dniach
$st_nowe   = number_format(st_licz($polaczenie, "SELECT COUNT(*) FROM sesje_rpg WHERE status <> 'Zakończona' AND akceptacja = 'ok' AND ostatnia_aktywnosc >= NOW() - INTERVAL 7 DAY"), 0, ',', ' ');
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>The Abyss — New York Roleplay</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/start.css?v=1">
</head>
<body data-widok="<?php echo st_h($widok); ?>">
<div class="st-fill"></div>
<header class="st-nav" id="stNav">
<a class="st-logo" href="#" data-w="start"><b>THE ABYSS</b><i></i></a>
<nav class="st-menu"><a href="#" data-w="start">Świat</a><a href="#opowiesci" data-w="opowiesci">Opowieści</a><a href="#spolecznosc" data-w="spolecznosc">Społeczność</a><a href="#faq" data-w="faq">FAQ</a></nav>
<a class="st-login szklo" <?php echo $zalogowany ? 'href="game.php"' : 'href="#zaloguj" data-w="zaloguj"'; ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"></circle><path d="M4 21c1-4.5 4.5-6.5 8-6.5s7 2 8 6.5"></path></svg><?php echo $zalogowany ? 'Graj' : 'Zaloguj'; ?><span class="rog"></span></a>
</header>
<main class="st-stage" id="stStage">
<div class="st-bg"></div>
<div class="st-rain"></div>
<section class="st-panel szklo" id="stPanel">
<span class="kl lg"></span><span class="kl pg"></span><span class="kl ld"></span><span class="kl pd"></span><span class="kl pd2"></span>
<button class="st-zamknij szklo" id="stZamknij" data-w="start" title="Wróć" hidden>✕</button>

<div class="widok on" id="w-start">
<div class="st-kicker">New York <em>//</em> Roleplay MMORPG</div>
<h1 class="st-tytul">THE ABYSS</h1>
<div class="st-cios"></div>
<p class="st-hasla">Wejdź do świata Abyss<br>i zostań kim chcesz.</p>
<p class="st-opis">Stwórz własną postać, pisz swoją historię,<br>i żyj w mieście, w którym każda decyzja ma znaczenie.</p>
<div class="st-akcje">
<a class="btn gl szklo" <?php echo $zalogowany ? 'href="game.php"' : 'href="#zaloguj" data-w="zaloguj"'; ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M7 4.5v15l12-7.5z"></path></svg>Wejdź do gry<span class="rog"></span></a>
<a class="btn szklo" href="#rejestracja" data-w="rejestracja"><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="7.5" r="4.5"></circle><path d="M3 22c.6-5 4.3-8 9-8s8.4 3 9 8z"></path></svg>Stwórz postać<span class="rog"></span></a>
<a class="btn szklo" href="#faq" data-w="faq"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M2 5c3-1.3 6.3-1.2 9.2.6V21C8.3 19.3 5 19.2 2 20.5zM22 5c-3-1.3-6.3-1.2-9.2.6V21c2.9-1.7 6.2-1.8 9.2-.5z"></path></svg>Jak to działa<span class="rog"></span></a>
</div>
</div>

<div class="widok" id="w-opowiesci">
<div class="st-kicker">Centrum <em>//</em> Opowieści</div>
<h2 class="st-h2">Opowieści</h2>
<div class="st-lista">
<div class="st-karta szklo"><h3>Sesja</h3><p>Prowadzi ją Mistrz Gry. Zgłaszasz postać do naboru, a potem piszesz wpisy fabularne razem z innymi graczami.</p></div>
<div class="st-karta szklo"><h3>Wydarzenie w Klubie</h3><p>Opowieść rozgrywana na Sali Głównej Klubu The Abyss. Bankiet, koncert, porachunki przy barze.</p></div>
<div class="st-karta szklo"><h3>Opowieść swobodna</h3><p>Prywatna historia dla kilku postaci, bez Mistrza Gry. Rzuty i zasady ustalacie sami.</p></div>
<div class="st-karta szklo"><h3>NC · Non Clima</h3><p>Każda Opowieść ma zakładkę rozmów poza fabułą: ustalenia, pytania do prowadzącego, spostrzeżenia.</p></div>
</div>
</div>

<div class="widok" id="w-spolecznosc">
<div class="st-kicker">Ludzie <em>//</em> Miasto</div>
<h2 class="st-h2">Społeczność</h2>
<div class="st-lista">
<div class="st-karta szklo"><h3>Klub The Abyss</h3><p>Czat Sali Głównej i sal bocznych. Tu gracze się poznają, a barman pilnuje porządku.</p></div>
<div class="st-karta szklo"><h3>Syndykaty</h3><p>Zbierz lojalnych ludzi, załóż własną Rodzinę i buduj wpływy w mieście.</p></div>
<div class="st-karta szklo"><h3>Mistrzowie Gry</h3><p>Prowadzą sesje i wydarzenia. Rangę prowadzącego nadaje MG albo Adminka Fabularna.</p></div>
<div class="st-karta szklo"><h3>Zasady</h3><p>Moderację prowadzą MG i Adminka. Od każdej kary możesz się odwołać.</p></div>
</div>
</div>

<div class="widok" id="w-faq">
<div class="st-kicker">Pytania <em>//</em> Odpowiedzi</div>
<h2 class="st-h2">Jak to działa</h2>
<div class="st-faq">
<details name="faq" class="szklo" open><summary>Jak zacząć?</summary><p>Stwórz postać: wybierz imię, e-mail i hasło. Po pierwszym logowaniu kreator poprowadzi Cię przez pochodzenie i profesję. Potem wejdź do Centrum Opowieści albo do Klubu.</p></details>
<details name="faq" class="szklo"><summary>Jak pisać post fabularny?</summary><p>Narrację piszesz w gwiazdkach <code>*tak*</code>, dialog zwykłym tekstem. Działa też <code>**pogrubienie**</code>, <code>_kursywa_</code> i <code>@Nick</code>, żeby kogoś wspomnieć.</p></details>
<details name="faq" class="szklo"><summary>Czy jest kolejka tur?</summary><p>Nie. Piszesz swój wpis w dowolnym momencie, zgodnie z charakterem postaci. Rzuty i przebieg sceny ustala prowadzący.</p></details>
<details name="faq" class="szklo"><summary>Czym jest NC?</summary><p>Non Clima, zakładka rozmów poza fabułą w każdej Opowieści. Piszą w niej uczestnicy i obserwatorzy.</p></details>
</div>
</div>

<div class="widok" id="w-zaloguj">
<div class="st-kicker">Dostęp <em>//</em> Obywatel</div>
<h2 class="st-h2">Zaloguj</h2>
<?php st_msg($msg, 'zaloguj'); ?>
<form class="st-form" action="login.php" method="POST">
<label class="st-pole"><span>Imię postaci</span><input type="text" name="login" required autocomplete="username" value="<?php echo st_h($msg['login'] ?? ''); ?>"></label>
<label class="st-pole"><span>Hasło</span><input type="password" name="haslo" required autocomplete="current-password"></label>
<button type="submit" class="btn gl szklo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M7 4.5v15l12-7.5z"></path></svg>Wejdź do gry<span class="rog"></span></button>
<div class="st-przelacz">Nie masz postaci?<a href="#rejestracja" data-w="rejestracja">Stwórz postać</a></div>
</form>
</div>

<div class="widok" id="w-rejestracja">
<div class="st-kicker">Nowy <em>//</em> Obywatel</div>
<h2 class="st-h2">Stwórz postać</h2>
<?php st_msg($msg, 'rejestracja'); ?>
<form class="st-form" action="register.php" method="POST">
<label class="st-pole"><span>Imię postaci</span><input type="text" name="login" required minlength="3" maxlength="24" autocomplete="off"></label>
<label class="st-pole"><span>E-mail</span><input type="email" name="email" required autocomplete="email"></label>
<label class="st-pole"><span>Hasło</span><input type="password" name="haslo" required autocomplete="new-password"></label>
<button type="submit" class="btn gl szklo"><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="7.5" r="4.5"></circle><path d="M3 22c.6-5 4.3-8 9-8s8.4 3 9 8z"></path></svg>Stwórz postać<span class="rog"></span></button>
<div class="st-przelacz">Masz już postać?<a href="#zaloguj" data-w="zaloguj">Zaloguj</a></div>
</form>
</div>
</section>

<div class="st-staty szklo">
<span class="kl lg"></span><span class="kl pd"></span>
<div class="st-st"><span class="kropka"></span><div><small>Online</small><b><?php echo $st_online; ?></b></div></div>
<div class="st-st"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3.5"></circle><path d="M2.5 20c.5-3.8 3.2-6 6.5-6s6 2.2 6.5 6"></path><path d="M16 4.8a3.4 3.4 0 010 6.4M18 14.4c2 .8 3.2 2.8 3.5 5.6"></path></svg><div><small>Gracze</small><b><?php echo $st_gracze; ?></b></div></div>
<div class="st-st"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M5 2h10l4 4v16H5zM8 9v1.6h8V9zm0 4v1.6h8V13zm0 4v1.6h5V17z"></path></svg><div><small>Nowe opowieści</small><b class="cz"><?php echo $st_nowe; ?></b></div></div>
</div>
</main>
<footer class="st-stopka" id="stStopka"><span>The Abyss // New York</span><span>Kreuj / Pisz / Żyj</span></footer>
<script src="js/start.js?v=1"></script>
</body>
</html>
