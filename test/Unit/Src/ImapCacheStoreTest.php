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
 * The PSR-16-backed {@see ImapCacheStore}.
 */
#[CoversClass(ImapCacheStore::class)]
class ImapCacheStoreTest extends TestCase
{
    private function store(ArrayCache $cache): ImapCacheStore
    {
        return new ImapCacheStore($cache, 'imap.example.test', 993, 'alice');
    }

    public function testSetAndGetRoundTrip(): void
    {
        $cache = new ArrayCache();
        $store = $this->store($cache);

        $store->set('INBOX', [
            5 => ['flags' => ['\\Seen'], 'size' => 100],
            7 => ['flags' => [], 'size' => 200],
        ], 42);
        $store->flush();

        $got = $store->get('INBOX', [5, 7], [], 42);

        self::assertSame(['\\Seen'], $got[5]['flags']);
        self::assertSame(200, $got[7]['size']);
    }

    public function testGetReadsFromWriteBufferBeforeFlush(): void
    {
        $cache = new ArrayCache();
        $store = $this->store($cache);

        $store->set('INBOX', [5 => ['size' => 100]], 42);

        // No flush yet: the value is still only in the buffer.
        $got = $store->get('INBOX', [5], [], 42);
        self::assertSame(100, $got[5]['size']);
    }

    public function testSetMergesFieldsForSameUid(): void
    {
        $cache = new ArrayCache();
        $store = $this->store($cache);

        $store->set('INBOX', [5 => ['size' => 100]], 42);
        $store->set('INBOX', [5 => ['flags' => ['\\Seen']]], 42);
        $store->flush();

        $got = $store->get('INBOX', [5], [], 42);
        self::assertSame(100, $got[5]['size']);
        self::assertSame(['\\Seen'], $got[5]['flags']);
    }

    public function testGetWithFieldFilter(): void
    {
        $cache = new ArrayCache();
        $store = $this->store($cache);

        $store->set('INBOX', [5 => ['flags' => [], 'size' => 100, 'envelope' => 'x']], 42);
        $store->flush();

        $got = $store->get('INBOX', [5], ['size'], 42);
        self::assertSame(['size' => 100], $got[5]);
    }

    public function testGetCachedUids(): void
    {
        $cache = new ArrayCache();
        $store = $this->store($cache);

        $store->set('INBOX', [5 => ['size' => 1], 7 => ['size' => 2], 9 => ['size' => 3]], 42);
        $store->flush();

        $uids = $store->getCachedUids('INBOX', 42);
        sort($uids);
        self::assertSame([5, 7, 9], $uids);
    }

    public function testUidValidityChangeInvalidatesCache(): void
    {
        $cache = new ArrayCache();
        $store = $this->store($cache);

        $store->set('INBOX', [5 => ['size' => 100]], 42);
        $store->flush();

        // A different UIDVALIDITY means the UID space was reassigned.
        self::assertSame([], $store->get('INBOX', [5], [], 99));
        self::assertSame([], $store->getCachedUids('INBOX', 99));
    }

    public function testDeleteMsgs(): void
    {
        $cache = new ArrayCache();
        $store = $this->store($cache);

        $store->set('INBOX', [5 => ['size' => 1], 7 => ['size' => 2]], 42);
        $store->flush();

        $store->deleteMsgs('INBOX', [5]);
        $store->flush();

        self::assertSame([7], $store->getCachedUids('INBOX', 42));
        self::assertArrayNotHasKey(5, $store->get('INBOX', [5, 7], [], 42));
    }

    public function testDeleteMailbox(): void
    {
        $cache = new ArrayCache();
        $store = $this->store($cache);

        $store->set('INBOX', [5 => ['size' => 1]], 42);
        $store->flush();

        $store->deleteMailbox('INBOX');

        self::assertSame([], $store->getCachedUids('INBOX', 42));
    }

    public function testMetadataRoundTrip(): void
    {
        $cache = new ArrayCache();
        $store = $this->store($cache);

        $store->setMetadata('INBOX', ['uidvalid' => 42, 'highestmodseq' => 715]);
        $store->flush();

        $meta = $store->getMetadata('INBOX', 42, []);
        self::assertSame(42, $meta['uidvalid']);
        self::assertSame(715, $meta['highestmodseq']);
    }

    public function testMetadataUidValidityMismatchReturnsOnlyUidvalid(): void
    {
        $cache = new ArrayCache();
        $store = $this->store($cache);

        $store->setMetadata('INBOX', ['uidvalid' => 42, 'highestmodseq' => 715]);
        $store->flush();

        $meta = $store->getMetadata('INBOX', 99, []);
        self::assertSame(['uidvalid' => 99], $meta);
    }

    public function testDestructFlushesPendingWrites(): void
    {
        $cache = new ArrayCache();
        $store = $this->store($cache);

        $store->set('INBOX', [5 => ['size' => 100]], 42);
        // Trigger the destructor flush by dropping the only reference.
        unset($store);

        self::assertNotSame([], $cache->data);
    }

    public function testSeparateAccountsDoNotCollide(): void
    {
        $cache = new ArrayCache();
        $alice = new ImapCacheStore($cache, 'imap.example.test', 993, 'alice');
        $bob = new ImapCacheStore($cache, 'imap.example.test', 993, 'bob');

        $alice->set('INBOX', [5 => ['size' => 1]], 42);
        $alice->flush();

        // Bob sees nothing of Alice's cache.
        self::assertSame([], $bob->getCachedUids('INBOX', 42));
    }
}
