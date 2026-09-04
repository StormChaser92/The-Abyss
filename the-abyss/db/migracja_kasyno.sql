-- =====================================================================
--  THE ABYSS — migracja kasyna (MariaDB / MySQL, baza `the_abyss`)
--  Dokłada żetony, stoły Hold'em, boty i reputację hazardzisty.
--  NIE tworzy kont ani ekonomii — te już istnieją w tabeli `gracze`.
--
--  Uruchom raz w phpMyAdmin, zakładka SQL, na bazie the_abyss.
-- =====================================================================
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
--  1. GRACZE — nowe kolumny
--  Żetony żyją obok gotówki. Kasa kasyna wymienia je w obie strony,
--  przy sprzedaży potrącając 3% prowizji.
-- ---------------------------------------------------------------------
ALTER TABLE `gracze`
  ADD COLUMN `zetony`               INT(11)  NOT NULL DEFAULT 0  COMMENT 'żetony kasynowe',
  ADD COLUMN `kasyno_netto`         BIGINT   NOT NULL DEFAULT 0  COMMENT 'sumaryczny zysk/strata — podstawa reputacji',
  ADD COLUMN `kasyno_rozdania`      INT(11)  NOT NULL DEFAULT 0  COMMENT 'licznik rozdań w całej karierze',
  ADD COLUMN `kasyno_wygrana_max`   INT(11)  NOT NULL DEFAULT 0,
  ADD COLUMN `kasyno_ostatnia_gra`  DATETIME NULL DEFAULT NULL,
  ADD COLUMN `kasyno_wada_od`       DATETIME NULL DEFAULT NULL  COMMENT 'kiedy wpadł w hazard; NULL = czysty';

ALTER TABLE `gracze` ADD KEY `idx_gracze_kasyno_netto` (`kasyno_netto`);

