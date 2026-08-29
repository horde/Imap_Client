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

namespace Horde\Imap\Client\Test\Unit\Src;

use Horde\Imap\Client\ImapCacheStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * If {@see ImapCacheStore} learns to cache SEARCH results in the future,
 * this test will catch missing invalidation of cached SEARCH results.
 *
 * See https://github.com/horde/Imap_Client/pull/52 for the lib/ variant.
 *
 * The legacy lib client caches SEARCH result sets keyed off the mailbox
 * sync token, which required Base::_deleteMsgs() to explicitly invalidate
 * that cache when messages were expunged or moved out (a MOVE need not
 * advance HIGHESTMODSEQ, so stale UIDs could linger). The PSR-16 store has
 * no equivalent SEARCH cache. The search() method always hits the wire.
 * There is nothing to invalidate and the stale-UID bug cannot occur yet.
 *
 */
#[CoversClass(ImapCacheStore::class)]
class ImapCacheStoreSearchInvariantTest extends TestCase
{
    private function store(ArrayCache $cache): ImapCacheStore
    {
        return new ImapCacheStore($cache, 'imap.example.test', 993, 'alice');
    }

    /**
     * Deleting messages (the expunge/move path) only affects per-UID data
     * and never leaves behind a cached result set, because none is stored.
     */
    public function testDeleteMsgsDoesNotStripAnySearchState(): void
    {
        $cache = new ArrayCache();
        $store = $this->store($cache);

        $store->set('INBOX', [5 => ['size' => 1], 7 => ['size' => 2]], 42);
        $store->setMetadata('INBOX', ['uidvalid' => 42, 'highestmodseq' => 715]);
        $store->flush();

        $store->deleteMsgs('INBOX', [5]);
        $store->flush();

        /* Only the deleted UID is gone; unrelated metadata survives and no
         * search-keyed entry was ever present to become stale. */
        self::assertSame([7], $store->getCachedUids('INBOX', 42));

        $meta = $store->getMetadata('INBOX', 42, []);
        self::assertSame(715, $meta['highestmodseq']);

        foreach ($cache->data as $key => $value) {
            self::assertStringNotContainsStringIgnoringCase(
                'search',
                (string) $key,
                'No cache entry should be keyed to SEARCH results.'
            );
        }
    }
}
