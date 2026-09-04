-- Rinomina della seconda fase: "creativi" diventa "esecutivi".
--
-- In italiano "creativo" come sostantivo indica la persona (art director,
-- copywriter) piu spesso del materiale; "esecutivo" e il termine preciso
-- per il file finito pronto alla pubblicazione. La 003 resta com'e: e'
-- gia stata applicata e le migrazioni sono un registro, non si riscrivono.

-- L'ENUM si allarga, si travasa e si stringe: cosi nessuna riga resta
-- con un valore non piu ammesso durante il passaggio.
ALTER TABLE piani
    MODIFY COLUMN fase ENUM('concept','creativi','esecutivi') NOT NULL DEFAULT 'concept';

UPDATE piani SET fase = 'esecutivi' WHERE fase = 'creativi';

ALTER TABLE piani
    MODIFY COLUMN fase ENUM('concept','esecutivi') NOT NULL DEFAULT 'concept';

-- CHANGE COLUMN e non RENAME COLUMN: quest'ultimo esiste solo da
-- MariaDB 10.5, e in produzione non si sa mai quale versione si trova.
ALTER TABLE post
    CHANGE COLUMN stato_creativo stato_esecutivo
        ENUM('da_approvare','approvato','da_rivedere','pubblicato') NOT NULL DEFAULT 'da_approvare',
    CHANGE COLUMN commento_creativo commento_esecutivo TEXT NULL,
    CHANGE COLUMN commento_creativo_il commento_esecutivo_il DATETIME NULL,
    CHANGE COLUMN approvato_creativo_il approvato_esecutivo_il DATETIME NULL;
