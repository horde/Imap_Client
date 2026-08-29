# Upgrading Horde_Imap_Client

Contact: dev@lists.horde.org

This lists the API changes between releases of the package.

## Upgrading to 3.0.0 (src/ PSR-4 rewrite)

Version 3.0 adds a modern PSR-4 codebase under `src/` (namespace
`Horde\Imap\Client`) alongside the legacy PSR-0 classes in `lib/`. Both
autoload roots are active at once. The `lib/` engine remains functional
and unchanged; integrators move to the new model at their own pace.

The new model replaces the single `Horde_Imap_Client_Socket` god-class
and its `Horde_Imap_Client_Base` factory with a small set of focused,
final classes and immutable value objects. Two concrete clients are
provided: `ImapClient` (IMAP4rev1 / IMAP4rev2) and `Pop3Client` (POP3).

### Constructing a client

The untyped configuration array is replaced by a typed `ConnectionConfig`
value object and credentials by `Horde\Sasl` value objects.

```php
// BEFORE (2.x)
$client = new Horde_Imap_Client_Socket([
    'username' => 'alice',
    'password' => 'secret',
    'hostspec' => 'imap.example.com',
    'port'     => 993,
    'secure'   => 'ssl',
]);
$client->login();

// AFTER (3.x)
use Horde\Imap\Client\ConnectionConfig;
use Horde\Imap\Client\ImapClient;
use Horde\Imap\Client\SecureMode;
use Horde\Sasl\Credentials\PasswordCredentials;
use Horde\Sasl\Credentials\PlainSecret;

$config = new ConnectionConfig(
    hostspec: 'imap.example.com',
    port: 993,
    secure: SecureMode::Ssl,
);
$credentials = new PasswordCredentials('alice', new PlainSecret('secret'));

$client = new ImapClient($config, $credentials);
$client->login();
```

### Class mapping

| Legacy (lib/) | Modern (src/) |
|---|---|
| `Horde_Imap_Client_Socket` | `Horde\Imap\Client\ImapClient` |
| `Horde_Imap_Client_Socket_Pop3` | `Horde\Imap\Client\Pop3Client` |
| `Horde_Imap_Client_Base` (config array) | `Horde\Imap\Client\ConnectionConfig` |
| `Horde_Imap_Client_Ids` | `Horde\Imap\Client\ImapIdSet` / `Pop3IdSet` |
| `Horde_Imap_Client_Search_Query` | `Horde\Imap\Client\ImapSearchQuery` |
| `Horde_Imap_Client_Fetch_Query` | `Horde\Imap\Client\ImapFetchQuery` / `Pop3FetchQuery` |
| `Horde_Imap_Client_Data_Fetch` | `Horde\Imap\Client\ImapFetchResult` |
| `Horde_Imap_Client_Data_Envelope` | `Horde\Imap\Client\ImapEnvelope` |
| `Horde_Imap_Client_Data_Thread` | `Horde\Imap\Client\ImapThreadResult` |
| `Horde_Imap_Client_Data_Namespace` / `_Namespace_List` | `Horde\Imap\Client\ImapNamespace` / `ImapNamespaceList` |
| `Horde_Imap_Client_Data_Acl` / `_AclRights` | `Horde\Imap\Client\ImapAcl` / `ImapAclRights` |
| `Horde_Imap_Client_Data_Capability_Imap` | `Horde\Imap\Client\ImapCapability` |
| `Horde_Imap_Client_Cache` + `Cache_Backend_*` | `Horde\Imap\Client\ImapCacheStore` (PSR-16 backed) |
| *(none)* | `Horde\Imap\Client\ImapSyncResult` / `SyncCriteria` (new) |
| *(none)* | `Horde\Imap\Client\ImapQresyncResult` (new) |

### Enums replace integer/string constants

The `Horde_Imap_Client::*` constant groups become backed enums, at the
same underlying values for a mechanical migration:

| Legacy constants | Modern enum |
|---|---|
| `OPEN_READONLY` / `OPEN_READWRITE` / `OPEN_AUTO` | `OpenMode` |
| `MBOX_SUBSCRIBED` / `MBOX_ALL` / ... | `MailboxListMode` |
| `STATUS_MESSAGES` / `STATUS_UNSEEN` / ... | `StatusFlag` |
| `SORT_ARRIVAL` / `SORT_DATE` / ... | `SortCriteria` |
| `SEARCH_RESULTS_COUNT` / `_MATCH` / ... | `SearchResultType` |
| `THREAD_ORDEREDSUBJECT` / `_REFERENCES` / `_REFS` | `ThreadAlgorithm` |
| `FLAG_SEEN` / `FLAG_DELETED` / ... | `SystemFlag` |
| `SPECIALUSE_SENT` / `_DRAFTS` / ... | `SpecialUse` |
| `ACL_LOOKUP` / `_READ` / ... | `AclRight` |

### Result objects and IDs

