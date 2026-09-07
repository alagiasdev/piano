-- Quali clienti puo vedere un collaboratore.
--
-- Fino a qui chiunque avesse un accesso vedeva e modificava tutto: le
-- rotte di clienti, piani e post chiedevano solo il login. Da adesso un
-- collaboratore vede i clienti che gli sono stati assegnati, e nient'altro.
--
-- Gli amministratori NON compaiono in questa tabella: vedono tutto per
-- definizione, e metterceli vorrebbe dire doverli aggiornare a ogni nuovo
-- cliente, con il rischio di dimenticarsene e chiudere fuori chi comanda.

CREATE TABLE utente_cliente (
    utente_id  INT UNSIGNED NOT NULL,
    cliente_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (utente_id, cliente_id),
    KEY idx_utente_cliente_cliente (cliente_id),
    CONSTRAINT fk_utente_cliente_utente
        FOREIGN KEY (utente_id) REFERENCES utenti (id) ON DELETE CASCADE,
    CONSTRAINT fk_utente_cliente_cliente
        FOREIGN KEY (cliente_id) REFERENCES clienti (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Il giorno del passaggio non deve cambiare niente per nessuno: ogni
-- collaboratore parte con tutti i clienti che gia oggi vede comunque.
-- Le assegnazioni si stringono dopo, cliente per cliente, con calma.
-- Il contrario -- partire da zero -- avrebbe chiuso fuori tutti al primo
-- accesso dopo l'aggiornamento, senza preavviso.
INSERT INTO utente_cliente (utente_id, cliente_id)
SELECT u.id, c.id
FROM utenti u
CROSS JOIN clienti c
WHERE u.amministratore = 0;
