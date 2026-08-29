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

use Generator;
use DateTimeImmutable;
use Horde\Imap\Client\Auth\SaslAuthenticator;
use Horde\Imap\Client\Auth\SocketChannelBindingProvider;
use Horde\Imap\Client\Exception\AuthenticationException;
use Horde\Imap\Client\Exception\ConnectionException;
use Horde\Imap\Client\Exception\Pop3ProtocolException;
use Horde\Sasl\Credentials\Credentials;
use Horde\Sasl\Credentials\PasswordCredentials;
use Horde\Socket\Client\Client as SocketClient;
use Horde\Socket\Client\ClientInterface;
use Horde\Socket\Client\ConnectionConfig as SocketConnectionConfig;
use Horde\Socket\Client\Exception\ConnectionException as SocketConnectionException;
use Horde\Socket\Client\SecureMode as SocketSecureMode;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * A modern POP3 client (RFC 1939, RFC 2449, RFC 5034, RFC 6856) built on
 * `Horde\Socket\Client` and `Horde\Sasl`.
 *
 * Consumer of {@see SaslAuthenticator}: ^
 * Mechanism negotiation, SCRAM verification,  OAuth and channel binding
 * are all inherited unchanged from `horde/Sasl`.
 * Only `USER` / `PASS` and `APOP` mechanisms outside SASL are handled directly by this class.
 * XOAUTH2, OAUTHBEARER and TLS-client-certificate auth all already route through the ordinary
 * `AUTH` SASL path.
 *
 * `fetch()` / `store()` / `expunge()` cover the genuinely protocol-native
 * POP3 surface. Full message / header / body text, UID, size, sequence
 * number, date, and delete/undelete via `DELE`/`RSET`.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class Pop3Client implements MailboxProtocol
{
    private ?ClientInterface $client;

    private ?Pop3Connection $connection = null;

    private ?SaslAuthenticator $authenticator = null;

    private ?Pop3Capability $capability = null;

    private bool $loggedIn = false;

    /** The `<...@...>` timestamp from the greeting banner, if present (APOP). */
    private ?string $apopTimestamp = null;

    /** @var list<int> Sequence numbers marked `\Deleted` this session (RFC 1939 §5). */
    private array $deletedSeqIds = [];

    public function __construct(
        private readonly ConnectionConfig $config,
        private ?Credentials $credentials = null,
        private readonly ?EventDispatcherInterface $dispatcher = null,
        ?ClientInterface $client = null,
    ) {
        $this->client = $client;
    }

    public function getCapability(): Pop3Capability
    {
        $this->connect();

        return $this->capability ??= $this->fetchCapability();
    }

    public function login(): void
    {
        if ($this->loggedIn) {
            return;
        }

        $this->connect();

        if ($this->credentials === null) {
            throw new AuthenticationException(
                'No credentials supplied: pass them to the constructor.'
            );
        }

        $this->maybeUpgradeTls();

        $saslMechanisms = $this->getCapability()->getParams('SASL');

        if ($saslMechanisms !== []) {
            try {
                $this->loginSasl($saslMechanisms);
                $this->loggedIn = true;
                // RFC 2449 §6.3: The capability list MAY change after
                // authentication (e.g. UIDL/TOP becoming visible).
                $this->capability = null;

                return;
            } catch (AuthenticationException $e) {
                if (!$this->credentials instanceof PasswordCredentials) {
                    throw $e;
                }
                // Fall through to the APOP/USER-PASS native fallback below.
            }
        }

        if (!$this->credentials instanceof PasswordCredentials) {
            throw new AuthenticationException(
                'The server offered no usable SASL mechanism, and no password'
                    . ' credential was supplied for the USER/PASS or APOP fallback.'
            );
        }

        $this->loginNative($this->credentials);
        $this->loggedIn = true;
        $this->capability = null;
    }

    public function logout(): void
    {
        if ($this->connection === null) {
            return;
        }

        try {
            $this->connection->sendLine('QUIT');
            $this->connection->expectOk();
        } catch (Pop3ProtocolException) {
            // The server is going away regardless. Nothing more to do.
        } finally {
            $this->client?->close();
            $this->connection = null;
            $this->capability = null;
            $this->loggedIn = false;
            $this->deletedSeqIds = [];
        }
    }

    public function noop(): void
    {
        $this->connect();
        $this->connection->sendLine('NOOP');
        $this->connection->expectOk();
    }

    /**
     * @return object MailboxStatus value object
     */
    public function status(string $mailbox, int $flags): object
    {
        $this->requireInbox($mailbox);

        $this->connect();

        $result = [];

        if (($flags & (StatusFlag::Messages->value | StatusFlag::Recent->value)) !== 0) {
            $stat = $this->stat();

            if (($flags & StatusFlag::Messages->value) !== 0) {
                $result['messages'] = $stat['messages'];
            }

            if (($flags & StatusFlag::Recent->value) !== 0) {
                $result['recent'] = $stat['messages'];
            }
        }

        if (($flags & StatusFlag::UidNext->value) !== 0) {
            $result['uidnext'] = $this->uidNext();
        }

        if (($flags & StatusFlag::UidValidity->value) !== 0) {
            $result['uidvalidity'] = $this->getCapability()->query('UIDL') ? 1 : microtime(true);
        }

        if (($flags & StatusFlag::Unseen->value) !== 0) {
            $result['unseen'] = 0;
        }

        return (object) $result;
    }

    public function fetch(string $mailbox, MessageIdSet $ids, object $query): Generator
    {
        if (!$query instanceof Pop3FetchQuery) {
            throw new Pop3ProtocolException('Pop3Client::fetch() requires a Pop3FetchQuery.');
        }

        $this->requireInbox($mailbox);
        $this->connect();

        $sequenceMode = $ids instanceof Pop3IdSet && $ids->isSequence();
        $uidl = (!$sequenceMode || $query->wantsUid()) ? $this->uidlBySeq() : [];
        $seqIds = $this->resolveSeqIds($ids, $sequenceMode, $uidl);

        if ($seqIds === []) {
            return;
        }

        $sizes = $query->wantsSize() ? $this->listSizes() : [];

        $needsBody = $query->wantsFullMsg() || $query->bodyTextIds() !== [];
        $needsHeader = $query->headerTextIds() !== [] || $query->wantsImapDate();

        foreach ($seqIds as $seq) {
            $uid = $uidl[$seq] ?? (string) $seq;
            $data = new Pop3MessageData($uid, $seq);

            $raw = null;
            $header = null;
            $body = null;

            if ($needsBody) {
                $raw = $this->retr($seq);
                [$header, $body] = $this->splitMessage($raw);
            } elseif ($needsHeader) {
                $header = $this->top($seq);

                if ($header === null) {
                    $raw = $this->retr($seq);
                    [$header, $body] = $this->splitMessage($raw);
                }
            }

            if ($query->wantsFullMsg()) {
                $range = $query->fullMsgRange();
                $data->setFullMsg($this->applyRange($raw ?? '', $range['start'], $range['length']));
            }

            foreach ($query->headerTextIds() as $id) {
                $data->setHeaderText($id, $header ?? '');
            }

            foreach ($query->bodyTextIds() as $id) {
                $data->setBodyText($id, $body ?? '');
            }

            if ($query->wantsSize()) {
                $data->setSize($sizes[$seq] ?? strlen($raw ?? ''));
            }

            if ($query->wantsImapDate()) {
                $data->setImapDate($this->parseDateHeader($header ?? ''));
            }

            yield ($sequenceMode ? $seq : $uid) => $data;
        }
    }

    /**
     * @param array{ids?: MessageIdSet, add?: list<SystemFlag|string>,
     *     remove?: list<SystemFlag|string>, replace?: list<SystemFlag|string>} $options
     *     POP3 only understands the `\Deleted` flag: `add`/`replace`
     *     containing it marks the given `ids` for deletion (`DELE`);
     *     `remove` containing it, or a `replace` without it, undeletes.
     *     But RFC 1939's `RSET` always restores *every* deletion mark
     *     in the session so there is no way to selectively undelete a
     *     subset. Any other flag is silently ignored (POP3 has no
     *     concept of `\Seen`/`\Flagged`/etc.).
     */
    public function store(string $mailbox, array $options): MessageIdSet
    {
        $this->requireInbox($mailbox);
        $this->connect();

        $flagValue = static fn (SystemFlag|string $flag): string => $flag instanceof SystemFlag
            ? $flag->value
            : $flag;

        $add = array_map($flagValue, $options['add'] ?? []);
        $remove = array_map($flagValue, $options['remove'] ?? []);
        $replace = array_key_exists('replace', $options)
            ? array_map($flagValue, $options['replace'])
            : null;

        $deleted = SystemFlag::Deleted->value;

        $wantsDelete = in_array($deleted, $add, true)
            || ($replace !== null && in_array($deleted, $replace, true));
        $wantsUndelete = in_array($deleted, $remove, true)
            || ($replace !== null && !in_array($deleted, $replace, true));

        if ($wantsUndelete) {
            $this->connection->expectOkFor('RSET');
            $this->deletedSeqIds = [];

            return new Pop3IdSet([], true);
        }

        if (!$wantsDelete) {
            return new Pop3IdSet([], true);
        }

        $idsToStore = $options['ids'] ?? $this->getIdsOb();
        $storeSequenceMode = $idsToStore instanceof Pop3IdSet && $idsToStore->isSequence();
        $storeUidl = (!$storeSequenceMode && !$idsToStore->isEmpty()) ? $this->uidlBySeq() : [];
        $seqIds = $this->resolveSeqIds($idsToStore, $storeSequenceMode, $storeUidl);
        $deletedNow = [];

        foreach ($seqIds as $seq) {
            try {
                $this->connection->expectOkFor('DELE ' . $seq);
                $deletedNow[] = $seq;
            } catch (Pop3ProtocolException) {
                // Server refused this one (e.g. already deleted); skip it.
            }
        }

        $this->deletedSeqIds = array_values(array_unique([...$this->deletedSeqIds, ...$deletedNow]));

        return new Pop3IdSet($deletedNow, true);
    }

    /**
     * RFC 1939 has no partial expunge: Deletions only take effect when the
     * session ends with `QUIT` (`RSET` discards them instead). This calls
     * {@see logout()} to commit them, so the connection is closed
     * afterwards.
     */
    public function expunge(string $mailbox, array $options): MessageIdSet
    {
        $this->requireInbox($mailbox);
        $this->connect();

        $expunged = new Pop3IdSet($this->deletedSeqIds, true);

        $this->logout();

        return empty($options['list']) ? new Pop3IdSet([], true) : $expunged;
    }

    public function getIdsOb(mixed $ids = null, bool $sequence = false): MessageIdSet
    {
        if ($ids === null) {
            return new Pop3IdSet([], $sequence);
        }

        if ($ids instanceof MessageIdSet) {
            return new Pop3IdSet($ids->toArray(), $sequence);
        }

        if (is_string($ids)) {
            return Pop3IdSet::fromSequenceString($ids, $sequence);
        }

        return new Pop3IdSet(is_array($ids) ? $ids : [$ids], $sequence);
    }

    private function connect(): void
    {
        if ($this->connection !== null) {
            return;
        }

        if ($this->client === null) {
            try {
                $this->client = new SocketClient(
                    new SocketConnectionConfig(
                        host: $this->config->hostspec,
                        port: $this->config->port ?? $this->defaultPort(),
                        secure: SocketSecureMode::from($this->config->secure->value),
                        connectTimeout: $this->config->timeout,
                        readTimeout: $this->config->readTimeout,
                        context: $this->config->context ?? [],
                    ),
                    $this->dispatcher,
                );
            } catch (SocketConnectionException $e) {
                throw new ConnectionException('Error connecting to mail server.', 0, $e);
            }
        }

        $connection = new Pop3Connection($this->client);
        $greeting = $connection->expectOk();

        if (preg_match('/<.+@.+>/U', $greeting->text, $matches) === 1) {
            $this->apopTimestamp = $matches[0];
        }

        $this->connection = $connection;
    }

    private function defaultPort(): int
    {
        return $this->config->secure === SecureMode::Ssl ? 995 : 110;
    }

    private function fetchCapability(): Pop3Capability
    {
        $capability = new Pop3Capability();

        try {
            $this->connection->sendLine('CAPA');
            $this->connection->expectOk();

            foreach ($this->connection->readMultiline() as $line) {
                $parts = explode(' ', $line);
                $capability->add($parts[0], array_slice($parts, 1));
            }
        } catch (Pop3ProtocolException) {
            // No CAPA support: Assume the bare minimum (RFC 1939 §4).
            $capability->add('USER');
        }

        return $capability;
    }

    private function maybeUpgradeTls(): void
    {
        if ($this->config->secure !== SecureMode::Tls || $this->client->isSecure()) {
            return;
        }

        if (!$this->getCapability()->query('STLS')) {
            throw new ConnectionException(
                'Could not open a secure connection: the server does not advertise STLS.'
            );
        }

        $this->connection->sendLine('STLS');
        $this->connection->expectOk();

        if (!$this->client->startTls()) {
            throw new ConnectionException('Could not open secure connection to the POP3 server.');
        }

        // Capabilities may legitimately change after the TLS upgrade
        // (RFC 2595). Discard the pre-STLS snapshot.
        $this->capability = null;
    }

    /**
     * @param list<string> $saslMechanisms
     */
    private function loginSasl(array $saslMechanisms): void
    {
        $channel = new Pop3AuthChannel($this->connection);

        $this->authenticator ??= new SaslAuthenticator(
            $this->config,
            $this->credentials,
            new SocketChannelBindingProvider($this->client),
            $this->dispatcher,
        );

        $this->authenticator->authenticate(
            $channel,
            $saslMechanisms,
            $this->client->isSecure(),
            $this->credentials,
        );
    }

    private function loginNative(PasswordCredentials $credentials): void
    {
        $password = $credentials->password()->reveal();

        if ($password === '') {
            throw new AuthenticationException('No password provided.');
        }

        if ($this->apopTimestamp !== null) {
            try {
                $digest = hash('md5', $this->apopTimestamp . $password);
                $this->connection->sendLine('APOP ' . $credentials->authcid() . ' ' . $digest);
                $this->connection->expectOk();

                return;
            } catch (Pop3ProtocolException) {
                // Fall through to USER/PASS.
            }
        }

        $this->connection->sendLine('USER ' . $credentials->authcid());
        $this->connection->expectOk();
        $this->connection->sendLine('PASS ' . $password);

        try {
            $this->connection->expectOk();
        } catch (Pop3ProtocolException $e) {
            throw new AuthenticationException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array{messages: int, size: int}
     */
    private function stat(): array
    {
        $status = $this->connection->expectOkFor('STAT');
        [$messages, $size] = explode(' ', $status->text, 2) + ['0', '0'];

        return ['messages' => (int) $messages, 'size' => (int) $size];
    }

    private function uidNext(): int|string
    {
        if (!$this->getCapability()->query('UIDL')) {
            return $this->stat()['messages'] + 1;
        }

        $ctx = hash_init('md5');

        foreach ($this->uidlBySeq() as $seq => $uid) {
            hash_update($ctx, '|' . $seq . '|' . $uid);
        }

        return hash_final($ctx);
    }

    private function requireInbox(string $mailbox): void
    {
        if (strcasecmp($mailbox, 'INBOX') !== 0) {
            throw new Pop3ProtocolException('POP3 only supports the INBOX mailbox.');
        }
    }

    /**
     * Resolve a `MessageIdSet` into POP3 sequence numbers.
     *
     * An empty set means "every message" (matching legacy `_getSeqIds()`).
     * A `Pop3IdSet` built in sequence mode is used as-is. Anything else is
     * treated as a set of UIDLs and mapped back to sequence numbers via
     * the caller-supplied `$uidl` map (silently dropping any UID the
     * server no longer has. It was presumably expunged by a concurrent
     * session). Callers must supply `$uidl` whenever `$sequenceMode` is
     * false and `$ids` is non-empty.
     *
     * @param array<int, string> $uidl
     * @return list<int>
     */
    private function resolveSeqIds(MessageIdSet $ids, bool $sequenceMode, array $uidl = []): array
    {
        if ($ids->isEmpty()) {
            return range(1, $this->stat()['messages']);
        }

        if ($sequenceMode) {
            return array_map(intval(...), $ids->toArray());
        }

        $seqByUid = array_flip($uidl);

        $result = [];

        foreach ($ids->toArray() as $uid) {
            if (isset($seqByUid[$uid])) {
                $result[] = $seqByUid[$uid];
            }
        }

        return $result;
    }

    /**
     * @return array<int, string> Sequence number => UIDL string.
     */
    private function uidlBySeq(): array
    {
        $this->connection->expectOkFor('UIDL');

        $map = [];

        foreach ($this->connection->readMultiline() as $line) {
            $parts = explode(' ', $line, 2);
            $map[(int) $parts[0]] = $parts[1] ?? '';
        }

        return $map;
    }

    /**
     * @return array<int, int> Sequence number => octet size.
     */
    private function listSizes(): array
    {
        $this->connection->expectOkFor('LIST');

        $map = [];

        foreach ($this->connection->readMultiline() as $line) {
            $parts = explode(' ', $line, 2);
            $map[(int) $parts[0]] = (int) ($parts[1] ?? 0);
        }

        return $map;
    }

    /**
     * Fetch the full raw message via `RETR`.
     */
    private function retr(int $seq): string
    {
        $this->connection->expectOkFor('RETR ' . $seq);

        return implode("\r\n", iterator_to_array($this->connection->readMultiline()));
    }

    /**
     * Fetch just the header via `TOP <seq> 0`, or null if the server
     * doesn't support `TOP` (RFC 1939 §7) or refuses this particular
     * message. The caller falls back to a full `RETR` in that case.
     */
    private function top(int $seq): ?string
    {
        if (!$this->getCapability()->query('TOP')) {
            return null;
        }

        try {
            $this->connection->expectOkFor('TOP ' . $seq . ' 0');

            return implode("\r\n", iterator_to_array($this->connection->readMultiline()));
        } catch (Pop3ProtocolException) {
            return null;
        }
    }

    /**
     * Split a raw RFC 822 message into header/body at the first blank
     * line. There is no MIME-part addressing here (see
     * {@see Pop3FetchQuery}). This is always the whole message's header
     * and the whole message's body.
     *
     * @return array{0: string, 1: string}
     */
    private function splitMessage(string $raw): array
    {
        $pos = strpos($raw, "\r\n\r\n");

        return $pos === false ? [$raw, ''] : [substr($raw, 0, $pos), substr($raw, $pos + 4)];
    }

    private function applyRange(string $data, ?int $start, ?int $length): string
    {
        if ($length !== null) {
            return substr($data, $start ?? 0, $length);
        }

        return $start !== null ? substr($data, $start) : $data;
    }

    /**
     * Parse the message's `Date:` header. Deliberately simple (no folded
     * header support). Good enough for the common case. Returns null if
     * absent or unparseable, and {@see Pop3MessageData::getImapDate()}
     * falls back to the epoch.
     */
    private function parseDateHeader(string $header): ?DateTimeImmutable
    {
        foreach (explode("\r\n", $header) as $line) {
            if (stripos($line, 'Date:') === 0) {
                try {
                    return new DateTimeImmutable(trim(substr($line, 5)));
                } catch (\Exception) {
                    return null;
                }
            }
        }

        return null;
    }
}

