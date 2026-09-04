<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Due persone hanno modificato lo stesso campo dello stesso post.
 *
 * Non e' un errore da nascondere: porta con se' il valore che c'e' adesso
 * in database e chi ce l'ha messo, perche' chi sta salvando possa vedere
 * cosa stava per sovrascrivere e decidere.
 */
final class Conflitto extends RuntimeException
{
    public function __construct(
        public readonly mixed $valoreAttuale,
        public readonly ?string $chi = null,
        public readonly ?string $quando = null,
    ) {
        parent::__construct('Nel frattempo qualcun altro ha modificato questo campo.');
    }

    /** Frase pronta per l'interfaccia: "Giulia Rossi, 3 minuti fa". */
    public function autore(): string
    {
        if ($this->chi === null) {
            return 'Qualcun altro';
        }

        return $this->chi;
    }
}
