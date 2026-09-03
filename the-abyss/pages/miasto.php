<style>
    /* ========================================================
       GLASSMORPHISM W GŁÓWNYM HUBIE MIASTA
       ======================================================== */
    .miasto-header { 
        background: linear-gradient(to bottom, rgba(5, 5, 5, 0.4), rgba(5, 5, 5, 0.9)), url('img/kasyno_bg.jpg') center/cover no-repeat fixed; 
        height: 220px; 
        border: 1px solid rgba(0, 255, 0, 0.3); 
        border-radius: 8px; 
        margin-bottom: 35px; 
        display: flex; 
        flex-direction: column;
        justify-content: flex-end; 
        padding: 30px; 
        box-sizing: border-box;
        box-shadow: 0 10px 40px rgba(0, 255, 0, 0.1);
        backdrop-filter: blur(5px);
        position: relative;
        overflow: hidden;
    }
    
    /* Neonowa linia na dole headera */
    .miasto-header::after {
        content: '';
        position: absolute;
        bottom: 0; left: 0; width: 100%; height: 3px;
        background: linear-gradient(90deg, transparent, #00ff00, transparent);
        box-shadow: 0 0 15px #00ff00;
    }

    .miasto-header h1 { 
        margin: 0 0 5px 0; 
        font-family: 'Oswald', sans-serif; 
        color: #00ff00; 
        font-size: 3.5em; 
        text-transform: uppercase; 
        text-shadow: 0 0 20px rgba(0, 255, 0, 0.6); 
        letter-spacing: 2px;
        font-weight: 700;
    }
    .miasto-header p {
        margin: 0;
        color: #aaa;
        font-family: 'Open Sans', sans-serif;
        font-size: 1.1em;
        text-shadow: 0 1px 3px #000;
    }
    
    .miasto-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 25px;
    }
    
    /* ========================================================
       SZKLANE KARTY LOKACJI
       ======================================================== */
    .karta-lokacji {
        display: flex; 
        flex-direction: column;
        text-decoration: none; 
        background: rgba(10, 10, 10, 0.6); 
        border: 1px solid rgba(255, 255, 255, 0.08); 
        border-radius: 8px; 
        overflow: hidden; 
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); 
        position: relative;
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
        height: 100%;
    }
    
    /* Wspólny efekt hover z lekkim uniesieniem */
    .karta-lokacji:hover { 
        transform: translateY(-8px); 
        background: rgba(15, 15, 15, 0.8);
    }
    
    .lokacja-img {
        height: 130px; 
        background: rgba(0, 0, 0, 0.5); 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        font-size: 3.5em;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        position: relative;
        overflow: hidden;
    }
    
    /* Delikatny glow pod ikonką */
    .lokacja-img::before {
        content: '';
        position: absolute;
        width: 60px; height: 60px;
        background: currentColor;
        filter: blur(35px);
        opacity: 0.15;
        border-radius: 50%;
    }
    
    .lokacja-tresc { padding: 20px; flex-grow: 1; display: flex; flex-direction: column;}
    .lokacja-tytul { 
        color: #fff; 
        font-family: 'Oswald', sans-serif; 
        font-weight: 700;
        font-size: 1.4em; 
        margin: 0 0 10px 0; 
        text-transform: uppercase; 
        letter-spacing: 1px;
        text-shadow: 0 2px 4px rgba(0,0,0,0.8);
    }
    .lokacja-opis { 
        color: #bbb; 
        font-size: 0.95em; 
        margin: 0; 
        line-height: 1.6; 
        font-family: 'Open Sans', sans-serif;
    }
    
    .status-badge { 
        position: absolute; 
        top: 12px; right: 12px; 
        padding: 4px 10px; 
        font-family: 'Oswald', sans-serif; 
        font-size: 0.85em; 
        border-radius: 4px; 
        font-weight: 700; 
        letter-spacing: 1px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.5);
        z-index: 10;
    }

    /* ========================================================
       INDYWIDUALNE NEONOWE BLASKI DLA LOKACJI
       ======================================================== */
    .karta-klub:hover { border-color: #ff3333; box-shadow: 0 10px 25px rgba(255, 51, 51, 0.4); }
    .karta-doki:hover { border-color: #888888; box-shadow: 0 10px 25px rgba(136, 136, 136, 0.4); }
    .karta-rynek:hover { border-color: #00ff00; box-shadow: 0 10px 25px rgba(0, 255, 0, 0.4); }
    .karta-szpital:hover { border-color: #00ccff; box-shadow: 0 10px 25px rgba(0, 204, 255, 0.4); }
    .karta-sklep:hover { border-color: #ffaa00; box-shadow: 0 10px 25px rgba(255, 170, 0, 0.4); }
    .karta-syndykat:hover { border-color: #aa00ff; box-shadow: 0 10px 25px rgba(170, 0, 255, 0.4); }
    .karta-urzad:hover { border-color: #ffd700; box-shadow: 0 10px 25px rgba(255, 215, 0, 0.4); }
    .karta-uni:hover { border-color: #8888ff; box-shadow: 0 10px 25px rgba(136, 136, 255, 0.4); }
</style>

<div class="miasto-header">
    <h1>Eksploracja Miasta</h1>
    <p>Wybierz cel swojej dzisiejszej podróży. Uważaj w zaułkach.</p>
</div>

<div class="miasto-grid">
    
    <a href="game.php?page=czat" class="karta-lokacji karta-klub">
        <div class="lokacja-img" style="color: #ff3333; text-shadow: 0 0 15px rgba(255,51,51,0.5);">🍷</div>
        <div class="lokacja-tresc">
            <h3 class="lokacja-tytul" style="color: #ff3333;">The Abyss Club</h3>
            <p class="lokacja-opis">Najgłośniejszy klub w mieście. Idealne miejsce na plotki, szukanie zleceń i wydawanie gotówki.</p>
        </div>
    </a>

    <a href="game.php?page=doki" class="karta-lokacji karta-doki">
        <div class="status-badge" style="background: rgba(255,51,51,0.2); color: #ff3333; border: 1px solid #ff3333;">STREFA WALKI</div>
        <div class="lokacja-img" style="color: #888; text-shadow: 0 0 15px rgba(136,136,136,0.5);">⚓</div>
        <div class="lokacja-tresc">
            <h3 class="lokacja-tytul" style="color: #bbb;">Doki i Zaułki</h3>
            <p class="lokacja-opis">Niebezpieczne rewiry. Miejsce polowań na szczury, gangi i idealny teren do szabrowania złomu.</p>
        </div>
    </a>

    <a href="game.php?page=rynek" class="karta-lokacji karta-rynek">
        <div class="lokacja-img" style="color: #00ff00; text-shadow: 0 0 15px rgba(0,255,0,0.5);">🛒</div>
        <div class="lokacja-tresc">
            <h3 class="lokacja-tytul" style="color: #00ff00;">Czarny Rynek</h3>
            <p class="lokacja-opis">Jeśli masz gotówkę, tu kupisz każdą nielegalną broń i pancerz z przemytu. Żadnych pytań.</p>
        </div>
    </a>

    <a href="game.php?page=szpital" class="karta-lokacji karta-szpital">
        <div class="lokacja-img" style="color: #00ccff; text-shadow: 0 0 15px rgba(0,204,255,0.5);">🏥</div>
        <div class="lokacja-tresc">
            <h3 class="lokacja-tytul" style="color: #00ccff;">Klinika Uliczna</h3>
            <p class="lokacja-opis">Dostałeś kulkę w żebra? Rzeźnik poskłada Cię do kupy, oczywiście jeśli masz czym zapłacić.</p>
        </div>
    </a>

    <a href="game.php?page=sklep" class="karta-lokacji karta-sklep">
        <div class="status-badge" style="background: rgba(255,170,0,0.2); color: #ffaa00; border: 1px solid #ffaa00;">DLA NOWYCH</div>
        <div class="lokacja-img" style="color: #ffaa00; text-shadow: 0 0 15px rgba(255,170,0,0.5);">🛠️</div>
        <div class="lokacja-tresc">
            <h3 class="lokacja-tytul" style="color: #ffaa00;">Lombard "Rdza i Krew"</h3>
            <p class="lokacja-opis">Kupisz tu zardzewiałą broń i podstawowe ubrania ochronne. Idealne na pierwszą bitwę w Dokach.</p>
        </div>
    </a>

    <a href="game.php?page=syndykaty" class="karta-lokacji karta-syndykat">
        <div class="lokacja-img" style="color: #aa00ff; text-shadow: 0 0 15px rgba(170,0,255,0.5);">🏴</div>
        <div class="lokacja-tresc">
            <h3 class="lokacja-tytul" style="color: #aa00ff;">Siedziby Syndykatów</h3>
            <p class="lokacja-opis">Samotne wilki giną tu najszybciej. Załóż własne imperium zbrodni lub dołącz do struktur potężnego gangu.</p>
        </div>
    </a>

    <a href="game.php?page=uniwersytet" class="karta-lokacji karta-uni">
        <div class="status-badge" style="background: rgba(136,136,255,0.2); color: #8888ff; border: 1px solid #8888ff;">OTWARTE</div>
        <div class="lokacja-img" style="color: #8888ff; text-shadow: 0 0 15px rgba(136,136,255,0.5);">🎓</div>
        <div class="lokacja-tresc">
            <h3 class="lokacja-tytul" style="color: #8888ff;">Akademia Nauk</h3>
            <p class="lokacja-opis">Edukacja to klucz do wyższych sfer. Zdobywaj dyplomy, podchodź do egzaminów przed komisją AI.</p>
        </div>
    </a>

    <a href="game.php?page=firma" class="karta-lokacji karta-urzad">
        <div class="status-badge" style="background: rgba(255,215,0,0.2); color: #ffd700; border: 1px solid #ffd700;">PREMIUM VIP</div>
        <div class="lokacja-img" style="color: #ffd700; text-shadow: 0 0 15px rgba(255,215,0,0.5);">🏛️</div>
        <div class="lokacja-tresc">
            <h3 class="lokacja-tytul" style="color: #ffd700;">Ratusz Miejski</h3>
            <p class="lokacja-opis">Urząd korporacyjny obsługujący elitę. Rejestracja własnej działalności gospodarczej dla mieszkańców VIP.</p>
        </div>
    </a>

</div>