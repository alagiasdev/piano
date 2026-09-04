-- Chi ha toccato per ultimo un post.
--
-- Serve a dire "Giulia ha scritto..." quando due persone modificano lo
-- stesso campo insieme: senza, il messaggio di conflitto potrebbe solo
-- dire "qualcuno", che e' molto meno utile.

ALTER TABLE post
    ADD COLUMN modificato_da INT UNSIGNED NULL AFTER ordine;

-- Se l'account viene eliminato il post resta, senza attribuzione.
ALTER TABLE post
    ADD CONSTRAINT fk_post_modificato_da FOREIGN KEY (modificato_da)
        REFERENCES utenti (id) ON DELETE SET NULL;
