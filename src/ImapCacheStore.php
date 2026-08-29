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

use Psr\SimpleCache\CacheInterface;

/**
 * An IMAP message/metadata cache backed by any PSR-16 store.
 *
 * Replaces the entire legacy `Cache/Backend/*` hierarchy (abstract base
 * plus five backends plus the orchestrator) with one class. Storage
 * strategy is the PSR-16 implementation's concern; this class only owns
 * the IMAP-specific keying (host + port + user + mailbox + UIDVALIDITY +
 * UID), UIDVALIDITY invalidation, and an in-memory write buffer flushed
 * on {@see flush()} or destruction (replacing the old PSR-6 deferred
 * writes).
 *
 * Each mailbox keeps a small index entry (`{uidvalidity, uids, meta}`)
 * so {@see getCachedUids()} and the UIDVALIDITY check are single reads,
 * and each cached UID's field array lives under its own key so a batch
 * fetch maps onto `getMultiple()`. A UIDVALIDITY that no longer matches
 * the stored one drops the mailbox's cache (RFC 3501 §2.3.1.1: the UID
 * space has been reassigned).
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapCacheStore
{
    /**
     * Per-mailbox index buffer, keyed by mailbox name. Each entry is
     * `{uidvalidity: int, uids: array<int, true>, meta: array<string, mixed>}`.
     * Loaded lazily, mutated by set/delete, and written back on flush.
     *
     * @var array<string, array{uidvalidity: int, uids: array<int, true>, meta: array<string, mixed>}>
     */
    private array $index = [];

    /**
     * Buffered per-UID field writes, keyed by PSR-16 key. Flushed via
     * `setMultiple()`.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $pending = [];

    /**
     * Mailbox index keys touched since the last flush.
     *
     * @var array<string, true>
     */
    private array $dirtyIndex = [];

    private readonly string $scope;

    public function __construct(
        private readonly CacheInterface $cache,
        string $hostspec,
        int $port,
        string $username,
    ) {
        // One namespace per account so two accounts on the same PSR-16
        // store never collide.
        $this->scope = substr(hash('sha256', $hostspec . ':' . $port . ':' . $username), 0, 16);
    }

    /**
     * Retrieve cached field data for a set of UIDs.
     *
     * @param list<int>    $uids   The UIDs wanted.
     * @param list<string> $fields The fields wanted; empty for all cached
     *                             fields of each UID.
     *
     * @return array<int, array<string, mixed>> Field data keyed by UID.
     *         A UID absent from the cache is omitted.
     */
    public function get(string $mailbox, array $uids, array $fields, int $uidvalidity): array
    {
        if (!$this->indexValid($mailbox, $uidvalidity) || $uids === []) {
            return [];
        }

        $keys = [];
        foreach ($uids as $uid) {
            $keys[$this->uidKey($mailbox, $uidvalidity, $uid)] = $uid;
        }

        $out = [];

        foreach ($this->cache->getMultiple(array_keys($keys)) as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            // Merge any not-yet-flushed writes for this UID on top.
            if (isset($this->pending[$key])) {
                $value = array_merge($value, $this->pending[$key]);
            }

            $uid = $keys[$key];
            $out[$uid] = $fields === [] ? $value : array_intersect_key($value, array_flip($fields));
        }

        // Include UIDs that exist only in the write buffer.
        foreach ($keys as $key => $uid) {
            if (!isset($out[$uid]) && isset($this->pending[$key])) {
                $value = $this->pending[$key];
                $out[$uid] = $fields === [] ? $value : array_intersect_key($value, array_flip($fields));
            }
        }

        return $out;
    }

    /**
     * The list of UIDs currently cached for a mailbox (unsorted).
     *
     * @return list<int>
     */
    public function getCachedUids(string $mailbox, int $uidvalidity): array
    {
        if (!$this->indexValid($mailbox, $uidvalidity)) {
            return [];
        }

        return array_map('intval', array_keys($this->index[$mailbox]['uids']));
    }

    /**
     * Buffer field data for a set of UIDs. Values are merged with any
     * already-cached fields for the same UID. Nothing is written to the
     * PSR-16 store until {@see flush()}.
     *
     * @param array<int, array<string, mixed>> $data Field data keyed by UID.
     */
    public function set(string $mailbox, array $data, int $uidvalidity): void
    {
        if ($data === []) {
            return;
        }

        $this->loadIndex($mailbox);

        // A changed UIDVALIDITY invalidates every earlier entry (RFC 3501
        // §2.3.1.1); start the mailbox's cache over at the new value.
        if ($this->index[$mailbox]['uidvalidity'] !== $uidvalidity) {
            $this->dropMailbox($mailbox);
            $this->index[$mailbox]['uidvalidity'] = $uidvalidity;
        }

        foreach ($data as $uid => $fields) {
            $uid = (int) $uid;
            $key = $this->uidKey($mailbox, $uidvalidity, $uid);
            $existing = $this->pending[$key] ?? [];
            $this->pending[$key] = array_merge($existing, $fields);
            $this->index[$mailbox]['uids'][$uid] = true;
        }

        $this->dirtyIndex[$mailbox] = true;
    }

    /**
     * Mailbox-level metadata (for example the last-seen HIGHESTMODSEQ).
     * `uidvalid` is always present, from the index.
     *
     * @param list<string> $entries Entry names wanted; empty for all.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(string $mailbox, int $uidvalidity, array $entries): array
    {
        $this->loadIndex($mailbox);

        if ($this->index[$mailbox]['uidvalidity'] !== $uidvalidity) {
            return ['uidvalid' => $uidvalidity];
        }

        $meta = $this->index[$mailbox]['meta'];
        $meta['uidvalid'] = $this->index[$mailbox]['uidvalidity'];

        return $entries === [] ? $meta : array_intersect_key($meta, array_flip([...$entries, 'uidvalid']));
    }

    /**
     * Store mailbox-level metadata. A `uidvalid` key updates the stored
     * UIDVALIDITY (and drops the mailbox cache if it changed).
     *
     * @param array<string, mixed> $data
     */
    public function setMetadata(string $mailbox, array $data): void
    {
        $this->loadIndex($mailbox);

        if (isset($data['uidvalid'])) {
            $uidvalidity = (int) $data['uidvalid'];
            unset($data['uidvalid']);

            if ($this->index[$mailbox]['uidvalidity'] !== $uidvalidity) {
                $this->dropMailbox($mailbox);
                $this->index[$mailbox]['uidvalidity'] = $uidvalidity;
            }
        }

        if ($data !== []) {
            $this->index[$mailbox]['meta'] = array_merge($this->index[$mailbox]['meta'], $data);
        }

        $this->dirtyIndex[$mailbox] = true;
    }

    /**
     * Remove cached data for a set of UIDs.
     *
     * @param list<int> $uids
     */
    public function deleteMsgs(string $mailbox, array $uids): void
    {
        if ($uids === []) {
            return;
        }

        $this->loadIndex($mailbox);
        $uidvalidity = $this->index[$mailbox]['uidvalidity'];
        $keys = [];

        foreach ($uids as $uid) {
            $uid = (int) $uid;
            $key = $this->uidKey($mailbox, $uidvalidity, $uid);
            $keys[] = $key;
            unset($this->pending[$key], $this->index[$mailbox]['uids'][$uid]);
        }

        $this->cache->deleteMultiple($keys);
        $this->dirtyIndex[$mailbox] = true;
    }

    /**
     * Drop a mailbox entirely from the cache.
     */
    public function deleteMailbox(string $mailbox): void
    {
        $this->loadIndex($mailbox);
        $this->dropMailbox($mailbox);
        $this->cache->delete($this->indexKey($mailbox));

        unset($this->index[$mailbox], $this->dirtyIndex[$mailbox]);
    }

    /**
     * Write all buffered data to the PSR-16 store. Called automatically
     * on destruction, but a caller may flush explicitly (for example
     * before a long-running operation).
     */
    public function flush(): void
    {
        if ($this->pending !== []) {
            $this->cache->setMultiple($this->pending);
            $this->pending = [];
        }

        foreach (array_keys($this->dirtyIndex) as $mailbox) {
            $this->cache->set($this->indexKey($mailbox), $this->index[$mailbox]);
        }

        $this->dirtyIndex = [];
    }

    public function __destruct()
    {
        $this->flush();
    }

    /**
     * Load a mailbox's index entry from the store into memory, if not
     * already loaded, initializing an empty one otherwise.
     */
    private function loadIndex(string $mailbox): void
    {
        if (isset($this->index[$mailbox])) {
            return;
        }

        $stored = $this->cache->get($this->indexKey($mailbox));

        $this->index[$mailbox] = (is_array($stored) && isset($stored['uidvalidity']))
            ? [
                'uidvalidity' => (int) $stored['uidvalidity'],
                'uids' => is_array($stored['uids'] ?? null) ? $stored['uids'] : [],
                'meta' => is_array($stored['meta'] ?? null) ? $stored['meta'] : [],
            ]
            : ['uidvalidity' => 0, 'uids' => [], 'meta' => []];
    }

    /**
     * Whether the mailbox is cached at the given UIDVALIDITY. A mismatch
     * drops the stale cache.
     */
    private function indexValid(string $mailbox, int $uidvalidity): bool
    {
        $this->loadIndex($mailbox);

        if ($this->index[$mailbox]['uidvalidity'] === 0) {
            return false;
        }

        if ($this->index[$mailbox]['uidvalidity'] !== $uidvalidity) {
            $this->dropMailbox($mailbox);
            $this->index[$mailbox]['uidvalidity'] = $uidvalidity;
            $this->dirtyIndex[$mailbox] = true;

            return false;
        }

        return true;
    }

    /**
     * Delete every cached UID key for a mailbox and reset its in-memory
     * index (keeping the entry so a fresh UIDVALIDITY can be set).
     */
    private function dropMailbox(string $mailbox): void
    {
        $uidvalidity = $this->index[$mailbox]['uidvalidity'];
        $keys = [];

        foreach (array_keys($this->index[$mailbox]['uids']) as $uid) {
            $key = $this->uidKey($mailbox, $uidvalidity, (int) $uid);
            $keys[] = $key;
            unset($this->pending[$key]);
        }

        if ($keys !== []) {
            $this->cache->deleteMultiple($keys);
        }

        $this->index[$mailbox]['uids'] = [];
        $this->index[$mailbox]['meta'] = [];
    }

    private function indexKey(string $mailbox): string
    {
        return 'imap:' . $this->scope . ':idx:' . $this->hashPart($mailbox);
    }

    private function uidKey(string $mailbox, int $uidvalidity, int $uid): string
    {
        return 'imap:' . $this->scope . ':' . $this->hashPart($mailbox) . ':' . $uidvalidity . ':' . $uid;
    }

    /**
     * Hash a mailbox name into a PSR-16-safe key segment (RFC-reserved
     * PSR-16 characters `{}()/\@:` cannot appear in a key literally, and a
     * mailbox name may contain any of them).
     */
    private function hashPart(string $value): string
    {
        return substr(hash('sha256', $value), 0, 24);
    }
}
