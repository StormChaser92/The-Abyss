<?php
// ══════════════════════════════════════════════════════════════════
// KLUB — PLACEHOLDER (sale wkrótce dostępne)
// Włączany dla sal z 'aktywna'=>false w katalogu $SALE w czat.php
// ══════════════════════════════════════════════════════════════════
?>
<style>
.placeholder-room {
    padding: 60px 30px; text-align: center;
    background: rgba(10,5,12,0.55);
    border: 1px dashed var(--border-mid);
    border-radius: 2px; margin-bottom: 20px;
    position: relative; overflow: hidden;
}
.placeholder-room::before {
    content: ''; position: absolute; inset: 0;
    background: 
        repeating-linear-gradient(45deg, transparent 0 12px, rgba(255,23,68,0.02) 12px 13px),
        radial-gradient(ellipse at center, rgba(255,23,68,0.05), transparent 70%);
    pointer-events: none;
}
.placeholder-room .ic {
    font-size: 4em; line-height: 1; margin-bottom: 14px;
    filter: drop-shadow(0 0 20px rgba(255,23,68,0.4));
    position: relative;
}
.placeholder-room .nazwa {
    font-family: 'Oswald', sans-serif; font-size: 2em;
    color: #fff; letter-spacing: 4px; text-transform: uppercase;
    margin-bottom: 10px; position: relative;
    text-shadow: 0 0 16px rgba(255,23,68,0.3);
}
.placeholder-room .opis {
    font-family: 'Cormorant Garamond', serif; font-style: italic;
    color: var(--txt-dim); font-size: 1.05em; line-height: 1.5;
    max-width: 480px; margin: 0 auto 20px;
    position: relative;
}
.placeholder-room .status {
    display: inline-block; padding: 6px 18px;
    background: rgba(255,215,0,0.08);
    border: 1px solid rgba(255,215,0,0.4);
    color: var(--neon-gold);
    font-family: 'JetBrains Mono', monospace;
    font-size: .78em; letter-spacing: 3px;
    text-transform: uppercase; border-radius: 2px;
    position: relative;
}
.placeholder-room .info-faza {
    margin-top: 20px; padding: 14px 18px;
    background: rgba(0,0,0,0.5);
    border-left: 2px solid var(--neon-gold);
    font-family: 'JetBrains Mono', monospace;
    font-size: .76em; color: var(--txt-mute);
    letter-spacing: 1px; line-height: 1.6;
    text-align: left; max-width: 520px;
    margin-left: auto; margin-right: auto;
    position: relative;
}
.placeholder-room .info-faza b {
    color: var(--neon-gold);
    text-transform: uppercase; letter-spacing: 2px;
    display: block; margin-bottom: 6px;
}
.placeholder-room .back-btn {
    display: inline-block; margin-top: 20px;
    padding: 10px 22px; background: rgba(255,23,68,0.08);
    border: 1px solid var(--neon-red);
    color: var(--neon-red-hot); text-decoration: none;
    font-family: 'Oswald', sans-serif; font-size: .9em;
    letter-spacing: 2.5px; text-transform: uppercase;
    border-radius: 2px; transition: .2s;
    position: relative;
}
.placeholder-room .back-btn:hover {
    background: var(--neon-red); color: #fff;
    box-shadow: 0 0 18px rgba(255,23,68,0.5);
}
</style>

<div class="placeholder-room">
    <div class="ic"><?php echo $dane_sali['ikona']; ?></div>
    <div class="nazwa"><?php echo htmlspecialchars($dane_sali['nazwa']); ?></div>
    <div class="opis"><?php echo htmlspecialchars($dane_sali['opis']); ?></div>
    <div class="status">⧗ Wkrótce otwarte</div>

    <div class="info-faza">
        <b>// W przygotowaniu</b>
        Ta sala dołączy do klubu w jednej z kolejnych aktualizacji. Każda nowa sala dostanie własny klimat, NPC i mechanikę — tak jak Sauna, Pokój BDSM czy Tyły. Wracaj tu — drzwi wkrótce się otworzą.
    </div>

    <a href="game.php?page=czat&sala=lobby" class="back-btn">◂ Wróć do Lobby</a>
</div>