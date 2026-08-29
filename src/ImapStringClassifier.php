<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client;

/**
 * Scans a candidate IMAP string once and reports how it must be sent
 * (RFC 3501 §4.3): As a bare atom, a quoted string, or a literal.
 *
 * A plain byte scan replaces the H5 library's `php_user_filter`
 * approach (`Data_Format_Filter_String`). Command arguments are ordinary
 * PHP strings here, not streams so there is nothing left for a stream
 * filter to buy us.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapStringClassifier
{
    /**
     * Atom-special characters (RFC 3501 §9) that force quoting when
     * they appear inside an otherwise plain string. `%` and `*` are
     * handled separately below as they are only specials outside a
     * LIST/LSUB pattern argument.
     */
    private const ATOM_SPECIALS = " \"(){}\\\x7f";

    /**
     * @param bool $allowWildcards If true, `%` and `*` do not force
     *                             quoting. Set this for a LIST/LSUB
     *                             mailbox-pattern argument, where those
     *                             characters are wildcards rather than
     *                             literal text (RFC 3501 §6.3.8).
     */
    public static function classify(string $data, bool $allowWildcards = false): ImapStringClassification
    {
        $quoted = false;
        $literal = false;
        $nonAscii = false;

        $len = strlen($data);

        for ($i = 0; $i < $len; ++$i) {
            $byte = $data[$i];
            $ord = ord($byte);

            if ($ord === 0) {
                // A null byte can only travel in a literal8 (RFC 3516).
                // Nothing else in the string changes that, so stop early.
                return new ImapStringClassification(
                    quoted: false,
                    literal: true,
                    binary: true,
                    nonAscii: $nonAscii,
                );
            }

            if ($ord === 10 || $ord === 13) {
                // Embedded CR/LF: Only a literal can carry this safely.
                $literal = true;
            } elseif ($ord < 32) {
                // Other control characters must, at minimum, be quoted.
                $quoted = true;
            } elseif ($ord > 127) {
                $nonAscii = true;
                // 8-bit octets must travel in a literal.
                $literal = true;
            } elseif (($byte === '%' || $byte === '*')) {
                if (!$allowWildcards) {
                    $quoted = true;
                }
            } elseif (str_contains(self::ATOM_SPECIALS, $byte)) {
                $quoted = true;
            }
        }

        return new ImapStringClassification(
            quoted: $quoted && !$literal,
            literal: $literal,
            binary: false,
            nonAscii: $nonAscii,
        );
    }
}
