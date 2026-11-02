# Stream input for modern APPEND

`Horde\Imap\Client\ImapClient::append()` accepts a readable, seekable PHP
stream resource in a message's `data` field:

```php
$message = fopen('/private/message.eml', 'rb');
try {
    $uids = $client->append('Objects', [['data' => $message]]);
} finally {
    fclose($message);
}
```

Pass a complete MIME message, not a bare attachment. The client sends bytes
from the stream's **current position through EOF**, with no newline or MIME
transformations. Rewind first if you just finished writing the message and
want to send it from the beginning. The original position is restored, and
the resource remains open and owned by the caller after success or failure.
Do not modify or close the stream while APPEND is running.

Stream input always uses an IMAP literal, including zero-length and
atom-safe content. The implementation determines length by seeking and
scans for NUL using bounded buffers, then writes chunks of at most 64 KiB
after the server accepts the literal announcement. It never calls
`stream_get_contents()` to materialize the whole message and does not make
a temporary copy. A source without NUL requires a complete scan before
sending; detecting NUL early can avoid the remaining scan.

A message containing NUL requires server BINARY support and is sent using
literal8 (`~{n}`), as specified in RFC 3516. Under enabled UTF8=ACCEPT, stream
input uses the RFC 6855 `UTF8 (~{n}...)` form. MIME headers and boundaries
must already have CRLF line endings. For arbitrary binary payloads, use an
appropriate MIME type and `Content-Transfer-Encoding: binary`.

Non-seekable sources, including typical pipes, must be spooled by the
caller to a seekable temporary stream first. Seekability supplies the byte
count needed before the literal and lets the client replay the same data
when `create => true` handles a server's TRYCREATE response. Stream inputs
are validated before any message in an APPEND batch is sent, including
when MULTIAPPEND is unavailable.

Strings and streams can be mixed in a MULTIAPPEND batch. Existing string
and CATENATE inputs retain their existing behavior; this change does not
add stream resources to CATENATE text parts or change the legacy API.

If a source fails while its announced literal is being transmitted, the
connection is closed because another command could otherwise be read as
the missing payload bytes. Discard that client connection and reconcile
the upload before retrying: a transport failure can leave an uncertain
server outcome.
