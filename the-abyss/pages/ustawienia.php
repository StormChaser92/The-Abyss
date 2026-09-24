<?php
require_once "db.php";
require_once __DIR__ . "/../includes/avatar.php";
$id_gracza = (int)$_SESSION['id_gracza'];

$komunikat = "";

// 1. OBSŁUGA ZAPISU WYKADROWANEGO AWATARA (CROPPER.JS)
// Cropper przysyła 500×625 jako data:image/png;base64. Sprawdzamy, że to naprawdę obraz,
// zapisujemy do the-abyss/uploads/avatars/ (ścieżka od pliku, nie od bieżącego katalogu)
// i zmieniamy bazę dopiero, gdy plik leży na dysku.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['zapisz_avatar'])) {
    $cropped_image = (string)($_POST['cropped_image'] ?? '');
    $dane = null;
    if (preg_match('~^data:image/(png|jpeg|webp);base64,~', $cropped_image)) {
        $dane = base64_decode(substr($cropped_image, strpos($cropped_image, ',') + 1), true);
    }
    $info = $dane ? @getimagesizefromstring($dane) : false;

    if ($cropped_image === '') {
        $komunikat = "<div class='blad'>Najpierw wybierz i wykadruj zdjęcie! Jeśli wybrałeś zdjęcie, a widzisz ten komunikat, plik był za duży dla serwera (limit post_max_size w php.ini).</div>";
    } elseif (!$info) {
        $komunikat = "<div class='blad'>To nie wygląda na obraz. Spróbuj innego pliku.</div>";
    } else {
        $katalog = dirname(__DIR__) . '/uploads/avatars';
        if (!is_dir($katalog) && !@mkdir($katalog, 0775, true)) {
            $komunikat = "<div class='blad'>Serwer nie może utworzyć folderu uploads/avatars. Załóż go ręcznie w katalogu the-abyss.</div>";
        } else {
            $nazwa = 'avatar_' . $id_gracza . '_' . time();
            $zapisane = false;
            // JPEG 88% waży kilka razy mniej niż PNG z croppera — szybciej ładuje się w podglądach
            if (function_exists('imagecreatefromstring') && ($img = @imagecreatefromstring($dane))) {
                $plik = "uploads/avatars/$nazwa.jpg";
                $zapisane = imagejpeg($img, dirname(__DIR__) . '/' . $plik, 88);
                imagedestroy($img);
            }
            if (!$zapisane) {
                $plik = "uploads/avatars/$nazwa.png";
                $zapisane = file_put_contents(dirname(__DIR__) . '/' . $plik, $dane) > 0;
            }
            if (!$zapisane) {
                $komunikat = "<div class='blad'>Nie udało się zapisać pliku na dysku. Sprawdź uprawnienia folderu uploads/avatars.</div>";
            } else {
                $st = $polaczenie->prepare("UPDATE gracze SET avatar = ? WHERE id = ?");
                $st->bind_param('si', $plik, $id_gracza);
                $st->execute();
                $komunikat = "<div class='sukces'>Twój awatar został wykadrowany (portret) i zaktualizowany!</div>";
            }
        }
    }
}

// POBIERANIE DANYCH DO USTAWIEŃ
$wynik = $polaczenie->query("SELECT avatar, tryb_pacyfisty FROM gracze WHERE id=$id_gracza");
$gracz = $wynik->fetch_assoc();

$aktualny_avatar = htmlspecialchars(avatar_url($gracz['avatar'] ?? ''), ENT_QUOTES);

if (isset($_GET['diag'])) {
    $root = dirname(__DIR__);
    $wpis = (string)($gracz['avatar'] ?? '');
    $p2 = ltrim(str_replace('\\', '/', $wpis), '/'); $pl = basename($p2);
    $kat = $root . '/uploads/avatars';
    echo "<pre style='background:#000;color:#0f0;padding:12px;border:1px solid #f33;white-space:pre-wrap;font-size:13px'>";
    echo "DIAGNOSTYKA AWATARA\n";
    echo "wpis w bazie:     " . htmlspecialchars(var_export($wpis, true)) . "\n";
    echo "katalog gry:      " . htmlspecialchars($root) . "\n";
    echo "avatar_url():     " . ($aktualny_avatar ?: '(pusto — pliku nie znaleziono)') . "\n";
    echo "URL strony:       " . htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') . "\n\n";
    foreach ([$p2, 'uploads/' . $p2, 'uploads/avatars/' . $pl, 'avatars/' . $pl] as $k) {
        $f = $root . '/' . $k;
        echo str_pad(htmlspecialchars($k), 60) . (is_file($f) ? 'JEST (' . filesize($f) . ' B)' : 'brak') . "\n";
    }
    echo "\nuploads/avatars istnieje: " . (is_dir($kat) ? 'tak' : 'NIE') . ", zapisywalny: " . (is_writable($kat) ? 'tak' : 'NIE') . "\n";
    echo "GD (imagejpeg):   " . (function_exists('imagejpeg') ? 'tak' : 'NIE') . "\n";
    echo "post_max_size:    " . ini_get('post_max_size') . "\n";
    if (is_dir($kat)) { echo "\npliki w uploads/avatars:\n"; foreach (array_slice(scandir($kat), 2) as $x) echo "  $x (" . filesize("$kat/$x") . " B)\n"; }
    echo "</pre>";
}

