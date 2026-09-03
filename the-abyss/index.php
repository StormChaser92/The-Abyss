<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>The Abyss - PBF</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
            
            /* Te 3 linijki centrują ramkę na środku i usuwają biały pasek */
            min-height: 100vh; 
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .ramka {
            border: 1px solid #333;
            padding: 50px 70px; 
            background-color: rgba(0, 0, 0, 0.5); 
            backdrop-filter: blur(5px); 
            -webkit-backdrop-filter: blur(5px);
            box-shadow: 0px 0px 25px rgba(0, 255, 0, 0.15); 
            border-radius: 8px;
            max-width: 600px; 
        }
        
        h1 {
            font-family: 'Oswald', sans-serif; 
            text-transform: uppercase;
            font-size: 3em;
            letter-spacing: 5px; 
            margin-top: 0;
            margin-bottom: 30px;
            color: #fff; 
            text-shadow: 0px 0px 10px #00ff00, 0px 0px 20px #00ff00, 0px 0px 40px #00ff00; 
        }
        
        h2 {
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
            font-size: 1.5em;
            color: #fff;
            text-shadow: 0px 0px 5px #00ff00; 
        }
        
        p {
            line-height: 1.8; 
            margin-bottom: 20px;
        }

        /* --- STYL PRZYCISKÓW --- */
        .btn-container {
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 20px; /* Odstęp między przyciskami */
        }

        .btn {
            background-color: transparent;
            color: #00ff00;
            font-family: 'Oswald', sans-serif;
            font-size: 1.2em;
            letter-spacing: 2px;
            padding: 12px 30px;
            border: 1px solid #00ff00;
            border-radius: 4px;
            text-decoration: none; /* Usuwa podkreślenie z linku */
            text-transform: uppercase;
            transition: 0.3s; /* Płynna animacja najechania myszką */
            box-shadow: 0px 0px 10px rgba(0, 255, 0, 0.2) inset;
        }

        .btn:hover {
            background-color: #00ff00;
            color: #000;
            box-shadow: 0px 0px 20px rgba(0, 255, 0, 0.6);
            cursor: pointer;
        }
    </style>
</head>
<body>

    <div class="ramka">
        <h1>THE ABYSS</h1>
        
        <?php
            echo "<h2>Wkrocz do Otchłani. Napisz swój los.</h2>";
            echo "<p>Wciel się w kogo tylko zechcesz. Bądź bezwzględnym dilerem, genialnym malarzem szukającym natchnienia w rynsztoku, zmęczonym życiem nauczycielem, luksusową prostytutką znającą sekrety elit, albo nieprzekupnym policjantem, który wypowiedział wojnę półświatkowi. Zbuduj swoje wpływy od zera, zgromadź lojalnych ludzi, załóż własną Rodzinę Mafijną i zostań Ojcem Chrzestnym, przed którym drży cała metropolia.</p>";
            echo "<p>To miasto nigdy nie śpi. Pytanie brzmi: czy Ty przetrwasz do świtu?</p>";
        ?>

        <div class="btn-container">
            <a href="login.php" class="btn">Zaloguj się</a>
            <a href="register.php" class="btn">Zarejestruj się</a>
        </div>
    </div>

</body>
</html>