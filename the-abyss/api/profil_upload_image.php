<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['zalogowany']) || $_SESSION['zalogowany'] !== true || empty($_SESSION['id_gracza'])) {
    echo json_encode(['ok' => false, 'msg' => 'Brak autoryzacji.']);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/profil_sanitizer.php';

$id_gracza = (int)$_SESSION['id_gracza'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Nieprawidłowa metoda.']);
    exit;
}

if (empty($_FILES['obrazek']) || $_FILES['obrazek']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'msg' => 'Nie wybrano obrazka albo upload się nie powiódł.']);
    exit;
}

$file = $_FILES['obrazek'];
$max_size = 8 * 1024 * 1024; // 8 MB na pojedynczy obrazek inline
if ($file['size'] > $max_size) {
    echo json_encode(['ok' => false, 'msg' => 'Obrazek jest za duży. Maksymalnie 8 MB.']);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

if (!isset($allowed[$mime])) {
    echo json_encode(['ok' => false, 'msg' => 'Dozwolone formaty: JPG, PNG, GIF, WEBP.']);
    exit;
}

$base_dir = __DIR__ . '/../uploads/profile_inline/' . $id_gracza;
$public_dir = 'uploads/profile_inline/' . $id_gracza;
if (!is_dir($base_dir) && !mkdir($base_dir, 0775, true)) {
    echo json_encode(['ok' => false, 'msg' => 'Nie udało się utworzyć folderu uploadu.']);
    exit;
}

$ext = $allowed[$mime];
$name = 'story_' . $id_gracza . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$target = $base_dir . '/' . $name;
$public_path = $public_dir . '/' . $name;

if (!move_uploaded_file($file['tmp_name'], $target)) {
    echo json_encode(['ok' => false, 'msg' => 'Nie udało się zapisać pliku.']);
    exit;
}

// Rejestr w bazie jest opcjonalny — endpoint działa też, jeśli tabela nie została jeszcze utworzona.
if (abyss_table_exists($polaczenie, 'profile_images')) {
    $orig = $file['name'] ?? '';
    $stmt = $polaczenie->prepare("INSERT INTO profile_images (gracz_id, sciezka, original_name) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('iss', $id_gracza, $public_path, $orig);
        $stmt->execute();
    }
}

echo json_encode([
    'ok' => true,
    'path' => $public_path,
    'html' => '<figure class="story-figure"><img src="' . htmlspecialchars($public_path, ENT_QUOTES, 'UTF-8') . '" alt=""><figcaption>Podpis obrazka</figcaption></figure>'
]);