// 2. LOGIKA ZMIANY TRYBU NIETYKALNOŚCI (PACYFISTA)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['zmien_tryb'])) {
    $nowy_tryb = $gracz['tryb_pacyfisty'] == 1 ? 0 : 1;
    $polaczenie->query("UPDATE gracze SET tryb_pacyfisty = $nowy_tryb WHERE id = $id_gracza"); 
    echo "<script>alert('Status nietykalności został zaktualizowany!'); window.location.href='game.php?page=ustawienia';</script>";
    exit;
}
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

<style>
    .ustawienia-panel { background: #111; border: 1px solid #333; padding: 25px; border-radius: 4px; margin-bottom: 20px; }
    .ustawienia-panel h2 { color: #00ff00; font-family: 'Oswald'; text-transform: uppercase; margin-top: 0; border-bottom: 1px solid #222; padding-bottom: 10px; }
    
    .input-file { width: 100%; padding: 12px; background: #050505; border: 1px solid #444; color: #fff; border-radius: 3px; font-size: 1em; margin-bottom: 15px; box-sizing: border-box; cursor: pointer; }
    
    .btn-zapisz { background: transparent; color: #00ff00; border: 1px solid #00ff00; padding: 10px 25px; font-family: 'Oswald'; cursor: pointer; text-transform: uppercase; transition: 0.3s; font-size: 1.1em; border-radius: 3px; width: 100%; }
    .btn-zapisz:hover { background: #00ff00; color: #000; }

    .btn-akcja { background: #0066cc; color: #fff; border: 1px solid #00aaff; padding: 10px 25px; font-family: 'Oswald'; cursor: pointer; text-transform: uppercase; border-radius: 3px; transition: 0.3s; font-size: 1.1em; }
    .btn-akcja:hover { background: #00aaff; color: #000; }

    .sukces { background: rgba(0,255,0,0.1); border: 1px solid #00ff00; color: #00ff00; padding: 15px; margin-bottom: 15px; text-align: center; font-weight: bold; }
    .blad { background: rgba(255,51,51,0.1); border: 1px solid #ff3333; color: #ff3333; padding: 15px; margin-bottom: 15px; text-align: center; font-weight: bold; }
    .info-tekst { color: #888; font-size: 0.9em; margin-bottom: 20px; line-height: 1.6; }

    /* CSS DO CROPPERA */
    .cropper-wrapper { display: flex; gap: 30px; flex-wrap: wrap; margin-bottom: 20px; }
    .cropper-left { flex: 1; min-width: 200px; max-width: 300px; }
    .cropper-right { flex: 2; min-width: 300px; }
    
    .img-container { max-width: 100%; max-height: 500px; display: none; background: #000; border: 1px dashed #555; margin-bottom: 20px; }
    .img-container img { display: block; max-width: 100%; }
    
    /* ZAKTUALIZOWANE NA WYSOKI PORTRET (5:6.25) */
    .podglad-avatara { width: 100%; max-width: 250px; aspect-ratio: 500/625; background-color: #050505; background-size: cover; background-repeat: no-repeat; background-position: center; border: 1px solid #444; border-radius: 3px; margin-bottom: 15px; box-shadow: inset 0 0 15px #000; }
    
    /* ZAKTUALIZOWANE NA WYSOKI PORTRET (Podgląd na żywo) */
    .img-preview { width: 100%; max-width: 250px; height: 312.5px; overflow: hidden; border: 1px solid #00ccff; background: #050505; border-radius: 3px; box-shadow: 0 0 15px rgba(0,204,255,0.1); margin-bottom: 20px; }
</style>

<h1 class="witaj">Ustawienia Konta</h1>

<?php echo $komunikat; ?>

<div class="ustawienia-panel">
    <h2>Zarządzanie Wizerunkiem (Portret)</h2>
    <p class="info-tekst">Wgraj zdjęcie ze swojego urządzenia. Optymalne, zalecane minimum to <b>szerokość 500px i wysokość 625px</b> (wysoki portret). System uruchomi narzędzie, które pozwoli Ci idealnie wykadrować postać z zachowaniem tych proporcji.</p>
    
    <div class="cropper-wrapper">
        <div class="cropper-left">
            <b style="color: #888; display: block; margin-bottom: 10px; text-transform: uppercase;">Aktualny wizerunek:</b>
            <div class="podglad-avatara"<?php if ($aktualny_avatar): ?> style="background-image: url('<?php echo $aktualny_avatar; ?>');"<?php endif; ?>></div>
            
            <input type="file" id="imageInput" class="input-file" accept="image/png, image/jpeg, image/gif, image/webp">
        </div>

        <div class="cropper-right">
            <div class="img-container" id="cropper-container">
                <img id="imageToCrop" src="">
            </div>
            
            <div id="preview-section" style="display: none;">
                <b style="color: #00ccff; display: block; margin-bottom: 10px; text-transform: uppercase; font-family: 'Oswald';">Podgląd portretu na żywo:</b>
                <div class="img-preview"></div>
            </div>

            <form method="POST" id="avatarForm" style="display: none;">
                <input type="hidden" name="cropped_image" id="cropped_image_data">
                <input type="hidden" name="zapisz_avatar" value="1">
                <button type="submit" name="zapisz_avatar" class="btn-zapisz">Zapisz Wykadrowany Portret</button>
            </form>
        </div>
    </div>
</div>

<div class="ustawienia-panel" style="border-color: #0066cc;">
    <h2 style="color: #00aaff; border-bottom-color: #004488;">🛡️ Tryb Nietykalności (PvP)</h2>
    <p class="info-tekst">Włączenie tego trybu sprawi, że nikt nie będzie mógł zaatakować Cię na ulicy ani Cię okraść. Z drugiej strony, Ty również stracisz możliwość inicjowania walk i napadów na innych obywateli.</p>
    
    <div style="margin-bottom: 20px; font-size: 1.1em;">
        Twój obecny status: 
        <?php if ($gracz['tryb_pacyfisty'] == 1): ?>
            <b style="color: #00ccff;">WŁĄCZONY (Jesteś bezpieczny)</b>
        <?php else: ?>
            <b style="color: #ff3333;">WYŁĄCZONY (Można Cię zaatakować)</b>
        <?php endif; ?>
    </div>
    
    <form method="POST" style="margin: 0;">
        <button type="submit" name="zmien_tryb" class="btn-akcja">Przełącz Tryb</button>
    </form>
</div>

<script>
    const imageInput = document.getElementById('imageInput');
    const imageToCrop = document.getElementById('imageToCrop');
    const cropperContainer = document.getElementById('cropper-container');
    const avatarForm = document.getElementById('avatarForm');
    const croppedImageData = document.getElementById('cropped_image_data');
    const previewSection = document.getElementById('preview-section');
    let cropper;

    imageInput.addEventListener('change', function (e) {
        const files = e.target.files;
        if (files && files.length > 0) {
            const file = files[0];
            
            // Limit wielkości pliku (opcjonalnie, np. 10MB dla dużych portretów)
            if(file.size > 10 * 1024 * 1024) {
                alert('Plik jest za duży! Maksymalna waga to 10MB.');
                return;
            }

            const reader = new FileReader();
            reader.onload = function (event) {
                
                // Sprawdzanie naturalnych wymiarów obrazka przed załadowaniem
                const imgCheck = new Image();
                imgCheck.onload = function() {
                    // ZMIENIONE MINIMUM NA PORTRET: 500x625
                    if (this.width < 500 || this.height < 625) {
                        alert('UWAGA: Twoje zdjęcie jest mniejsze niż zalecane minimum 500x625 pikseli. Po wykadrowaniu i powiększeniu portret może być rozmazany.');
                    }

                    // Wrzucanie obrazka do widoku
                    imageToCrop.src = event.target.result;
                    cropperContainer.style.display = 'block';
                    previewSection.style.display = 'block';
                    avatarForm.style.display = 'block';

                    if (cropper) {
                        cropper.destroy();
                    }

                    // Konfiguracja Cropper.js (ZMIENIONE PROPORCJE NA PORTRET: 500 / 625)
                    cropper = new Cropper(imageToCrop, {
                        aspectRatio: 500 / 625, 
                        preview: '.img-preview',
                        viewMode: 1, 
                        dragMode: 'move', 
                        autoCropArea: 1,
                        restore: false,
                        guides: true,
                        center: true,
                        highlight: false,
                        cropBoxMovable: false, 
                        cropBoxResizable: false, 
                        toggleDragModeOnDblclick: false,
                    });
                };
                imgCheck.src = event.target.result;
            };
            reader.readAsDataURL(file);
        }
    });

    avatarForm.addEventListener('submit', function (e) {
        e.preventDefault(); 
        
        if (cropper) {
            // Skrypt wymusza na wyjściu DOKŁADNIE 500x625 (pionowy)
            const canvas = cropper.getCroppedCanvas({
                width: 500,
                height: 625,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });
            
            // Zamiana wyciętego kawałka na format PNG Base64 i wysłanie do PHP
            const base64Image = canvas.toDataURL('image/png');
            croppedImageData.value = base64Image;
            
            this.submit();
        }
    });
</script>