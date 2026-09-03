<?php
session_start();
require_once "db.php";

// Strażnik: Wpuszcza tylko zalogowanych, którzy nie mają jeszcze profesji
if (!isset($_SESSION['zalogowany'])) {
    header("Location: index.php");
    exit;
}

// Jeśli gracz kliknął "WEJDŹ DO MIASTA" (wysłał formularz)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['wybrana_profesja'])) {
    $profesja = $polaczenie->real_escape_string($_POST['wybrana_profesja']);
    $id_gracza = $_SESSION['id_gracza'];

    // Zapisujemy wybór w bazie danych
    $sql = "UPDATE gracze SET profesja='$profesja' WHERE id=$id_gracza";
    
    if ($polaczenie->query($sql) === TRUE) {
        // Po udanym zapisie, przenosimy od razu do gry!
        header("Location: game.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>The Abyss - Kreator Postaci</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Oswald:wght@700&display=swap" rel="stylesheet">
    <style>
        body {
            background-image: url('the-abyss.png'); 
            background-size: cover; 
            background-position: center; 
            background-attachment: fixed; 
            color: #d1d1d1; 
            font-family: 'Open Sans', sans-serif; 
            margin: 0; 
            min-height: 100vh; 
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .ramka-kreatora {
            background-color: rgba(0, 0, 0, 0.8); 
            backdrop-filter: blur(8px); 
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid #333;
            box-shadow: 0px 0px 30px rgba(0, 255, 0, 0.15); 
            border-radius: 8px;
            width: 800px; 
            padding: 40px;
            text-align: center;
        }

        h2 {
            font-family: 'Oswald', sans-serif;
            color: #fff;
            text-shadow: 0px 0px 10px #00ff00; 
            font-size: 2.5em;
            margin-top: 0;
            margin-bottom: 30px;
        }

        /* Klasy / Przyciski na górze */
        .klasy-kontener {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .klasa-btn {
            background-color: #111;
            border: 1px solid #444;
            color: #aaa;
            padding: 15px 30px;
            font-family: 'Oswald', sans-serif;
            font-size: 1.2em;
            text-transform: uppercase;
            cursor: pointer;
            transition: 0.3s;
            border-radius: 4px;
        }

        .klasa-btn:hover { border-color: #00ff00; color: #fff; }
        
        /* Aktywny przycisk */
        .klasa-btn.aktywny {
            background-color: rgba(0, 255, 0, 0.1);
            border-color: #00ff00;
            color: #00ff00;
            box-shadow: 0px 0px 15px rgba(0, 255, 0, 0.4);
        }

        /* Ramka z opisem */
        .opis-box {
            background-color: rgba(0, 0, 0, 0.6);
            border: 1px solid #333;
            padding: 25px;
            min-height: 150px;
            text-align: left;
            border-radius: 4px;
            margin-bottom: 30px;
        }

        .opis-tytul {
            color: #00ff00;
            font-family: 'Oswald', sans-serif;
            font-size: 1.8em;
            margin-top: 0;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
        }

        .opis-tekst { line-height: 1.6; font-size: 1.05em; }

        .btn-submit {
            background-color: #00ff00;
            color: #000;
            font-family: 'Oswald', sans-serif;
            font-size: 1.3em;
            padding: 15px 50px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-transform: uppercase;
            box-shadow: 0px 0px 20px rgba(0, 255, 0, 0.4);
            transition: 0.3s;
        }

        .btn-submit:hover { background-color: #fff; box-shadow: 0px 0px 30px rgba(255, 255, 255, 0.6); }

    </style>
</head>
<body>

    <div class="ramka-kreatora">
        <h2>Kim jesteś w tym Mieście?</h2>
        
        <div class="klasy-kontener">
            <button class="klasa-btn" onclick="zmienKlase('Szabrownik', this)">Szabrownik</button>
            <button class="klasa-btn" onclick="zmienKlase('Inzynier', this)">Inżynier</button>
            <button class="klasa-btn" onclick="zmienKlase('Egzekutor', this)">Egzekutor</button>
        </div>

        <div class="opis-box">
            <h3 class="opis-tytul" id="tytul-klasy">Wybierz swoją ścieżkę</h3>
            <p class="opis-tekst" id="tekst-klasy">Kliknij jedną z mechanicznych ról powyżej, aby zapoznać się z jej przeznaczeniem w The Abyss.</p>
        </div>

        <form method="POST" action="creator.php">
            <input type="hidden" name="wybrana_profesja" id="ukryte-pole" value="">
            <button type="submit" class="btn-submit" id="przycisk-zatwierdz" style="display: none;">Wkrocz do Miasta</button>
        </form>
    </div>

    <script>
        const opisy = {
            'Szabrownik': "Gdy inni śpią, Ty przetrząsasz opuszczone doki i zrujnowane magazyny. Jesteś kręgosłupem gospodarki. Bez Twojego złomu i rzadkich komponentów, całe The Abyss stanęłoby w miejscu. Cechuje Cię wybitna spostrzegawczość i umiejętność unikania wzroku policji.",
            
            'Inzynier': "Twórca cudów z ołowiu i stali. Posiadasz wiedzę, by ze zwykłego złomu stworzyć na Czarnym Rynku potężne uzbrojenie. Jesteś jedyną osobą w Mieście, która ma szansę odpalić tajemniczy Projekt Omega i stworzyć Legendarną Broń.",
            
            'Egzekutor': "Walka to dla Ciebie sztuka, a krew to tylko waluta. Twoje życie to 24-rundowy taniec ze śmiercią. Wymuszasz haracze, chronisz słabszych (za odpowiednią opłatą) i rządzisz ulicami za pomocą brutalnej siły lub doskonałych uników."
        };

        function zmienKlase(klasa, przycisk) {
            // 1. Zmień tytuł i tekst
            document.getElementById('tytul-klasy').innerText = klasa;
            document.getElementById('tekst-klasy').innerText = opisy[klasa];
            
            // 2. Wpisz wybór do ukrytego pola (dla bazy danych)
            document.getElementById('ukryte-pole').value = klasa;
            
            // 3. Pokaż przycisk "Wkrocz do Miasta"
            document.getElementById('przycisk-zatwierdz').style.display = "inline-block";

            // 4. Zdejmij podświetlenie ze wszystkich przycisków
            let przyciski = document.getElementsByClassName('klasa-btn');
            for(let i = 0; i < przyciski.length; i++) {
                przyciski[i].classList.remove('aktywny');
            }
            
            // 5. Podświetl tylko ten kliknięty
            przycisk.classList.add('aktywny');
        }
    </script>

</body>
</html>