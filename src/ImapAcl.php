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

use Stringable;

/**
 * A set of ACL rights for one identifier (RFC 4314).
 *
 * Rights are single letters ({@see AclRight}). The two RFC 2086 virtual
 * rights are normalized on construction (RFC 4314 §2.1.1): `c` expands to
 * `k`+`x` and `d` to `t`+`x`+`e`, and once any component right is present
 * the virtual letters are dropped, so an `ImapAcl` only ever holds RFC
 * 4314 rights. {@see getString()} can re-collapse them for a legacy
 * RFC 2086 server.
 *
 * Immutable. The legacy `ArrayAccess`/`Iterator`/`Serializable` surface is
 * dropped in favour of {@see has()}, {@see rights()} and stringification.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapAcl implements Stringable
{
    /**
     * RFC 2086 virtual rights and their RFC 4314 component letters
     * (RFC 4314 §2.1.1).
     */
    private const VIRTUAL = [
        'c' => ['k', 'x'],
        'd' => ['t', 'x', 'e'],
    ];

    /** @var list<string> The normalized RFC 4314 right letters. */
    private readonly array $rights;

    public function __construct(string $rights = '')
    {
        $this->rights = self::normalize(str_split($rights));
    }

    /**
     * Whether this ACL grants a right (an {@see AclRight} case or its
     * single-letter string).
     */
    public function has(AclRight|string $right): bool
    {
        $letter = $right instanceof AclRight ? $right->value : $right;

        return in_array($letter, $this->rights, true);
    }

    /**
     * The RFC 4314 right letters, in canonical (sorted) order.
     *
     * @return list<string>
     */
    public function rights(): array
    {
        return $this->rights;
    }

    /**
     * The rights that would be added and removed to reach `$other`
     * (virtual rights ignored).
     *
     * @return array{added: string, removed: string}
     */
    public function diff(string $other): array
    {
        $target = array_diff(str_split($other), array_keys(self::VIRTUAL));

        return [
            'added' => implode('', array_diff($target, $this->rights)),
            'removed' => implode('', array_diff($this->rights, $target)),
        ];
    }

    /**
     * The wire string for IMAP calls. RFC 4314 form by default; with
     * `$rfc2086` the RFC 4314 component rights are collapsed back into the
     * virtual `c`/`d` letters for a legacy server.
     */
    public function getString(bool $rfc2086 = false): string
    {
        $acl = (string) $this;

        if (!$rfc2086) {
            return $acl;
        }

        foreach (self::VIRTUAL as $virtual => $components) {
            $acl = str_replace($components, '', $acl, $count);

            if ($count) {
                $acl .= $virtual;
            }
        }

        return $acl;
    }

    public function __toString(): string
    {
        return implode('', $this->rights);
    }

    /**
     * Expand any RFC 2086 virtual rights into their RFC 4314 components
     * and drop the virtual letters (RFC 4314 §2.1.1).
     *
     * @param list<string> $rights
     *
     * @return list<string>
     */
    private static function normalize(array $rights): array
    {
        $expanded = [];

        foreach ($rights as $right) {
            if (isset(self::VIRTUAL[$right])) {
                foreach (self::VIRTUAL[$right] as $component) {
                    $expanded[$component] = true;
                }

                continue;
            }

            $expanded[$right] = true;
        }

        // Drop the virtual letters themselves; keep only real rights.
        unset($expanded['c'], $expanded['d']);

        $letters = array_keys($expanded);
        sort($letters);

        return $letters;
    }
}
