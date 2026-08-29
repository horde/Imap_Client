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
 * One parsed IMAP response line: Tagged, untagged, or a continuation
 * request (RFC 3501 §7).
 *
 * Deliberately non-throwing same as {@see Pop3StatusLine}: A tagged `NO`/`BAD`
 * is handed back as plain data so callers with different needs (an ordinary command that wants an exception, versus
 * a SASL exchange that wants to route it into a
 * {@see Auth\ChannelEvent} failure) can each decide what it means to
 * them.
 *
 * `$data` holds whatever tokens were not consumed as tag/status/code:
 * for an untagged data response (`* 1 EXISTS`, `* LIST (...) "/" INBOX`,
 * `* CAPABILITY ...`), that is the entire response, since those never
 * carry a status word.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapResponse
{
    /**
     * @param list<mixed> $data Unconsumed tokens (see class docblock).
     */
    public function __construct(
        public readonly ImapResponseKind $kind,
        public readonly ?string $tag = null,
        public readonly ?ImapResponseStatus $status = null,
        public readonly ?ImapResponseCode $responseCode = null,
        public readonly array $data = [],
        public readonly string $text = '',
    ) {}

    public function isTagged(): bool
    {
        return $this->kind === ImapResponseKind::Tagged;
    }

    public function isUntagged(): bool
    {
        return $this->kind === ImapResponseKind::Untagged;
    }

    public function isContinuation(): bool
    {
        return $this->kind === ImapResponseKind::Continuation;
    }

    public function isOk(): bool
    {
        return $this->status === ImapResponseStatus::Ok;
    }

    public function isNo(): bool
    {
        return $this->status === ImapResponseStatus::No;
    }

    public function isBad(): bool
    {
        return $this->status === ImapResponseStatus::Bad;
    }

    public function isBye(): bool
    {
        return $this->status === ImapResponseStatus::Bye;
    }

    public function isPreAuth(): bool
    {
        return $this->status === ImapResponseStatus::PreAuth;
    }
}
