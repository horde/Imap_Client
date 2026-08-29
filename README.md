# Horde_Imap_Client

An IMAP4rev1 / IMAP4rev2 (RFC 3501 / RFC 9051) and POP3 (RFC 1939) client
library for PHP.

The package ships two codebases side by side:

- **`src/`** (namespace `Horde\Imap\Client`): the modern, PSR-4, PHP 8.2+
  rewrite. Small final classes and immutable value objects, built on
  `Horde\Socket\Client` and `Horde\Sasl`. This is the recommended API for
  new code.
- **`lib/`** (`Horde_Imap_Client_*`): the legacy PSR-0 engine, retained for
  backward compatibility. Functional but not actively developed.

## Modern usage

```php
use Horde\Imap\Client\ConnectionConfig;
use Horde\Imap\Client\ImapClient;
use Horde\Imap\Client\ImapFetchQuery;
use Horde\Imap\Client\OpenMode;
use Horde\Imap\Client\SecureMode;
use Horde\Sasl\Credentials\PasswordCredentials;
use Horde\Sasl\Credentials\PlainSecret;

$client = new ImapClient(
    new ConnectionConfig(hostspec: 'imap.example.com', port: 993, secure: SecureMode::Ssl),
    new PasswordCredentials('alice', new PlainSecret('secret')),
);

$client->login();
$client->openMailbox('INBOX', OpenMode::Readonly);

$query = (new ImapFetchQuery())->envelope()->flags();
foreach ($client->fetch('INBOX', $client->getIdsOb('1:*'), $query) as $uid => $msg) {
    echo $uid . ': ' . $msg->getEnvelope()->subject . "\n";
}

$client->logout();
```

`ImapClient` implements `ImapProtocol` (and `MailboxProtocol`), plus the
optional `ImapAclAware`, `ImapQuotaAware` and `ImapMetadataAware`
extension interfaces. `Pop3Client` implements `MailboxProtocol`.

Message caching is opt-in: pass an `ImapCacheStore` (backed by any PSR-16
cache) as the fifth `ImapClient` constructor argument and it is used
transparently across fetch, store and expunge.

## Supported extensions

STARTTLS, SASL (via `Horde\Sasl`), CAPABILITY/ENABLE, IMAP4rev2,
UTF8=ACCEPT, UIDPLUS, MOVE, CONDSTORE/QRESYNC, ESEARCH, SORT/ESORT,
THREAD, LIST-EXTENDED, LIST-STATUS, NAMESPACE, ACL, QUOTA, METADATA,
MULTIAPPEND, CATENATE, BINARY and SEARCH=FUZZY.

## Documentation

- `doc/UPGRADING.md`: migrating from the legacy `lib/` API to the modern
  `src/` clients, with a class-mapping table and before/after examples.
- `doc/examples/imapclient.php`: a runnable, read-only IMAP demonstration
  (capabilities, namespaces, mailbox list, status, fetch, search).
- `doc/examples/pop3client.php`: the equivalent POP3 demonstration.
- `doc/POP3CAPABILITIES.md`: what the POP3 client implements, what it
  deliberately does not and a server-compatibility matrix.

## License

LGPL 2.1. See the enclosed `LICENSE` file.
