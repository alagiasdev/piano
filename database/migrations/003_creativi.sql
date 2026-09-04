-- Seconda fase del piano: i creativi.
--
-- Il piano passa da "concept" (il cliente approva le idee) a "creativi"
-- (il cliente approva il post vero: copy finale e immagini). Le due fasi
-- usano gli stessi stati e gli stessi pulsanti; cambiano solo le colonne
-- su cui scrivono, cosi non c'e' logica duplicata.

ALTER TABLE piani
    ADD COLUMN fase ENUM('concept','creativi') NOT NULL DEFAULT 'concept' AFTER stato;

ALTER TABLE post
    ADD COLUMN copy_finale TEXT NULL AFTER contenuto,
    ADD COLUMN stato_creativo ENUM('da_approvare','approvato','da_rivedere','pubblicato')
        NOT NULL DEFAULT 'da_approvare' AFTER stato,
    ADD COLUMN commento_creativo TEXT NULL AFTER commento_il,
    ADD COLUMN commento_creativo_il DATETIME NULL AFTER commento_creativo,
    ADD COLUMN approvato_creativo_il DATETIME NULL AFTER approvato_il;

-- Le immagini del post: piu di una, perche i caroselli.
CREATE TABLE post_media (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id        INT UNSIGNED NOT NULL,
    percorso       VARCHAR(255) NOT NULL,
    nome_originale VARCHAR(255) NULL,
    mime           VARCHAR(60)  NOT NULL,
    byte           INT UNSIGNED NOT NULL DEFAULT 0,
    ordine         INT          NOT NULL DEFAULT 0,
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_media_post (post_id, ordine, id),
    CONSTRAINT fk_media_post FOREIGN KEY (post_id)
        REFERENCES post (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
