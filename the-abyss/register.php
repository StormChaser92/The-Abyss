<?php
require_once "db.php";

$komunikat = ""; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login = $_POST['login'];
    $email = $_POST['email'];
    $haslo = $_POST['haslo'];

    $login = $polaczenie->real_escape_string($login);
    $email = $polaczenie->real_escape_string($email);
    
    $zaszyfrowane_haslo = password_hash($haslo, PASSWORD_DEFAULT);

    $sql = "INSERT INTO gracze (login, email, haslo) VALUES ('$login', '$email', '$zaszyfrowane_haslo')";

    if ($polaczenie->query($sql) === TRUE) {
        $komunikat = "<div class='sukces'>Obywatel pomyślnie zarejestrowany. Witamy w The Abyss. <br><br> <a href='login.php' class='btn'>Przejdź do logowania</a></div>";
    } else {
        $komunikat = "<div class='blad'>Błąd centrali: " . $polaczenie->error . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>The Abyss - Rejestracja</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Oswald:wght@700&display=swap" rel="stylesheet">
    <style>
        body {
            background-image: url('the-abyss.png'); 
            background-size: cover; 
            background-position: center; 
            background-repeat: no-repeat; 
            background-attachment: fixed; 
            color: #d1d1d1; 
            font-family: 'Open Sans', sans-serif; 
            text-align: center;
            margin: 0; 
            min-height: 100vh; 
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .ramka {
            border: 1px solid #333;
            padding: 40px 60px; 
            background-color: rgba(0, 0, 0, 0.6); 
            backdrop-filter: blur(5px); 
            -webkit-backdrop-filter: blur(5px);
            box-shadow: 0px 0px 25px rgba(0, 255, 0, 0.15); 
            border-radius: 8px;
            width: 400px; 
        }

        h2 {
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
            font-size: 2em;
            color: #fff;
            text-shadow: 0px 0px 10px #00ff00; 
            margin-top: 0;
            margin-bottom: 30px;
        }

        /* --- STYLE DLA PŁYWAJĄCYCH ETYKIET --- */
        .input-group {
            position: relative;
            margin-bottom: 25px;
            width: 100%;
        }

        .input-group input {
            width: 100%;
            padding: 15px;
            background-color: rgba(0, 0, 0, 0.7);
            border: 1px solid #444;
            color: #00ff00;
            font-family: 'Courier New', Courier, monospace;
            font-size: 16px;
            border-radius: 4px;
            box-sizing: border-box;
            transition: 0.3s;
        }

        .input-group input:focus {
            outline: none;
            border-color: #00ff00;
            box-shadow: 0 0 10px rgba(0, 255, 0, 0.3);
        }

        .input-group label {
            position: absolute;
            top: 15px;
            left: 15px;
            color: #aaa;
            font-size: 16px;
            transition: 0.3s ease;
            pointer-events: none; 
        }

        .input-group input:focus ~ label,
        .input-group input:valid ~ label {
            top: -10px;
            left: 10px;
            font-size: 12px;
            color: #00ff00;
            background-color: #111; 
            padding: 0 5px;
            border-radius: 3px;
        }
        /* ------------------------------------------- */

        .btn {
            background-color: transparent;
            color: #00ff00;
            font-family: 'Oswald', sans-serif;
            font-size: 1.1em;
            letter-spacing: 2px;
            padding: 12px 30px;
            border: 1px solid #00ff00;
            border-radius: 4px;
            text-decoration: none;
            text-transform: uppercase;
            transition: 0.3s;
            cursor: pointer;
            width: 100%;
            display: block;
            box-sizing: border-box;
            margin-top: 10px;
        }

        .btn:hover {
            background-color: #00ff00;
            color: #000;
            box-shadow: 0px 0px 20px rgba(0, 255, 0, 0.6);
        }

        .powrot {
            display: block;
            margin-top: 20px;
            color: #888;
            text-decoration: none;
            font-size: 0.9em;
        }
        
        .powrot:hover {
            color: #fff;
        }

        .sukces { color: #00ff00; border: 1px solid #00ff00; padding: 15px; margin-bottom: 20px; border-radius: 4px; background: rgba(0,255,0,0.1); }
        .blad { color: #ff3333; border: 1px solid #ff3333; padding: 15px; margin-bottom: 20px; border-radius: 4px; background: rgba(255,0,0,0.1); }
    </style>
</head>
<body>

    <div class="ramka">
        <h2>Rejestracja</h2>
        
        <?php if($komunikat != "") echo $komunikat; ?>

        <form action="register.php" method="POST">
            
            <div class="input-group">
                <input type="text" name="login" required autocomplete="off">
                <label>Imię Twojej postaci</label>
            </div>

            <div class="input-group">
                <input type="email" name="email" required autocomplete="off">
                <label>Szyfrowany Kanał (E-mail)</label>
            </div>

            <div class="input-group">
                <input type="password" name="haslo" required>
                <label>Klucz dostępu (Hasło)</label>
            </div>

            <button type="submit" class="btn">Wejdź do gry</button>
        </form>

        <a href="index.php" class="powrot">Wróć na ulice miasta</a>
    </div>

</body>
</html>