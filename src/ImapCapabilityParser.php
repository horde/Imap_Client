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
 * Turns a `CAPABILITY` response's tokens into {@see ImapCapability} data (RFC 3501 §7.2.1).
 *
 * Each token is either a bare capability name (`IMAP4rev2`, `UIDPLUS`)
 * or a `NAME=PARAM` pair (`AUTH=PLAIN`, `UTF8=ACCEPT`). The same
 * shape whether the tokens came from an untagged `* CAPABILITY ...`
 * data response or a `[CAPABILITY ...]` response code piggybacked onto
 * a greeting or a tagged `OK` (RFC 3501 §7.1).
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapCapabilityParser
{
    private function __construct() {}

    /**
     * @param list<mixed> $tokens The capability names/pairs, with any
     *                            leading `CAPABILITY` token already
     *                            stripped.
     */
    public static function parse(array $tokens, ?ImapCapability $capability = null): ImapCapability
    {
        $capability ??= new ImapCapability();

        foreach ($tokens as $token) {
            if (!is_string($token)) {
                // A nested list never appears in a CAPABILITY response;
                // ignore defensively rather than fail on it.
                continue;
            }

            $equals = strpos($token, '=');

            if ($equals === false) {
                $capability->add($token);
            } else {
                $capability->add(substr($token, 0, $equals), substr($token, $equals + 1));
            }
        }

        return $capability;
    }
}
