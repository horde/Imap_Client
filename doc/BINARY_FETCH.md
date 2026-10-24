# Decoded BINARY FETCH in the modern API

`ImapFetchQuery` supports RFC 3516 server-side removal of MIME transfer
encoding. Offsets and lengths count **decoded bytes**, including when the
stored part uses base64 or quoted-printable:

```php
$client->openMailbox('Objects', OpenMode::Readonly);
$query = (new ImapFetchQuery())
    ->binaryBodyPart('1', start: 0, length: 1048576)
    ->binaryBodyPartSize('1');

foreach ($client->fetch('Objects', $client->getIdsOb([$uid]), $query) as $id => $message) {
    $decoded = $message->getBinaryBodyPart('1');
    if ($decoded === null) {
        throw new RuntimeException('The server did not return the decoded part');
    }
    $resource = $decoded->getResource();
    rewind($resource);
    stream_copy_to_stream($resource, $output);
    $totalDecodedBytes = $message->getBodyPartSize('1');
}
```

The example assumes the modern `ImapFetchQuery` and `OpenMode` classes are
imported and `$output` is an open output stream. `fetch()` uses UIDs by
default. Open the mailbox before fetching; it does not implicitly SELECT.

- `binaryBodyPart($id, $start, $length, $peek)` requests `BINARY.PEEK` by
  default, avoiding the Seen side effect. Set `peek: false` to request
  `BINARY` while the mailbox is open read-write.
- `binaryBodyPartSize($id)` requests `BINARY.SIZE`. The result's existing
  `getBodyPartSize($id)` accessor returns decoded size, or null if absent.
- Sections are numeric MIME part ids such as `1` and `1.2`, or `''` for the
  empty section. Header and MIME selectors do not apply to BINARY.
- A partial requires a positive length and a nonnegative offset (default
  zero). Supplying only an offset is rejected because that is response
  syntax, not a valid partial FETCH request.
- `getBinaryBodyPart($id)` returns a stream for received decoded content,
  including a zero-length stream for an empty part. NIL or a missing part
  returns null. No additional base64 decoding is applied by the client.
- Raw BODY and decoded BINARY content for the same part remain distinct.
  `getBodyPart()` prefers raw BODY when present and falls back to decoded
  BINARY for compatibility with the earlier parser's BINARY-only behavior.

The server must advertise BINARY. Missing capability is rejected before
FETCH; `NO [UNKNOWN-CTE]` errors propagate without a silent fallback to
encoded BODY data. Binary content and decoded-size queries bypass metadata
cache reads, including when mixed with otherwise cacheable fields.

## Large objects

Use one bounded partial per FETCH when reading large parts. This feature
does not make an unrestricted FETCH memory-bounded: the current tokenizer
materializes each requested literal, and one FETCH command collects all
its responses before yielding results. Requesting an entire 512 MiB part,
or many large parts in a single command, can therefore exceed PHP memory.
Iterate partials and process/release each result before requesting the next.
Fetch decoded size once when needed; servers may have to decode the part
to calculate it, so repeating SIZE for every partial can be expensive.

References: [RFC 3516 sections 4.2–4.3](https://www.rfc-editor.org/rfc/rfc3516#section-4.2).
