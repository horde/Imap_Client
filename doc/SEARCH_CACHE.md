# Caching search results

The modern `Horde\Imap\Client\ImapClient` deliberately does **not** cache
`SEARCH` results. This document explains how an application can
build and correctly invalidate its own search cache using the signals the
client exposes.

## Why the library does not cache searches

`ImapClient` caches per-message data and per-mailbox metadata through the
optional `ImapCacheStore` for envelope, flags, structure, size and similar
immutable-per-UID fields. Those are protocol facts the client can
invalidate deterministically. When a UID is expunged it is dropped, when
`UIDVALIDITY` changes the mailbox cache is dropped.

A `SEARCH` result set is different. It is a *derived query answer*.
Whether a cached answer is still acceptable is a policy decision that
depends on the consumer, not on the IMAP protocol. A message-list view, a
background indexer, and a filter rule each tolerate different staleness. A
library should not hardcode one invalidation policy on behalf of the consumer.

Instead the client reports *what changed* and leaves the caching policy to the application.

`ImapClient::search()` therefore always issues the command and returns a fresh `ImapSearchResult`.

## What the client provides

### Authoritative reconciliation: `sync()`

`getSyncToken(string $mailbox): string` captures the mailbox state
(`UIDVALIDITY` + `HIGHESTMODSEQ` + `UIDNEXT`) as an opaque token.

`sync(string $mailbox, string $token, array $criteria = []): ImapSyncResult`
diffs the current state against a stored token and returns:

- `newMsgs`: UIDs that appeared since the token,
- `flagChanges`: UIDs whose flags changed,
- `vanished`: UIDs that were removed.

A changed `UIDVALIDITY` (or a malformed token) raises `SyncException`,
meaning the UID space was reassigned and every cached result for that
mailbox is stale.

This is the *authoritative* mechanism. Store the sync token beside each
cached search result. Before serving a cached result, call `sync()` and
decide, per your policy, whether the reported `vanished` / `flagChanges`
sets invalidate it. `sync()` also recovers from gaps (for example a
reconnect during which live events were missed).

### Reactive signals: PSR-14 events

If you pass a PSR-14 `EventDispatcherInterface` to the client constructor,
it dispatches typed domain events you can listen for to invalidate
immediately, without polling:

- `Event\MailboxSelected`: A mailbox was opened. Fields: `mailbox`,
  `uidvalidity`, `uidnext`, `highestmodseq`. A new `uidvalidity` for a
  mailbox you have cached means drop everything for it.
- `Event\MailboxExpunged`: A messages were removed (a plain `EXPUNGE`, a
  `UID EXPUNGE`, or the source side of a `MOVE`). Fields: `mailbox`,
  `vanished` (an `ImapIdSet`), `uidvalidity`.

  Inspect `$event->vanished->isSequence()`:
  - `false`: The set is UIDs (server reported `VANISHED` or you issued a
    `UID EXPUNGE`). Intersect it with your cached result sets and drop only
    the affected entries.
  - `true`: The set is sequence numbers (a plain `EXPUNGE`). Sequence
    numbers are not stable keys, so invalidate the mailbox's cached
    searches conservatively.

These events are plain `ImapEvent` subclasses (not `DiagnosticEvent`), so
the default `FilteredEventDispatcher` passes them through to your listeners.

## Recommended pattern

Combine both: Events for low-latency in-process invalidation and `sync()` as
the authoritative check that also covers missed events.

```php
use Horde\Imap\Client\Event\MailboxExpunged;
use Horde\Imap\Client\Event\MailboxSelected;

// 1. React to in-process mutations.
$listener = function (object $event) use ($searchCache): void {
    if ($event instanceof MailboxExpunged) {
        if ($event->vanished->isSequence()) {
            $searchCache->invalidateMailbox($event->mailbox);
        } else {
            $searchCache->invalidateUids($event->mailbox, $event->vanished->toArray());
        }
    } elseif ($event instanceof MailboxSelected) {
        $searchCache->onUidValidity($event->mailbox, $event->uidvalidity);
    }
};

// 2. Cache a search result together with a sync token.
$token = $client->getSyncToken('INBOX');
$result = $client->search('INBOX', $query);
$searchCache->store('INBOX', $queryKey, $result->match->toArray(), $token);

// 3. Before serving a cached result, reconcile against the server.
[$cachedUids, $token] = $searchCache->load('INBOX', $queryKey);
try {
    $delta = $client->sync('INBOX', $token);
    if ($delta->vanished->count() > 0 /* or your flag-change policy */) {
        $searchCache->invalidate('INBOX', $queryKey);
    }
} catch (\Horde\Imap\Client\Exception\SyncException $e) {
    $searchCache->invalidateMailbox('INBOX'); // UIDVALIDITY changed.
}
```

The library owns none of `$searchCache`'s policy. It only tells you what
changed. You as the integrator decide on how aggressively you invalidate, what you key on and where you
store it.
