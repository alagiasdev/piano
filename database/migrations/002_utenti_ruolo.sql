-- Livello di accesso degli utenti.
-- Chi e amministratore gestisce utenti, impostazioni ed eliminazioni;
-- i collaboratori fanno tutto il resto (clienti, piani, post, export).

ALTER TABLE utenti
    ADD COLUMN amministratore TINYINT(1) NOT NULL DEFAULT 0 AFTER attivo;

-- Gli account gia esistenti diventano amministratori: erano gli unici,
-- e senza questo nessuno potrebbe piu gestire utenti e impostazioni.
UPDATE utenti SET amministratore = 1;