Fetch results implement the `MessageMetadata`, `MessageContent`,
`PartAccess` and `ParsedAccess` interfaces and reuse the `Horde\Mime` and
`Horde\Mail` value objects (`getStructure()` returns a `Horde\Mime\Part`,
`getEnvelope()` an `ImapEnvelope`). Message ID sets are `ImapIdSet`
(range-aware) built through `getIdsOb()`, replacing `Horde_Imap_Client_Ids`.

```php
// AFTER (3.x): fetch envelopes and flags
use Horde\Imap\Client\ImapFetchQuery;
use Horde\Imap\Client\OpenMode;

$client->openMailbox('INBOX', OpenMode::Readonly);
$query = (new ImapFetchQuery())->envelope()->flags()->size();

foreach ($client->fetch('INBOX', $client->getIdsOb('1:*'), $query) as $uid => $msg) {
    echo $uid . ': ' . $msg->getEnvelope()->subject . "\n";
}
```

### Caching

The `Horde_Imap_Client_Cache_Backend_*` hierarchy (Db, Hashtable, Mongo,
Cache, Null) and the `Horde_Imap_Client_Cache` orchestrator are replaced
by a single `ImapCacheStore` backed by any PSR-16 `CacheInterface`.
Caching is opt-in: pass an `ImapCacheStore` as the fifth `ImapClient`
constructor argument and it is used transparently across `fetch()`,
`store()`, `expunge()` and `deleteMailbox()`. Which storage engine backs
it (SQL, Redis, file, ...) is now the PSR-16 implementation's concern, not
this library's. If you want a SQL-indexed IMAP cache, supply a SQL-backed
`CacheInterface`.

### Deliberately dropped

- The `Serializable` interface throughout: value objects use
  `__serialize`/`__unserialize` only.
- The `cclient` (C-library) driver: removed in 2.0 already, not carried
  forward.
- The client-side `SORT`/`THREAD` fallbacks and the client-side
  `ORDEREDSUBJECT` threading: a server that lacks `SORT` / `THREAD=<algo>`
  now raises `CapabilityNotSupportedException` rather than sorting in PHP.
- The `ANNOTATEMORE` / `ANNOTATEMORE2` metadata fallback: superseded by
  RFC 5464 `METADATA`, which every maintained server speaks.
- The Cyrus 2.4.7 (2011) broken-ESEARCH workaround.

### Deprecation timeline

The `lib/` classes (`Horde_Imap_Client_*`) remain functional in 3.x but
are not actively maintained. They will be removed in a future major
version.

---

## Legacy release history (2.x and 1.x, lib/ API)

The entries below are the pre-3.0 `lib/` API changes, preserved from the
former `doc/Horde/Imap/Client/UPGRADING.rst`.

### Upgrading to 3.0.0 (lib/)

- `Horde_Imap_Client_Cache_Backend_Hashtable`: Deprecated. The per-UID
  HashTable storage strategy was justified for Memcache 1.0 with poor
  multi-get; modern Redis with MGET/pipelining handles the sliced strategy
  of `Backend_Cache` equally well. Use `Backend_Cache` with `Horde_Cache`
  configured to wrap a HashTable storage instead. Existing IMP
  configurations setting `cache.driver = 'hashtable'` continue to work
  (the IMP wrapper falls through to `Backend_Cache` during the deprecation
  period); update to `cache.driver = 'cache'` when convenient.

### Upgrading to 2.29.0

- SCRAM-SHA-1 authentication is now supported by both the IMAP and POP3
  drivers (`Horde_Imap_Client_Auth_Scram` added).

### Upgrading to 2.21.0 through 2.28.0

- Incremental additions across the 2.2x line: `capability`,
  `search_charset` and `url` properties on the base object; namespace list
  objects; the alerts observer; non-ASCII `Data_Format` classes; and
  command `on_error`/`on_success`/`pipeline` handling.

### Upgrading to 2.1.0 through 2.20.0

- XOAUTH2 token support; TLS options; cache backend refactors; and the
  deprecation of `capability()`, `queryCapability()`, `statusMultiple()`
  and `getCacheId()`.

### Upgrading to 2.0.0

- The `cclient` drivers were removed; instantiate the socket driver
  directly instead of via `factory()` (also removed).
- Exception logging was removed.
- Mailbox, source and destination parameters can no longer be
  UTF7-IMAP strings across the ~30 affected `Horde_Imap_Client_Base`
  methods; pass `Horde_Imap_Client_Mailbox` objects or UTF-8.
- Results are returned as objects (`Fetch_Results`, `Ids`, `Mailbox`,
  `Rfc822_List`).
- The `Utils`, `Sort` and `Utils_Pop3` classes were removed.

### Upgrading to 1.1.0 through 1.5.0

- 1.2.0 introduced `Horde_Imap_Client_Mailbox` objects, required UTF-8
  (deprecating auto-detection), and added `getIdsOb()` and the `Ids` /
  `Mailbox` / `Ids_Pop3` objects.
- 1.1.0 added the decoded envelope properties.

The complete, unabridged pre-3.0 history remains available in the git
history of `doc/Horde/Imap/Client/UPGRADING.rst`.
