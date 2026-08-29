<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright  2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit\Base;

use Horde\Imap\Client\Test\Stub\Base;
use Horde\Imap\Client\Test\Stub\CacheBackend;
use Horde_Imap_Client;
use Horde_Imap_Client_Base;
use Horde_Imap_Client_Ids;
use Horde_Imap_Client_Mailbox;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Tests that Horde_Imap_Client_Base::_deleteMsgs() invalidates any cached
 * SEARCH results for a mailbox when messages are expunged or moved out.
 *
 * @See https://github.com/horde/Imap_Client/pull/52
 *
 * A MOVE that does not visibly advance the tracked sync token would
 * otherwise leave stale UIDs in the SEARCH cache, so removing messages must
 * drop that cache regardless of the sync token.
 *
 * @copyright  2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class DeleteMsgsSearchCacheTest extends TestCase
{
    private CacheBackend $backend;

    private Base $ob;

    public function setUp(): void
    {
        $this->backend = new CacheBackend();
        $this->ob = new Base([
            'username' => 'user',
            'password' => 'pass',
            'cache' => [
                'fields' => [Horde_Imap_Client::FETCH_ENVELOPE],
                'backend' => $this->backend,
            ],
        ]);
    }

    public function testDeleteMsgsClearsCachedSearchResults(): void
    {
        /* A prior SEARCH cached its result set for this mailbox. */
        $this->backend->metadata['INBOX'] = [
            Horde_Imap_Client_Base::CACHE_SEARCH => [
                'somehash' => [1, 2, 3],
            ],
            Horde_Imap_Client_Base::CACHE_SEARCHID => 'cache-token-1',
        ];

        $this->ob->deleteMsgs(
            new Horde_Imap_Client_Mailbox('INBOX'),
            new Horde_Imap_Client_Ids([2], false)
        );

        $md = $this->backend->metadata['INBOX'];
        $this->assertSame([], $md[Horde_Imap_Client_Base::CACHE_SEARCH]);
        $this->assertNull($md[Horde_Imap_Client_Base::CACHE_SEARCHID]);
    }

    public function testDeleteMsgsLeavesCacheUntouchedWhenNoSearchCached(): void
    {
        /* No SEARCH was ever cached. Nothing to invalidate. */
        $this->ob->deleteMsgs(
            new Horde_Imap_Client_Mailbox('INBOX'),
            new Horde_Imap_Client_Ids([2], false)
        );

        $md = $this->backend->metadata['INBOX'] ?? [];
        $this->assertArrayNotHasKey(
            Horde_Imap_Client_Base::CACHE_SEARCH,
            $md
        );
        $this->assertArrayNotHasKey(
            Horde_Imap_Client_Base::CACHE_SEARCHID,
            $md
        );
    }
}
