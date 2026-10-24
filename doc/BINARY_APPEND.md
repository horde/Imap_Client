# Byte-preserving binary APPEND (legacy API)

The `Horde_Imap_Client_Socket` API accepts a per-message `binary` flag:

```php
$message = fopen('/private/object-message.eml', 'rb');
try {
    $uids = $client->append('Objects', [
        ['data' => $message, 'binary' => true],
    ]);
} finally {
    fclose($message);
}
```

The input must be a complete, valid MIME message. Construct textual headers
and MIME boundaries using CRLF. For example, arbitrary object bytes can be
the body of a single-part message with these headers:

```text
MIME-Version: 1.0
Content-Type: application/octet-stream
Content-Transfer-Encoding: binary

<object bytes>
```

The displayed header lines and separator above represent CRLF; the body is
preserved verbatim, including NULs, bare CR/LF, and trailing bytes. The
library does not validate or repair MIME structure in binary mode. Streams
must be seekable; they are rewound as with ordinary APPEND and remain owned
by the caller. Horde uses its existing temporary stream storage while
preparing the literal;
this option does not promise a zero-copy path.

Binary mode requires the server's `BINARY` capability and forces the
`~{n}` literal8 syntax even when the content has no NUL. It applies to
strings, stream resources, and text parts of the legacy CATENATE input
form. Binary messages use local assembly even when the server advertises
CATENATE: RFC 4469 permits ordinary literals for TEXT parts, not literal8.
Within a MULTIAPPEND batch the flag applies to each message independently.
Capability requirements are checked before
sending any message in a batch, including when MULTIAPPEND is unavailable.

If the server rejects an explicitly binary APPEND, the client does not
retry it as an ordinary literal. Existing mailbox creation retries retain
the flag. A rejection or disconnect can still leave an uncertain upload
outcome; reconcile before retrying if duplicates matter.

Omitting `binary`, or setting it to false, preserves the existing CRLF
normalization and fallback behavior. The modern namespaced `ImapClient`
API is unchanged by this option.

## Protocol requirements

- [RFC 3516 section 4.4](https://www.rfc-editor.org/rfc/rfc3516#section-4.4)
  extends APPEND with literal8 and forbids lossy transformations by the
  server. A mailbox unable to store binary content rejects it with
  `NO [UNKNOWN-CTE]`.
- [RFC 3516 section 6](https://www.rfc-editor.org/rfc/rfc3516#section-6)
  requires textual line-oriented sections to use CRLF. Binary transport
  does not make malformed textual headers valid.
- [RFC 2045 section 2.9](https://www.rfc-editor.org/rfc/rfc2045#section-2.9)
  defines binary data as permitting any sequence of octets.

The server may store a different content-transfer-encoding without losing
data. Use BINARY FETCH or decode the stored MIME CTE when retrieving the
payload; do not assume BODY FETCH returns identical on-disk message bytes.
