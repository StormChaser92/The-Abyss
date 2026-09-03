<?php
require_once "db.php";
$id_gracza = $_SESSION['id_gracza'];

$komunikat = "";

// 1. OBSŁUGA ZAPISU WYKADROWANEGO AWATARA (CROPPER.JS)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['zapisz_avatar'])) {
    $cropped_image = $_POST['cropped_image']; 

    if (!empty($cropped_image)) {
        // Usuwamy nagłówek Base64, aby uzyskać czyste dane obrazu PNG
        $image_parts = explode(";base64,", $cropped_image);
        $image_base64 = base64_decode($image_parts[1]);
        
        // Sprawdzamy czy folder istnieje, jak nie, to go tworzymy
        $folder = 'uploads/avatars';
        if (!file_exists($folder)) {
            mkdir($folder, 0777, true);
        }
        
        // Tworzymy unikalną nazwę i zapisujemy plik na serwerze
        $nazwa_pliku = $folder . '/avatar_' . $id_gracza . '_' . time() . '.png';
        file_put_contents($nazwa_pliku, $image_base64);

        // Aktualizacja bazy danych
        $polaczenie->query("UPDATE gracze SET avatar = '$nazwa_pliku' WHERE id = $id_gracza");
        
        $komunikat = "<div class='sukces'>Twój awatar został idealnie wykadrowany (portret) i zaktualizowany! (Może być konieczne odświeżenie strony F5).</div>";
    } else {
        $komunikat = "<div class='blad'>Najpierw wybierz i wykadruj zdjęcie!</div>";
    }
}

// POBIERANIE DANYCH DO USTAWIEŃ
$wynik = $polaczenie->query("SELECT avatar, tryb_pacyfisty FROM gracze WHERE id=$id_gracza");
$gracz = $wynik->fetch_assoc();

// Zaktualizowany placeholder na pionowy
$aktualny_avatar = !empty($gracz['avatar']) ? htmlspecialchars($gracz['avatar']) : 'https://via.placeholder.com/500x625/111/333?text=Portret';

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
            <div class="podglad-avatara" style="background-image: url('<?php echo $aktualny_avatar; ?>');"></div>
            
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