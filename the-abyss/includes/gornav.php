<?php
// Górna belka skrótów (2 rzędy). Wymaga: $aktualna, $zakladka, $n_poczta, $n_alerty, $n_zlecenia.
$gn_ikony = [
 'poczta'  => '<rect x="3" y="5" width="18" height="14" rx="1"/><path d="M3 6l9 7 9-7"/>',
 'alerty'  => '<path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
 'klub'    => '<circle cx="9" cy="8" r="3"/><circle cx="16.5" cy="9" r="2.5"/><path d="M3 19c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M15 14c3 0 6 1.8 6 5"/>',
 'centrum' => '<path d="M12 6c-2-1.3-5-1.8-8-1.5V18c3-.3 6 .2 8 1.5 2-1.3 5-1.8 8-1.5V4.5c-3-.3-6 .2-8 1.5z"/><path d="M12 6v13.5"/><path d="M6.5 8.5h3M6.5 11.5h3M14.5 8.5h3M14.5 11.5h3"/>',
 'zlecenia'=> '<rect x="3" y="7" width="18" height="12" rx="1"/><path d="M9 7V5h6v2"/><path d="M3 12h18"/><path d="M11 12v2h2v-2"/>',
 'uni'     => '<path d="M2 9l10-5 10 5-10 5z"/><path d="M6 11v4.5c0 1.4 2.7 3 6 3s6-1.6 6-3V11"/><path d="M21 9.5V15"/>',
 'lotnisko'=> '<path d="M21 3L3 10.5l7 2.5 2.5 7z"/><path d="M21 3L10 13"/>',
 'doki'    => '<rect x="2.5" y="4" width="8" height="6.5" rx="2" transform="rotate(35 6.5 7.25)"/><path d="M8.5 11.5l5 7"/><rect x="13.5" y="4" width="8" height="6.5" rx="2" transform="rotate(-35 17.5 7.25)"/><path d="M15.5 11.5l-5 7"/>',
 'karta'   => '<rect x="3" y="4.5" width="18" height="15" rx="1.5"/><circle cx="9" cy="10.5" r="2.5"/><path d="M5.5 16.5c.6-1.8 2-2.8 3.5-2.8s2.9 1 3.5 2.8"/><path d="M14.5 9.5h4M14.5 12.5h4M14.5 15.5h2.5"/>',
 'ust'     => '<circle cx="12" cy="12" r="3"/><path d="M12 2.5v3M12 18.5v3M2.5 12h3M18.5 12h3M5.3 5.3l2.1 2.1M16.6 16.6l2.1 2.1M5.3 18.7l2.1-2.1M16.6 7.4l2.1-2.1"/><circle cx="12" cy="12" r="6.5"/>',
 'ekw'     => '<path d="M7 8V6a5 5 0 0 1 10 0v2"/><rect x="4" y="8" width="16" height="13" rx="2"/><path d="M9 13h6v3H9z"/>',
 'sklep'   => '<path d="M5 8h14l-1 12H6z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/><path d="M9 12h6"/>',
 'mieszk'  => '<path d="M3 11l9-7 9 7"/><path d="M5 9.5V20h14V9.5"/><path d="M10 20v-6h4v6"/>',
 'bank'    => '<path d="M3 9l9-5 9 5z"/><path d="M5 9v9M9.5 9v9M14.5 9v9M19 9v9"/><path d="M3 20h18"/>',
 'klinika' => '<path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6z"/>',
 'zlom'    => '<path d="M14.5 6.5a4 4 0 0 0-5.3 5.3L3.5 17.5l3 3 5.7-5.7a4 4 0 0 0 5.3-5.3l-2.5 2.5-2.5-.5-.5-2.5z"/>',
 'synd'    => '<path d="M5 21V3"/><path d="M5 4h12l-2.5 4L17 12H5"/>',
 'kasyno'  => '<rect x="3" y="7" width="11" height="11" rx="2" transform="rotate(-12 8.5 12.5)"/><circle cx="6.5" cy="10.5" r=".6"/><circle cx="10" cy="14" r=".6"/><rect x="12" y="4" width="9" height="9" rx="2" transform="rotate(14 16.5 8.5)"/><circle cx="16.5" cy="8.5" r=".6"/>',
 'premium' => '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
];
// [etykieta, url, ikona, aktywny, licznik, klasa licznika, klasa pozycji]
$gn_r1 = [
 ['POCZTA','game.php?page=poczta','poczta',$aktualna=='poczta'&&$zakladka!='alerty',(int)$n_poczta,'',((int)$n_poczta>0?' neon-zepsuty':'')],
 ['POWIADOMIENIA','game.php?page=poczta&zakladka=alerty','alerty',$aktualna=='poczta'&&$zakladka=='alerty',(int)$n_alerty,' ember',''],
 ['KLUB','game.php?page=czat','klub',$aktualna=='czat',0,'',''],
 ['CENTRUM OPOWIEŚCI','game.php?page=centrum','centrum',in_array($aktualna,['centrum','rangi','przeglad','pokoj_sesji','moderacja']),0,'',''],
 ['ZLECENIA','game.php?page=zlecenia','zlecenia',$aktualna=='zlecenia',(int)($n_zlecenia ?? 0),' ember',''],
 ['UNIWERSYTET','game.php?page=uniwersytet','uni',$aktualna=='uniwersytet',0,'',''],
 ['LOTNISKO','game.php?page=lotnisko','lotnisko',$aktualna=='lotnisko',0,'',''],
 ['DOKI','game.php?page=doki','doki',$aktualna=='doki',0,'',''],
 ['KARTA POSTACI','game.php?page=karta','karta',$aktualna=='karta',0,'',''],
 ['USTAWIENIA','game.php?page=ustawienia','ust',$aktualna=='ustawienia',0,'',''],
];
$gn_r2 = [
 ['EKWIPUNEK','game.php?page=ekwipunek','ekw',$aktualna=='ekwipunek',0,'',''],
 ['SKLEP','game.php?page=sklep','sklep',$aktualna=='sklep',0,'',''],
 ['MIESZKANIE','game.php?page=mieszkanie','mieszk',$aktualna=='mieszkanie',0,'',''],
 '|',
 ['BANK','game.php?page=bank','bank',$aktualna=='bank',0,'',''],
 ['KLINIKA','game.php?page=szpital','klinika',$aktualna=='szpital',0,'',''],
 ['ZŁOMOWISKO','game.php?page=zlomowisko','zlom',$aktualna=='zlomowisko',0,'',''],
 ['SYNDYKATY','game.php?page=syndykaty','synd',$aktualna=='syndykaty',0,'',''],
 '|',
 ['KASYNO','game.php?page=kasyno','kasyno',$aktualna=='kasyno',0,'',''],
 '|',
 ['PREMIUM','game.php?page=premium','premium',$aktualna=='premium',0,'',' gold'],
];
if (!function_exists('gn_item')) {
    function gn_item($p, $ikony) {
        [$et,$url,$ik,$akt,$licz,$bcls,$cls] = $p;
        $h  = '<a href="'.htmlspecialchars($url, ENT_QUOTES).'" class="gn-item'.$cls.($akt?' aktywny':'').'"'.($akt?' aria-current="page"':'').'>';
        $h .= '<svg class="gn-fala" aria-hidden="true"><rect class="gn-tor" pathLength="100"/><rect class="gn-iskra" pathLength="100"/><rect class="gn-rdzen" pathLength="100"/></svg>';
        $h .= '<span class="gn-ikona"><svg viewBox="0 0 24 24" aria-hidden="true">'.$ikony[$ik].'</svg>';
        if ($licz > 0) $h .= '<b class="gn-licznik'.$bcls.'">'.($licz>99?'99+':$licz).'</b>';
        $h .= '</span><span class="gn-et">'.$et.'</span></a>';
        return $h;
    }
}
?>
<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>
<filter id="gn-iskra" x="-10%" y="-30%" width="120%" height="160%">
<feTurbulence type="fractalNoise" baseFrequency="0.09" numOctaves="2" seed="1" result="szum"><animate attributeName="seed" values="1;7;3;9;5;2;8" dur="0.45s" calcMode="discrete" repeatCount="indefinite"/></feTurbulence>
<feDisplacementMap in="SourceGraphic" in2="szum" scale="3.5" xChannelSelector="R" yChannelSelector="G"/>
</filter>
</defs></svg>
<header class="gornav">
<div class="gn-r1">
<nav class="gn-lista"><?php foreach ($gn_r1 as $p) echo gn_item($p, $gn_ikony); ?></nav>
<a href="logout.php" class="gn-wyloguj" title="Wyloguj się" aria-label="Wyloguj się"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v8"/><path d="M6.3 6.6a8 8 0 1 0 11.4 0"/></svg></a>
</div>
<nav class="gn-r2"><?php foreach ($gn_r2 as $p) echo $p==='|' ? '<span class="gn-sep" aria-hidden="true"></span>' : gn_item($p, $gn_ikony); ?></nav>
</header>
