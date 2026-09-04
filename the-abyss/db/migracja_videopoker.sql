-- =====================================================================
--  THE ABYSS — Video Poker z double-up
--  Wymaga wcześniejszego migracja_kasyno.sql (żetony, ledger, stoły).
--  Uruchom raz w phpMyAdmin na bazie the_abyss.
-- =====================================================================
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
--  Rundy gier solo
--  Stan rundy żyje w BAZIE, nie w sesji — odświeżenie strony w połowie
--  rozdania nie gubi kart ani niedokończonego double-up.
--  `talia` i `karty_double` są tajne: nie wychodzą do przeglądarki.
-- ---------------------------------------------------------------------
CREATE TABLE `kasyno_solo` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `gracz_id`       INT(11)      NOT NULL,
  `gra`            VARCHAR(24)  NOT NULL DEFAULT 'videopoker',
  `stawka`         INT(11)      NOT NULL,
  `zetony_stawka`  INT(11)      NOT NULL DEFAULT 1 COMMENT 'ile żetonów postawiono (1-5)',
  `wyplata`        INT(11)      NOT NULL DEFAULT 0,
  `stan`           ENUM('rozdanie','dobranie','double','zakonczona') NOT NULL DEFAULT 'rozdanie',
  `karty`          VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'ręka gracza, np. As,Kd,9h,4s,Tc',
  `talia`          VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'reszta talii — TAJNE',
  `trzymane`       VARCHAR(16)  NOT NULL DEFAULT '' COMMENT 'np. 1,0,1,1,0',
  `uklad`          VARCHAR(40)  NULL DEFAULT NULL,
  `mnoznik`        INT(11)      NOT NULL DEFAULT 0,
  `double_poziom`  TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ile razy podwoił z rzędu',
  `double_karty`   VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'krupier + 4 zakryte — TAJNE',
  `double_wybor`   TINYINT UNSIGNED NULL DEFAULT NULL,
  `klucz_zadania`  VARCHAR(64)  NULL DEFAULT NULL COMMENT 'idempotencja: podwójny klik nie płaci dwa razy',
  `czas`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_solo_klucz` (`gracz_id`,`klucz_zadania`),
  KEY `idx_solo_gracz` (`gracz_id`,`id`),
  KEY `idx_solo_otwarte` (`gracz_id`,`stan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Lekki log uczestnictwa — liczy się do progu wady hazardzisty (200/7 dni).
CREATE TABLE `kasyno_solo_udzial` (
  `id`       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `gracz_id` INT(11)     NOT NULL,
  `gra`      VARCHAR(24) NOT NULL,
  `stawka`   INT(11)     NOT NULL,
  `wyplata`  INT(11)     NOT NULL,
  `czas`     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sudzial_gracz_czas` (`gracz_id`,`czas`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Statystyki per gra — do rankingu bez skanowania wszystkich rund.
CREATE TABLE `kasyno_solo_stat` (
  `gracz_id`   INT(11)     NOT NULL,
  `gra`        VARCHAR(24) NOT NULL,
  `rozdania`   INT(11)     NOT NULL DEFAULT 0,
  `obrot`      BIGINT      NOT NULL DEFAULT 0,
  `wygrane`    BIGINT      NOT NULL DEFAULT 0,
  `najwieksza` INT(11)     NOT NULL DEFAULT 0,
  PRIMARY KEY (`gracz_id`,`gra`),
  KEY `idx_sstat_gra` (`gra`,`wygrane`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tablica największych trafień w grach solo (jawna, jak ranking Hold'em).
CREATE OR REPLACE VIEW `v_kasyno_solo_trafienia` AS
SELECT s.`id`, g.`login`, s.`gra`, s.`stawka`, s.`wyplata`, s.`uklad`, s.`czas`
FROM `kasyno_solo` s
JOIN `gracze` g ON g.`id` = s.`gracz_id`
WHERE s.`stan` = 'zakonczona' AND s.`wyplata` > s.`stawka`
ORDER BY s.`wyplata` DESC;
