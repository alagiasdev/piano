-- Piano — schema iniziale
-- MySQL 5.7+ / MariaDB 10.3+

CREATE TABLE utenti (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome           VARCHAR(80)  NOT NULL,
    email          VARCHAR(190) NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    attivo         TINYINT(1)   NOT NULL DEFAULT 1,
    ultimo_accesso DATETIME     NULL,
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_utenti_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limit elementare sul login, per indirizzo IP.
CREATE TABLE login_tentativi (
    ip               VARCHAR(45) NOT NULL,
    tentativi        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ultimo_tentativo DATETIME    NOT NULL,
    PRIMARY KEY (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clienti (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome           VARCHAR(120) NOT NULL,
    slug           VARCHAR(140) NOT NULL,
    logo_path      VARCHAR(255) NULL,
    contatto_nome  VARCHAR(120) NULL,
    contatto_email VARCHAR(190) NULL,
    canali         JSON         NULL,
    tono_di_voce   TEXT         NULL,
    note           TEXT         NULL,
    attivo         TINYINT(1)   NOT NULL DEFAULT 1,
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_clienti_slug (slug),
    KEY idx_clienti_attivo (attivo, nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE piani (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cliente_id     INT UNSIGNED NOT NULL,
    titolo         VARCHAR(120) NOT NULL,
    data_inizio    DATE         NOT NULL,
    data_fine      DATE         NOT NULL,
    stato          ENUM('bozza','inviato','approvato','chiuso') NOT NULL DEFAULT 'bozza',
    nota_cliente   TEXT         NULL,
    token_pubblico VARCHAR(64)  NULL,
    inviato_il     DATETIME     NULL,
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_piani_token (token_pubblico),
    KEY idx_piani_cliente (cliente_id, data_inizio),
    KEY idx_piani_periodo (data_inizio, data_fine),
    CONSTRAINT fk_piani_cliente FOREIGN KEY (cliente_id)
        REFERENCES clienti (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE post (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    piano_id         INT UNSIGNED NOT NULL,
    data             DATE         NOT NULL,
    ora              TIME         NULL,
    canali           JSON         NULL,
    contenuto        TEXT         NULL,
    formato          VARCHAR(60)  NULL,
    cta              VARCHAR(120) NULL,
    pilastro         VARCHAR(60)  NULL,
    visual_url       VARCHAR(500) NULL,
    stato            ENUM('da_approvare','approvato','da_rivedere','pubblicato') NOT NULL DEFAULT 'da_approvare',
    commento_cliente TEXT         NULL,
    commento_il      DATETIME     NULL,
    -- Non era nello schema iniziale: senza, non si sa quando il cliente
    -- ha approvato e la dashboard non puo mostrare le novita recenti.
    approvato_il     DATETIME     NULL,
    ordine           INT          NOT NULL DEFAULT 0,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- L'ordine e per giorno: il riordino avviene dentro la stessa data.
    KEY idx_post_piano_data (piano_id, data, ordine, id),
    KEY idx_post_data_stato (data, stato),
    CONSTRAINT fk_post_piano FOREIGN KEY (piano_id)
        REFERENCES piani (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE impostazioni (
    chiave VARCHAR(60) NOT NULL,
    valore TEXT        NULL,
    PRIMARY KEY (chiave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO impostazioni (chiave, valore) VALUES
    ('nome_studio', 'Alagias. — Soluzioni per il web'),
    ('firma', 'Alagias. — Soluzioni per il web · Lauria (PZ)'),
    ('nota_standard', 'Le bozze di testo e visual vengono condivise il lunedì della settimana precedente alla pubblicazione. Feedback entro 2 giorni lavorativi; in assenza di risposta il contenuto si intende approvato.');