-- ---------------------------------------------------------------------
--  2. BOTY
--  Jeden bot dosiada się do pustego stołu, losowany z trzech osobowości.
--  Oznaczony jawnie — gracz zawsze wie, że siedzi z AI.
--  Sejf kasyna jest nieskończony: bot nie ma własnego bankrollu.
-- ---------------------------------------------------------------------
CREATE TABLE `kasyno_boty` (
  `id`         TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nick`       VARCHAR(32)  NOT NULL,
  `osobowosc`  ENUM('ostrozny','szarpiacy','nieobliczalny') NOT NULL,
  `opis`       VARCHAR(190) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `kasyno_boty` (`nick`,`osobowosc`,`opis`) VALUES
  ('Varik_R',  'ostrozny',      'Gra rzadko i tylko z mocną ręką. Kiedy podbija, zwykle ma powód.'),
  ('Kestra_V', 'szarpiacy',     'Naciska przy każdej okazji. Trudno powiedzieć, czy trzyma cokolwiek.'),
  ('Szept',    'nieobliczalny', 'Nie widać w tym żadnego systemu. Może właśnie o to chodzi.');

-- ---------------------------------------------------------------------
--  3. STOŁY
--  `wersja` rośnie przy każdej zmianie — klient pyta „co nowego od N".
--  `talia` i karty zakryte nigdy nie wychodzą do przeglądarki.
-- ---------------------------------------------------------------------
CREATE TABLE `kasyno_stoly` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nazwa`           VARCHAR(64)  NOT NULL,
  `gra`             ENUM('holdem') NOT NULL DEFAULT 'holdem',
  `blind_maly`      INT(11)      NOT NULL DEFAULT 100,
  `blind_duzy`      INT(11)      NOT NULL DEFAULT 200,
  `wejscie_min`     INT(11)      NOT NULL DEFAULT 10000,
  `prog_majatku`    INT(11)      NOT NULL DEFAULT 50000 COMMENT 'ile trzeba mieć, by wejść do kasyna',
  `miejsca`         TINYINT UNSIGNED NOT NULL DEFAULT 6,
  `faza`            ENUM('oczekiwanie','preflop','flop','turn','river','showdown','sprzatanie') NOT NULL DEFAULT 'oczekiwanie',
  `faza_do`         DATETIME     NULL DEFAULT NULL COMMENT 'deadline decyzji (45 s)',
  `aktywne_miejsce` TINYINT UNSIGNED NULL DEFAULT NULL,
  `przycisk`        TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'miejsce z żetonem krupiera',
  `rozdanie_id`     BIGINT UNSIGNED NULL DEFAULT NULL,
  `rozdanie_nr`     INT(11)      NOT NULL DEFAULT 0,
  `board`           VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'np. Ks,9h,4s,Qd',
  `talia`           VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'reszta talii — tajne',
  `pula`            INT(11)      NOT NULL DEFAULT 0,
  `zaklad_biezacy`  INT(11)      NOT NULL DEFAULT 0 COMMENT 'do ile trzeba dorównać na tej ulicy',
  `ostatni_podbil`  TINYINT UNSIGNED NULL DEFAULT NULL,
  `wersja`          BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `zmieniono`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `kasyno_stoly` (`nazwa`,`blind_maly`,`blind_duzy`,`wejscie_min`) VALUES
  ('Złoty Smok — Hold\'em', 100, 200, 10000);

-- ---------------------------------------------------------------------
--  4. MIEJSCA
--  Jedno miejsce = gracz ALBO bot. `zetony` to stos przy stole,
--  oddzielony od portfela aż do wstania.
-- ---------------------------------------------------------------------
CREATE TABLE `kasyno_miejsca` (
  `stol_id`         INT UNSIGNED NOT NULL,
  `miejsce`         TINYINT UNSIGNED NOT NULL,
  `gracz_id`        INT(11)      NULL DEFAULT NULL,
  `bot_id`          TINYINT UNSIGNED NULL DEFAULT NULL,
  `zetony`          INT(11)      NOT NULL DEFAULT 0,
  `status`          ENUM('wolne','czeka','gra','spasowal','allin','pauza') NOT NULL DEFAULT 'wolne',
  `karty`           VARCHAR(16)  NOT NULL DEFAULT '' COMMENT 'karty zakryte — tajne',
  `wplata_ulica`    INT(11)      NOT NULL DEFAULT 0,
  `wplata_rozdanie` INT(11)      NOT NULL DEFAULT 0,
  `ostatnia_akcja`  VARCHAR(24)  NULL DEFAULT NULL,
  `dzialal_w_ulicy` TINYINT(1)   NOT NULL DEFAULT 0,
  `widziano`        DATETIME     NULL DEFAULT NULL COMMENT 'do wykrywania rozłączeń',
  `timeouty`        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`stol_id`,`miejsce`),
  UNIQUE KEY `uq_miejsce_gracz` (`stol_id`,`gracz_id`),
  KEY `idx_miejsce_gracz` (`gracz_id`),
  CONSTRAINT `fk_miejsce_stol` FOREIGN KEY (`stol_id`) REFERENCES `kasyno_stoly`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `kasyno_miejsca` (`stol_id`,`miejsce`)
SELECT t.`id`, m.`n` FROM `kasyno_stoly` t
JOIN (SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) m
WHERE m.`n` <= t.`miejsca`;

-- ---------------------------------------------------------------------
--  5. ROZDANIA I AKCJE — historia i podstawa statystyk
-- ---------------------------------------------------------------------
CREATE TABLE `kasyno_rozdania` (
  `id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `stol_id`   INT UNSIGNED NOT NULL,
  `nr`        INT(11)      NOT NULL,
  `board`     VARCHAR(40)  NOT NULL DEFAULT '',
  `pula`      INT(11)      NOT NULL DEFAULT 0,
  `rake`      INT(11)      NOT NULL DEFAULT 0,
  `wynik`     TEXT         NULL DEFAULT NULL COMMENT 'JSON: kto, jaki układ, ile wziął',
  `start`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `koniec`    DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rozd_stol` (`stol_id`,`id`),
  KEY `idx_rozd_pula` (`start`,`pula`) COMMENT 'tablica największych pul dnia'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `kasyno_akcje` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rozdanie_id` BIGINT UNSIGNED NOT NULL,
  `gracz_id`    INT(11)      NULL DEFAULT NULL,
  `bot_id`      TINYINT UNSIGNED NULL DEFAULT NULL,
  `miejsce`     TINYINT UNSIGNED NOT NULL,
  `ulica`       ENUM('preflop','flop','turn','river') NOT NULL,
  `akcja`       VARCHAR(16)  NOT NULL COMMENT 'pas/czekam/sprawdzam/podbijam/allin/blind',
  `kwota`       INT(11)      NOT NULL DEFAULT 0,
  `czas`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_akcje_rozd` (`rozdanie_id`,`id`),
  KEY `idx_akcje_gracz` (`gracz_id`),
  CONSTRAINT `fk_akcje_rozd` FOREIGN KEY (`rozdanie_id`) REFERENCES `kasyno_rozdania`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Uczestnictwo gracza w rozdaniu — z tego liczymy próg wady (200 rozdań / 7 dni).
CREATE TABLE `kasyno_udzial` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rozdanie_id` BIGINT UNSIGNED NOT NULL,
  `gracz_id`    INT(11)      NOT NULL,
  `wplacil`     INT(11)      NOT NULL DEFAULT 0,
  `wzial`       INT(11)      NOT NULL DEFAULT 0,
  `uklad`       VARCHAR(40)  NULL DEFAULT NULL,
  `czas`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_udzial_gracz_czas` (`gracz_id`,`czas`),
  CONSTRAINT `fk_udzial_rozd` FOREIGN KEY (`rozdanie_id`) REFERENCES `kasyno_rozdania`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
--  6. LEDGER
--  Każda zmiana gotówki i żetonów w kasynie ma tu wiersz, z balansem
--  po operacji. Pozwala odtworzyć stan i wykryć niezgodność.
-- ---------------------------------------------------------------------
CREATE TABLE `kasyno_ledger` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `gracz_id`      INT(11)     NOT NULL,
  `delta_gotowka` INT(11)     NOT NULL DEFAULT 0,
  `delta_zetony`  INT(11)     NOT NULL DEFAULT 0,
  `gotowka_po`    INT(11)     NOT NULL,
  `zetony_po`     INT(11)     NOT NULL,
  `powod`         VARCHAR(40) NOT NULL COMMENT 'kup/sprzedaj/prowizja/wejscie/wyjscie/pula/wyplata',
  `ref_typ`       VARCHAR(20) NULL DEFAULT NULL,
  `ref_id`        BIGINT UNSIGNED NULL DEFAULT NULL,
  `czas`          DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ledger_gracz` (`gracz_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
--  7. CZAT I WIDZOWIE
-- ---------------------------------------------------------------------
CREATE TABLE `kasyno_wiadomosci` (
  `id`       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kanal`    VARCHAR(32) NOT NULL COMMENT 'stol:1 / klub',
  `gracz_id` INT(11)     NULL DEFAULT NULL,
  `bot_id`   TINYINT UNSIGNED NULL DEFAULT NULL,
  `typ`      ENUM('mowa','akcja','szept','system','mg','drink') NOT NULL DEFAULT 'mowa',
  `tresc`    TEXT        NOT NULL,
  `cel_id`   INT(11)     NULL DEFAULT NULL COMMENT 'adresat szeptu',
  `czas`     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wiad_kanal` (`kanal`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `kasyno_widzowie` (
  `stol_id`  INT UNSIGNED NOT NULL,
  `gracz_id` INT(11)      NOT NULL,
  `widziano` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`stol_id`,`gracz_id`),
  CONSTRAINT `fk_widz_stol` FOREIGN KEY (`stol_id`) REFERENCES `kasyno_stoly`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
--  8. RANKING I TABLICA PUL — jawne dla wszystkich graczy
--  Reputacja liczona z netto: Rekin stołów / Stały bywalec / Frajer kasyna.
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW `v_kasyno_ranking` AS
SELECT g.`id`, g.`login`, g.`profesja_fabularna`, g.`avatar`,
       g.`kasyno_netto`      AS netto,
       g.`kasyno_rozdania`   AS rozdania,
       g.`kasyno_wygrana_max` AS najwieksza_wygrana,
       CASE
         WHEN g.`kasyno_netto` >=  25000 THEN 'Rekin stołów'
         WHEN g.`kasyno_netto` <= -25000 THEN 'Frajer kasyna'
         ELSE 'Stały bywalec'
       END AS reputacja
FROM `gracze` g
WHERE g.`kasyno_rozdania` > 0;

CREATE OR REPLACE VIEW `v_kasyno_pule_dnia` AS
SELECT r.`id`, r.`nr`, r.`pula`, r.`rake`, r.`board`, r.`start`, s.`nazwa` AS stol
FROM `kasyno_rozdania` r
JOIN `kasyno_stoly` s ON s.`id` = r.`stol_id`
WHERE r.`koniec` IS NOT NULL AND r.`start` >= CURDATE()
ORDER BY r.`pula` DESC;
