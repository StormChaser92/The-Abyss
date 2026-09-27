<?php
/* the-abyss/includes/csrf.php — dołączany przez db.php, więc działa na każdej stronie i w api/.
   - Token jeden na sesję: $_SESSION['csrf'].
   - Każdy POST zalogowanego gracza musi mieć token: pole _csrf albo nagłówek X-CSRF-Token.
     Logowanie i rejestracja (bez sesji gracza) są poza kontrolą.
   - csrf_wstaw() — callback ob_start w game.php i creator.php: dopisuje ukryte pole do każdego
     <form method="post">, meta z tokenem i skrypt, który dokłada nagłówek do fetch/XHR
     i pole do formularzy tworzonych w JS.
   - Zły token: api/ dostaje JSON 419, strona wraca na poprzedni adres z komunikatem. */

if (!function_exists('csrf_token')) {

function csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) return '';
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_pole(): string { return '<input type=hidden name=_csrf value=' . csrf_token() . '>'; }

function csrf_sprawdz(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['zalogowany'])) return;
    if (in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), ['login.php', 'register.php', 'index.php'], true)) return;
    $dany = (string)($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if ($dany !== '' && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $dany)) return;

    $msg = 'Sesja formularza wygasła, spróbuj ponownie.';
    $api = strpos(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/api/') !== false
        || stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
        || !empty($_SERVER['HTTP_X_CSRF_TOKEN']);
    if ($api) {
        http_response_code(419);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'msg' => $msg, 'csrf' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $_SESSION['csrf_flash'] = $msg;
    $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    $cel = ($ref !== '' && parse_url($ref, PHP_URL_HOST) === parse_url('http://' . $host, PHP_URL_HOST)) ? $ref : 'game.php';
    while (ob_get_level() > 0) ob_end_clean();
    header('Location: ' . $cel, true, 303);
    exit;
}

function csrf_wstaw(string $html): string {
    $t = csrf_token();
    if ($t === '' || stripos($html, '<form') === false && stripos($html, '<head') === false) return $html;
    $html = preg_replace('/<form\b[^>]*\bmethod\s*=\s*["\']?post\b[^>]*>/i', '$0' . csrf_pole(), $html);
    $js = "<meta name=\"csrf-token\" content=\"$t\"><script>(function(){var T='$t';"
        . "var wlasny=function(u){try{return new URL(u,location.href).origin===location.origin}catch(e){return true}};"
        . "var of=window.fetch;if(of)window.fetch=function(i,o){o=o||{};var m=(o.method||(i&&i.method)||'GET').toUpperCase();var u=typeof i==='string'?i:(i&&i.url)||'';"
        . "if(m==='POST'&&wlasny(u)){var h=new Headers(o.headers||(i&&i.headers)||{});h.set('X-CSRF-Token',T);o.headers=h;}return of.call(this,i,o)};"
        . "var oo=XMLHttpRequest.prototype.open,os=XMLHttpRequest.prototype.send;"
        . "XMLHttpRequest.prototype.open=function(m,u){this._cm=String(m).toUpperCase();this._cu=u;return oo.apply(this,arguments)};"
        . "XMLHttpRequest.prototype.send=function(){if(this._cm==='POST'&&wlasny(this._cu))this.setRequestHeader('X-CSRF-Token',T);return os.apply(this,arguments)};"
        . "var dodaj=function(f){if(f&&String(f.method).toLowerCase()==='post'&&!f.querySelector('input[name=_csrf]')){var x=document.createElement('input');x.type='hidden';x.name='_csrf';x.value=T;f.appendChild(x)}};"
        . "document.addEventListener('submit',function(e){dodaj(e.target)},true);"
        . "var fs=HTMLFormElement.prototype.submit;HTMLFormElement.prototype.submit=function(){dodaj(this);return fs.apply(this,arguments)};"
        . "})();</script>";
    $html = preg_replace('/<head\b[^>]*>/i', '$0' . $js, $html, 1);
    if (!empty($GLOBALS['CSRF_FLASH'])) {
        $toast = "<div id=\"csrf-toast\" style=\"position:fixed;top:18px;left:50%;transform:translateX(-50%);z-index:99999;background:rgba(10,6,12,.96);border:1px solid #ff1744;color:#ff3d5e;padding:12px 20px;font-family:Oswald,sans-serif;letter-spacing:1.5px;box-shadow:0 0 24px rgba(255,23,68,.45)\" onclick=\"this.remove()\">⚠ "
               . htmlspecialchars($GLOBALS['CSRF_FLASH'], ENT_QUOTES, 'UTF-8') . "</div><script>setTimeout(function(){var t=document.getElementById('csrf-toast');if(t)t.remove()},6000)</script>";
        $html = preg_replace('/<body\b[^>]*>/i', '$0' . $toast, $html, 1);
    }
    return $html;
}

}

csrf_sprawdz();
if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['csrf_flash']) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $GLOBALS['CSRF_FLASH'] = $_SESSION['csrf_flash'];
    unset($_SESSION['csrf_flash']);
}
