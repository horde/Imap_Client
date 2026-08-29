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

use DateTimeImmutable;
use DateTimeInterface;
use Generator;
use Horde\Imap\Client\Auth\ImapAuthChannel;
use Horde\Imap\Client\Auth\SaslAuthenticator;
use Horde\Imap\Client\Auth\SocketChannelBindingProvider;
use Horde\Imap\Client\Event\MailboxExpunged;
use Horde\Imap\Client\Event\MailboxSelected;
use Horde\Imap\Client\Exception\AuthenticationException;
use Horde\Imap\Client\Exception\CapabilityNotSupportedException;
use Horde\Imap\Client\Exception\ConnectionException;
use Horde\Imap\Client\Exception\ImapProtocolException;
use Horde\Imap\Client\Exception\MailboxNotFoundException;
use Horde\Imap\Client\Exception\ServerResponseException;
use Horde\Imap\Client\Exception\SyncException;
use Horde\Sasl\Credentials\Credentials;
use Horde\Sasl\Credentials\PasswordCredentials;
use Horde\Mime\Part;
use Horde\Socket\Client\Client as SocketClient;
use Horde\Socket\Client\ClientInterface;
use Horde\Socket\Client\ConnectionConfig as SocketConnectionConfig;
use Horde\Socket\Client\Exception\ConnectionException as SocketConnectionException;
use Horde\Socket\Client\SecureMode as SocketSecureMode;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * A modern IMAP client (RFC 3501/RFC 9051) built on `Horde\Socket\Client` and `Horde\Sasl`
 *
 * Reuses {@see Auth\ImapAuthChannel} for SASL `AUTHENTICATE`
 * and {@see ImapCapabilityNegotiator} for `CAPABILITY`/ `ENABLE`.
 * `LOGIN` is the one native fallback this class handles directly for
 * servers that only advertise SASL mechanisms this library's policy
 * rejects or none at all.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
final class ImapClient implements ImapProtocol, ImapAclAware, ImapQuotaAware, ImapMetadataAware
{
    private ?ClientInterface $client;

    private ?ImapConnection $connection = null;

    private ?ImapCommandTag $tags = null;

    private ?ImapInteraction $interaction = null;

    private ?ImapCapabilityNegotiator $negotiator = null;

    private ?ImapCapability $capability = null;

    private bool $capabilityFetched = false;

    private ?SaslAuthenticator $authenticator = null;

    private bool $loggedIn = false;

    private ?string $selectedMailbox = null;

    private bool $selectedReadWrite = false;

    /**
     * UIDVALIDITY of the selected mailbox (RFC 3501 §2.3.1.1), captured
     * from its SELECT/EXAMINE reply. 0 when unknown/none selected. Used
     * as the cache-keying dimension.
     */
    private int $selectedUidValidity = 0;

    /**
     * HIGHESTMODSEQ of the selected mailbox (RFC 7162), captured from its
     * SELECT/EXAMINE reply. 0 when the server lacks CONDSTORE. Used to
     * gate the freshness of cached flags.
     */
    private int $selectedHighestModSeq = 0;

    public function __construct(
        private readonly ConnectionConfig $config,
        private ?Credentials $credentials = null,
        private readonly ?EventDispatcherInterface $dispatcher = null,
        ?ClientInterface $client = null,
        private readonly ?ImapCacheStore $cache = null,
    ) {
        $this->client = $client;
    }

    /**
     * Dispatch a domain event to the configured PSR-14 dispatcher, if any.
     *
     * A no-op when no dispatcher was injected, so mutation paths can signal
     * external listeners (for example a search-result cache the library
     * does not own) without every call site null-checking.
     */
    private function dispatch(object $event): void
    {
        $this->dispatcher?->dispatch($event);
    }

    public function getCapability(): ImapCapability
    {
        $this->connect();

        if (!$this->capabilityFetched) {
            $this->capability = $this->negotiator->fetch();
            $this->capabilityFetched = true;
        }

        return $this->capability;
    }

    /**
     * The currently selected/examined mailbox or null if none is
     * (RFC 3501 §3.2, "selected state").
     */
    public function selectedMailbox(): ?string
    {
        return $this->selectedMailbox;
    }

    /**
     * Whether the selected mailbox was opened read-write. Meaningless
     * (returns false) when no mailbox is currently selected.
     */
    public function isReadWrite(): bool
    {
        return $this->selectedReadWrite;
    }

    public function login(): void
    {
        if ($this->loggedIn) {
            return;
        }

        $this->connect();

        if ($this->loggedIn) {
            // A PREAUTH greeting (RFC 3501 §7.1.4) already authenticated
            // this connection out-of-band (e.g. by TLS client certificate,
            // or IP allowlisting). There is nothing left for login() to do.
            return;
        }

        if ($this->credentials === null) {
            throw new AuthenticationException(
                'No credentials supplied: pass them to the constructor.'
            );
        }

        $this->maybeUpgradeTls();

        $capability = $this->getCapability();
        $authMechanisms = $capability->getParams('AUTH');

        if ($authMechanisms !== []) {
            try {
                $this->loginSasl($authMechanisms);
                $this->finishLogin();

                return;
            } catch (AuthenticationException $e) {
                if (!$this->credentials instanceof PasswordCredentials) {
                    throw $e;
                }
                // Fall through to the native LOGIN fallback below.
            }
        }

        if (!$this->credentials instanceof PasswordCredentials) {
            throw new AuthenticationException(
                'The server offered no usable SASL mechanism, and no password'
                    . ' credential was supplied for the LOGIN fallback.'
            );
        }

        if ($capability->query('LOGINDISABLED')) {
            throw new AuthenticationException(
                'The server has disabled the LOGIN command (LOGINDISABLED) and'
                    . ' offered no usable SASL mechanism.'
            );
        }

        $this->loginNative($this->credentials);
        $this->finishLogin();
    }

    public function logout(): void
    {
        if ($this->connection === null) {
            return;
        }

        try {
            $this->interaction->send('LOGOUT');
        } catch (ImapProtocolException | ServerResponseException) {
            // The server is going away regardless; nothing more to do.
        } finally {
            $this->cache?->flush();
            $this->client?->close();
            $this->connection = null;
            $this->tags = null;
            $this->interaction = null;
            $this->negotiator = null;
            $this->capability = null;
            $this->capabilityFetched = false;
            $this->loggedIn = false;
            $this->selectedMailbox = null;
            $this->selectedReadWrite = false;
            $this->selectedUidValidity = 0;
            $this->selectedHighestModSeq = 0;
        }
    }

    public function noop(): void
    {
        $this->connect();
        $this->interaction->send('NOOP');
    }

    /**
     * Send `SELECT` (read-write) or `EXAMINE` (read-only) (RFC 3501
     * §6.3.1-6.3.2). `OpenMode::Auto` is treated the same as
     * `ReadWrite`. Always asks for the stronger mode and lets the server downgrade via its tagged
     * `[READ-ONLY]` response code if it must.
     *
     * @throws MailboxNotFoundException If the server rejects the command
     *                                     almost always caused by the
     *                                     mailbox not existing or being
     *                                     not selectable.
     */
    public function openMailbox(string $mailbox, OpenMode $mode): void
    {
        $this->connect();
        $this->sendSelect($mailbox, $mode, null);
    }

    /**
     * Open a mailbox with a QRESYNC parameter (RFC 7162 §3.2.5),
     * fast-forwarding the client's view from a known point.
     *
     * Passes the last-seen `$uidValidity` and `$modseq` (and optionally
     * the UIDs the client already knows) so the server replies with the
     * messages expunged since (`VANISHED (EARLIER)`) and a flag-change
     * FETCH for each changed message, all bundled into the returned
     * {@see ImapQresyncResult}. Requires QRESYNC to be enabled first (via
     * {@see enableQresync()}).
     *
     * @param ?ImapIdSet $knownUids The UIDs the client already has (the
     *                              QRESYNC "known-uids" set), or null to
     *                              omit it.
     *
     * @throws CapabilityNotSupportedException If QRESYNC is not enabled.
     * @throws MailboxNotFoundException        If the server rejects the open.
     */
    public function openMailboxQresync(
        string $mailbox,
        OpenMode $mode,
        int $uidValidity,
        int $modseq,
        ?ImapIdSet $knownUids = null,
    ): ImapQresyncResult {
        $this->connect();

        if (!$this->getCapability()->isEnabled('QRESYNC')) {
            throw new CapabilityNotSupportedException(
                'A QRESYNC SELECT requires QRESYNC to be enabled first (RFC 7162); call enableQresync().'
            );
        }

        $params = new ImapWireList([
            new ImapWireNumber($uidValidity),
            new ImapWireNumber($modseq),
        ]);

        if ($knownUids !== null && !$knownUids->isEmpty()) {
            $params->add(new ImapWireAtom((string) $knownUids));
        }

        $qresync = new ImapWireList([new ImapWireAtom('QRESYNC'), $params]);
        $result = $this->sendSelect($mailbox, $mode, $qresync);

        return new ImapQresyncResult(
            ImapVanishedParser::parse($result->untagged),
            $this->collectQresyncChanges($result->untagged),
        );
    }

    /**
     * Send the actual SELECT/EXAMINE, optionally with a trailing
     * parameter list (used for QRESYNC), and update the selected-mailbox
     * state. Shared by {@see openMailbox()} and
     * {@see openMailboxQresync()}.
     *
     * @throws MailboxNotFoundException If the server rejects the command.
     */
    private function sendSelect(string $mailbox, OpenMode $mode, ?ImapWireList $extra): ImapCommandResult
    {
        $command = $mode === OpenMode::Readonly ? 'EXAMINE' : 'SELECT';
        $arguments = [new ImapWireMailbox($mailbox, $this->mailboxNameCodec())];

        if ($extra !== null) {
            $arguments[] = $extra;
        }

        try {
            $result = $this->interaction->send($command, $arguments);
        } catch (ServerResponseException $e) {
            throw new MailboxNotFoundException(
                "Could not open mailbox '{$mailbox}': {$e->getMessage()}",
                0,
                $e,
            );
        }

        $this->selectedMailbox = $mailbox;
        $this->selectedReadWrite = $command === 'SELECT'
            && !(
                $result->tagged->responseCode !== null
                && strtoupper($result->tagged->responseCode->name) === 'READ-ONLY'
            );

        // Capture UIDVALIDITY / HIGHESTMODSEQ from the untagged
        // `* OK [UIDVALIDITY n]` / `* OK [HIGHESTMODSEQ n]` codes the open
        // reply carries (RFC 3501 §7.1, RFC 7162 §3.1.2.1); the cache
        // layer keys on the former and gates flag freshness on the latter.
        $this->selectedUidValidity = 0;
        $this->selectedHighestModSeq = 0;
        $uidnext = 0;

        foreach ($result->untagged as $response) {
            $code = $response->responseCode;

            if ($code === null || $code->data === [] || !is_string($code->data[0]) || !ctype_digit($code->data[0])) {
                continue;
            }

            match (strtoupper($code->name)) {
                'UIDVALIDITY' => $this->selectedUidValidity = (int) $code->data[0],
                'HIGHESTMODSEQ' => $this->selectedHighestModSeq = (int) $code->data[0],
                'UIDNEXT' => $uidnext = (int) $code->data[0],
                default => null,
            };
        }

        $this->dispatch(new MailboxSelected(
            $mailbox,
            $this->selectedUidValidity,
            $uidnext,
            $this->selectedHighestModSeq,
        ));

        return $result;
    }

    /**
     * @return object MailboxStatus value object.
     */
    public function status(string $mailbox, int $flags): object
    {
        $this->connect();

        $items = $this->statusItems($flags);
        $mailboxArg = new ImapWireMailbox($mailbox, $this->mailboxNameCodec());

        try {
            $result = $this->interaction->send('STATUS', [$mailboxArg, new ImapWireList($items)]);
        } catch (ServerResponseException $e) {
            throw new MailboxNotFoundException(
                "Could not read status of mailbox '{$mailbox}': {$e->getMessage()}",
                0,
                $e,
            );
        }

        return (object) $this->parseStatusResponse($result->untagged);
    }

    /**
     * Read the status of several mailboxes in one pipelined burst
     * (RFC 3501 §5.5), returning one status object per mailbox keyed by
     * name. This is the batch form of {@see status()}; issuing the STATUS
     * commands together saves a round trip each versus calling `status()`
     * in a loop.
     *
     * A mailbox the server rejects is simply absent from the result
     * (unlike `status()`, a single bad mailbox does not abort the batch).
     *
     * @param list<string> $mailboxes
     *
     * @return array<string, object> MailboxStatus value objects by mailbox.
     */
    public function statusMultiple(array $mailboxes, int $flags): array
    {
        $this->connect();

        if ($mailboxes === []) {
            return [];
        }

        $items = new ImapWireList($this->statusItems($flags));
        $commands = [];
        $tagToMailbox = [];

        foreach ($mailboxes as $mailbox) {
            $command = new ImapCommand(
                $this->interaction->newTag(),
                'STATUS',
                [new ImapWireMailbox($mailbox, $this->mailboxNameCodec()), $items],
            );
            $commands[] = $command;
            $tagToMailbox[$command->tag] = $mailbox;
        }

        $results = $this->interaction->sendPipeline($commands);
        $out = [];

        foreach ($results as $tag => $result) {
            // Skip a mailbox the server rejected (NO/BAD tagged response).
            if (!$result->tagged->isOk()) {
                continue;
            }

            $out[$tagToMailbox[$tag]] = (object) $this->parseStatusResponse($result->untagged);
        }

        return $out;
    }

    public function close(array $options = []): void
    {
        $this->connect();
        $this->interaction->send('CLOSE');
        $this->selectedMailbox = null;
        $this->selectedReadWrite = false;
        $this->selectedUidValidity = 0;
        $this->selectedHighestModSeq = 0;
    }

    /**
     * @throws CapabilityNotSupportedException If the server is not IMAP4rev2 and does not
     *                                            advertise `UNSELECT` (RFC 3691).
     */
    public function unselect(): void
    {
        $this->connect();
        $capability = $this->getCapability();

        if (!$capability->isEnabled('IMAP4REV2') && !$capability->query('UNSELECT')) {
            throw new CapabilityNotSupportedException(
                'The server does not advertise UNSELECT (RFC 3691). Only IMAP4rev2 servers support it unconditionally.'
            );
        }

        $this->interaction->send('UNSELECT');
        $this->selectedMailbox = null;
        $this->selectedReadWrite = false;
        $this->selectedUidValidity = 0;
        $this->selectedHighestModSeq = 0;
    }

    /**
     * Create a mailbox (RFC 3501 §6.3.3).
     *
     * When `$specialUse` is given, the server is asked to attach those
     * RFC 6154 special-use attributes to the new mailbox with the
     * `CREATE ... (USE (...))` form (RFC 6154 §3). A server without
     * `CREATE-SPECIAL-USE` may reject the attributes; this method does
     * not pre-check the capability, leaving the server to accept or
     * refuse.
     *
     * @param list<SpecialUse> $specialUse Special-use attributes to request.
     *
     * @throws ServerResponseException If the server rejects CREATE.
     */
    public function createMailbox(string $mailbox, array $specialUse = []): void
    {
        $this->connect();

        $arguments = [new ImapWireMailbox($mailbox, $this->mailboxNameCodec())];

        if ($specialUse !== []) {
            $uses = new ImapWireList();

            foreach ($specialUse as $use) {
                $uses->add(new ImapWireAtom($use->value));
            }

            // RFC 6154 §3: CREATE mailbox (USE (\Attr ...)).
            $arguments[] = new ImapWireList([new ImapWireAtom('USE'), $uses]);
        }

        // CREATE returns no untagged data (RFC 3501 §6.3.3).
        $this->interaction->send('CREATE', $arguments);
    }

    /**
     * Delete a mailbox (RFC 3501 §6.3.4).
     *
     * A mailbox that is currently selected is closed first, since some
     * servers refuse to delete an open mailbox. Some servers also refuse
     * to delete a mailbox that still holds messages; on a rejection the
     * mailbox is emptied (every message flagged `\Deleted` and expunged)
     * and the DELETE retried once.
     *
     * @throws ServerResponseException If the server rejects DELETE even
     *                                    after the mailbox was emptied.
     */
    public function deleteMailbox(string $mailbox): void
    {
        $this->connect();

        if ($this->selectedMailbox === $mailbox) {
            $this->close();
        }

        $arg = [new ImapWireMailbox($mailbox, $this->mailboxNameCodec())];

        try {
            // DELETE returns no untagged data (RFC 3501 §6.3.4).
            $this->interaction->send('DELETE', $arg);
        } catch (ServerResponseException $e) {
            // Some servers won't delete a non-empty mailbox. Empty it and
            // retry once (the legacy driver's same fallback).
            $this->emptyMailbox($mailbox);
            $this->interaction->send('DELETE', $arg);
        }

        $this->cache?->deleteMailbox($mailbox);
    }

    /**
     * Rename a mailbox (RFC 3501 §6.3.5).
     *
     * The source mailbox is closed first when it is the selected one,
     * since some servers refuse to rename an open mailbox.
     *
     * @throws ServerResponseException If the server rejects RENAME.
     */
    public function renameMailbox(string $old, string $new): void
    {
        $this->connect();

        if ($this->selectedMailbox === $old) {
            $this->close();
        }

        $codec = $this->mailboxNameCodec();

        // RENAME returns no untagged data (RFC 3501 §6.3.5).
        $this->interaction->send('RENAME', [
            new ImapWireMailbox($old, $codec),
            new ImapWireMailbox($new, $codec),
        ]);
    }

    /**
     * Subscribe to or unsubscribe from a mailbox (RFC 3501 §6.3.6-6.3.7).
     *
     * @throws ServerResponseException If the server rejects the command.
     */
    public function subscribeMailbox(string $mailbox, bool $subscribe = true): void
    {
        $this->connect();

        // SUBSCRIBE/UNSUBSCRIBE return no untagged data (RFC 3501
        // §6.3.6-6.3.7).
        $this->interaction->send(
            $subscribe ? 'SUBSCRIBE' : 'UNSUBSCRIBE',
            [new ImapWireMailbox($mailbox, $this->mailboxNameCodec())],
        );
    }

    /**
     * List mailboxes matching a pattern (RFC 3501 §6.3.8, RFC 5258).
     *
     * Uses the `LIST-EXTENDED` (RFC 5258) form when the server advertises
     * it, with `LIST (SUBSCRIBED)`/`RETURN (SUBSCRIBED)` selecting or
     * annotating subscription state. Otherwise it falls back to the base
     * RFC 3501 commands: `LSUB` for the subscribed-only modes, `LIST`
     * otherwise. `LIST (SUBSCRIBED)` is additive on top of ordinary
     * `LIST` parsing, not a separate response format.
     *
     * The result mirrors the legacy shape so existing consumers keep
     * working. With the `flat` option it is a `list<string>` of matching
     * mailbox names (UTF-8). Otherwise it is an
     * `array<string, array{mailbox: string, delimiter: ?string, attributes?: list<string>, status?: array<string, int|string>}>`
     * keyed by mailbox name, with `attributes` present when the caller
     * asked for them (the `attributes` option) or the server volunteered
     * any, and `status` present when the `status` option was requested and
     * the server supports `LIST-STATUS` (RFC 5819).
     *
     * `LIST-EXTENDED` return/select options are driven by the options:
     * `children` (`RETURN (CHILDREN)`), `special_use`
     * (`RETURN (SPECIAL-USE)`), `remote` (select `REMOTE`),
     * `recursivematch` (select `RECURSIVEMATCH`), and `status` (a
     * {@see StatusFlag} bitmask requesting `RETURN (STATUS (...))`). All
     * are ignored gracefully when the server lacks the relevant capability.
     *
     * @param array{
     *     flat?: bool,
     *     attributes?: bool,
     *     children?: bool,
     *     special_use?: bool,
     *     remote?: bool,
     *     recursivematch?: bool,
     *     status?: int
     * } $options
     *
     * @return array<int|string, mixed>
     *
     * @throws ServerResponseException If the server rejects the command.
     */
    public function listMailboxes(string $pattern, MailboxListMode $mode, array $options = []): array
    {
        $this->connect();

        $flat = !empty($options['flat']);
        $wantAttributes = $flat ? false : !empty($options['attributes']);
        $useExtended = $this->getCapability()->query('LIST-EXTENDED');

        $command = $useExtended
            ? $this->buildExtendedListCommand($pattern, $mode, $options)
            : $this->buildBaseListCommand($pattern, $mode);

        $result = $this->interaction->send($command['name'], $command['arguments']);

        $list = $this->parseListResponses($result->untagged, $mode, $flat, $wantAttributes, $useExtended);

        // LIST-STATUS (RFC 5819) interleaves * STATUS lines; fold them
        // into each entry when the caller asked for status.
        if (!$flat && !empty($options['status']) && $this->getCapability()->query('LIST-STATUS')) {
            $this->attachListStatus($list, $result->untagged);
        }

        return $list;
    }

    /**
     * List mailboxes matching several patterns in one pipelined burst
     * (RFC 3501 §5.5), the batch form of {@see listMailboxes()}. Each
     * pattern's `LIST` command is issued together and the matches are
     * merged into one result keyed by mailbox name.
     *
     * Only used when the server supports `LIST-EXTENDED` (so each LIST
     * carries no continuation and can be pipelined) and more than one
     * pattern is given; otherwise it falls back to issuing
     * {@see listMailboxes()} per pattern and merging.
     *
     * @param list<string> $patterns
     * @param array{
     *     flat?: bool, attributes?: bool, children?: bool,
     *     special_use?: bool, remote?: bool, recursivematch?: bool, status?: int
     * } $options
     *
     * @return array<int|string, mixed>
     */
    public function listMailboxesMulti(array $patterns, MailboxListMode $mode, array $options = []): array
    {
        $this->connect();

        if (count($patterns) <= 1) {
            return $patterns === [] ? [] : $this->listMailboxes($patterns[0], $mode, $options);
        }

        $flat = !empty($options['flat']);

        if (!$this->getCapability()->query('LIST-EXTENDED')) {
            // Base LIST/LSUB carries no RETURN options; merge per-pattern
            // results (still correct, just one round trip per pattern).
            $merged = [];

            foreach ($patterns as $pattern) {
                foreach ($this->listMailboxes($pattern, $mode, $options) as $key => $entry) {
                    if ($flat) {
                        $merged[] = $entry;
                    } else {
                        $merged[$key] = $entry;
                    }
                }
            }

            return $merged;
        }

        $wantAttributes = $flat ? false : !empty($options['attributes']);
        $commands = [];

        foreach ($patterns as $pattern) {
            $built = $this->buildExtendedListCommand($pattern, $mode, $options);
            $commands[] = new ImapCommand($this->interaction->newTag(), $built['name'], $built['arguments']);
        }

        $results = $this->interaction->sendPipeline($commands);

        // Merge every command's untagged responses, then parse once.
        $untagged = [];
        foreach ($results as $result) {
            array_push($untagged, ...$result->untagged);
        }

        $list = $this->parseListResponses($untagged, $mode, $flat, $wantAttributes, true);

        if (!$flat && !empty($options['status']) && $this->getCapability()->query('LIST-STATUS')) {
            $this->attachListStatus($list, $untagged);
        }

        return $list;
    }

    /**
     * Query the server's namespaces (RFC 2342).
     *
     * Returns an {@see ImapNamespaceList}. When the server does not
     * advertise the `NAMESPACE` capability the list is empty, matching
     * the legacy behaviour of returning an empty namespace list rather
     * than raising.
     */
    public function getNamespaces(): ImapNamespaceList
    {
        $this->connect();

        if (!$this->getCapability()->query('NAMESPACE')) {
            return new ImapNamespaceList();
        }

        $result = $this->interaction->send('NAMESPACE');

        foreach ($result->untagged as $response) {
            if (
                $response->isUntagged()
                && $response->data !== []
                && is_string($response->data[0])
                && strtoupper($response->data[0]) === 'NAMESPACE'
            ) {
                return $this->parseNamespaceResponse($response->data);
            }
        }

        return new ImapNamespaceList();
    }

    /**
     * Search the mailbox (RFC 3501 §6.4.4).
     *
     * Uses the ESEARCH form (`SEARCH RETURN (...)`, RFC 4731) when the
     * server advertises `ESEARCH`, requesting the return items that
     * cover the caller's `results`; otherwise it sends a classic
     * `SEARCH` and {@see ImapSearchParser} derives count/min/max from the
     * matching set. The `$results` option is a list of
     * {@see SearchResultType} cases (defaulting to just the match set).
     *
     * The caller must have opened `$mailbox` first (via
     * {@see openMailbox()}); `search()` does not implicitly `SELECT`. The
     * `$mailbox` argument is accepted for interface parity and to make the
     * intent explicit at the call site.
     *
     * When the `sort` option (a list of {@see SortCriteria}) is given, a
     * server-side `SORT`/`UID SORT` (RFC 5256) is sent instead, using
     * ESORT (RFC 5267) when advertised; the result's `match` set
     * preserves the server's ordering. Client-side sorting is not
     * implemented, so a server without `SORT` raises rather than falling
     * back.
     *
     * @param ImapSearchQuery|object $query An {@see ImapSearchQuery}.
     * @param array{
     *     sequence?: bool,
     *     results?: list<SearchResultType>,
     *     sort?: list<SortCriteria>
     * } $options
     *
     * @throws ImapProtocolException           If `$query` is not an {@see ImapSearchQuery}.
     * @throws CapabilityNotSupportedException  If `sort` is requested but the server lacks SORT.
     * @throws ServerResponseException          If the server rejects the SEARCH/SORT.
     */
    public function search(string $mailbox, object $query, array $options = []): ImapSearchResult
    {
        $this->connect();

        if (!$query instanceof ImapSearchQuery) {
            throw new ImapProtocolException('search() requires an ImapSearchQuery.');
        }

        $sequence = !empty($options['sequence']);
        $results = $options['results'] ?? [SearchResultType::Match];
        $built = $query->build();

        if (!empty($options['sort'])) {
            return $this->sortSearch($query, $built, $options['sort'], $results, $sequence);
        }

        $command = $sequence ? 'SEARCH' : 'UID SEARCH';

        try {
            $result = $this->interaction->send($command, $this->searchArguments($built, $results, $sequence));
        } catch (ServerResponseException $e) {
            // RFC 3501 §6.4.4: a rejected non-ASCII CHARSET yields
            // NO [BADCHARSET (charset ...)]. Re-encode the search text
            // into a charset the server accepts and retry. A UTF-8 client
            // never carries a CHARSET, so it cannot reach this.
            $result = $this->retryBadCharset($e, $query, $built, $results, $sequence, $command);
        }

        return ImapSearchParser::parse($result->untagged, $sequence);
    }

    /**
     * Retry a search after a `NO [BADCHARSET (...)]`, re-encoding its text
     * into the first server-offered charset that can represent it without
     * loss (a lossy charset such as US-ASCII would silently corrupt the
     * search terms, so it is skipped). Re-throws the original error when
     * the failure was not a BADCHARSET or no usable charset was offered.
     *
     * @param array{charset: ?string, criteria: list<ImapWireEncodable>} $built
     * @param list<SearchResultType> $results
     *
     * @throws ServerResponseException
     */
    private function retryBadCharset(
        ServerResponseException $e,
        ImapSearchQuery $query,
        array $built,
        array $results,
        bool $sequence,
        string $command,
    ): ImapCommandResult {
        $code = $e->responseCode;

        if ($built['charset'] === null || $code === null || strtoupper($code->name) !== 'BADCHARSET') {
            throw $e;
        }

        foreach ($this->badCharsetOffered($code) as $charset) {
            if (strtoupper($charset) === strtoupper($built['charset']) || !$query->canEncodeIn($charset)) {
                continue;
            }

            $retryBuilt = $query->build($charset);

            return $this->interaction->send($command, $this->searchArguments($retryBuilt, $results, $sequence));
        }

        throw $e;
    }

    /**
     * The charsets a `[BADCHARSET (...)]` response code offered, in order.
     * The list is normally parenthesized; bare trailing tokens are also
     * accepted defensively.
     *
     * @return list<string>
     */
    private function badCharsetOffered(ImapResponseCode $code): array
    {
        $offered = [];

        foreach ($code->data as $token) {
            if (is_array($token)) {
                foreach ($token as $charset) {
                    if (is_string($charset)) {
                        $offered[] = $charset;
                    }
                }
            } elseif (is_string($token)) {
                $offered[] = $token;
            }
        }

        return $offered;
    }

    /**
     * Assemble the argument list for a `[UID] SEARCH` command from a built
     * query: optional ESEARCH `RETURN (...)`, an optional `CHARSET`, and
     * the criteria (or `ALL`).
     *
     * @param array{charset: ?string, criteria: list<ImapWireEncodable>} $built
     * @param list<SearchResultType> $results
     *
     * @return list<ImapWireEncodable>
     */
    private function searchArguments(array $built, array $results, bool $sequence): array
    {
        $arguments = [];

        if ($this->getCapability()->query('ESEARCH')) {
            $arguments[] = new ImapWireAtom('RETURN');
            $arguments[] = new ImapWireList($this->searchReturnOptions($results));
        }

        // RFC 3501 §6.4.4: CHARSET is optional and only carried when the
        // query holds text. RFC 6855 §3: once the connection is in UTF-8
        // mode (UTF8=ACCEPT, or IMAP4rev2 which implies it) the client
        // MUST NOT send a CHARSET, since UTF-8 is implied.
        if ($built['charset'] !== null && !$this->utf8Enabled()) {
            $arguments[] = new ImapWireAtom('CHARSET');
            $arguments[] = new ImapWireAtom($built['charset']);
        }

        if ($built['criteria'] === []) {
            $arguments[] = new ImapWireAtom('ALL');
        } else {
            array_push($arguments, ...$built['criteria']);
        }

        return $arguments;
    }

    /**
     * Update message flags (RFC 3501 §6.4.6).
     *
     * Pass `add`/`remove` flag lists (`+FLAGS`/`-FLAGS`) or a `replace`
     * list (`FLAGS`); flags are {@see SystemFlag} cases or plain keyword
     * strings. `.SILENT` is used by default so the server does not echo a
     * FLAGS FETCH per message; pass `silent: false` in `$options` to see
     * them. `unchangedsince` gates the store on CONDSTORE (RFC 7162):
     * messages whose MODSEQ changed meanwhile are skipped and returned in
     * the result set (from the tagged `[MODIFIED ...]` code).
     *
     * @param array{
     *     ids?: MessageIdSet,
     *     add?: list<SystemFlag|string>,
     *     remove?: list<SystemFlag|string>,
     *     replace?: list<SystemFlag|string>,
     *     sequence?: bool,
     *     silent?: bool,
     *     unchangedsince?: int
     * } $options
     *
     * @return MessageIdSet The messages NOT updated because their MODSEQ
     *                      changed (CONDSTORE); empty on an ordinary store.
     *
     * @throws ServerResponseException If the server rejects the STORE.
     */
    public function store(string $mailbox, array $options): MessageIdSet
    {
        $this->connect();

        $ids = $options['ids'] ?? null;
        // The sequence/UID mode follows the id set when one is given
        // (mirroring fetch()), falling back to the explicit option.
        $sequence = $ids instanceof ImapIdSet ? $ids->isSequence() : !empty($options['sequence']);
        $ids ??= new ImapIdSet([], $sequence);
        $silent = $options['silent'] ?? true;

        $items = $this->storeItems($options, $silent);

        if ($items === [] || $ids->isEmpty()) {
            return new ImapIdSet([], $sequence);
        }

        $command = $sequence ? 'STORE' : 'UID STORE';
        $modified = new ImapIdSet([], $sequence);

        foreach ($items as [$key, $flags]) {
            $arguments = [new ImapWireAtom((string) $ids)];

            if (isset($options['unchangedsince'])) {
                $arguments[] = new ImapWireList([
                    new ImapWireAtom('UNCHANGEDSINCE'),
                    new ImapWireNumber((int) $options['unchangedsince']),
                ]);
            }

            $arguments[] = new ImapWireAtom($key);
            $arguments[] = $flags;

            $result = $this->interaction->send($command, $arguments);
            $modified = $this->mergeModified($modified, $result->tagged, $sequence);
        }

        // A flag change makes any cached flags for these messages stale.
        // Drop their cache entries (UID mode only, the cache keys on UID);
        // the next fetch re-warms them. Over-invalidating the immutable
        // fields is cheap and keeps this unambiguously correct.
        if (!$sequence && $this->cache !== null && $ids instanceof ImapIdSet && !$ids->isSpecial()) {
            $this->cache->deleteMsgs($this->selectedMailbox ?? '', $ids->toArray());
        }

        return $modified;
    }

    /**
     * Permanently remove messages flagged `\Deleted` (RFC 3501 §6.4.3),
     * or a UID subset of them with `UID EXPUNGE` (UIDPLUS, RFC 4315).
     *
     * With `delete: true` the requested `ids` are flagged `\Deleted`
     * first. With `list: true` the expunged messages are collected and
     * returned: sequence numbers from `* n EXPUNGE` on a plain
     * connection, or UIDs from `* VANISHED` once QRESYNC is enabled
     * (RFC 7162 §3.2.10). Cache-driven expunge bookkeeping is a
     * separate concern (see {@see ImapCacheStore}).
     *
     * @param array{ids?: MessageIdSet, delete?: bool, list?: bool, sequence?: bool} $options
     *
     * @return MessageIdSet The expunged messages when `list` was set
     *                      (UIDs under QRESYNC, else sequence numbers),
     *                      otherwise empty.
     *
     * @throws ServerResponseException If the server rejects the command.
     */
    public function expunge(string $mailbox, array $options = []): MessageIdSet
    {
        $this->connect();

        $ids = $options['ids'] ?? null;
        $uidExpunge = $this->getCapability()->query('UIDPLUS')
            && $ids instanceof ImapIdSet
            && !$ids->isEmpty()
            && !$ids->isSequence();

        if (!empty($options['delete']) && $ids instanceof ImapIdSet && !$ids->isEmpty()) {
            $this->store($mailbox, ['ids' => $ids, 'add' => [SystemFlag::Deleted]]);
        }

        if ($uidExpunge) {
            // RFC 4315 §2.1: UID EXPUNGE only touches the given UIDs.
            $result = $this->interaction->send('UID EXPUNGE', [new ImapWireAtom((string) $ids)]);
        } else {
            $result = $this->interaction->send('EXPUNGE');
        }

        // Drop expunged messages from the cache. VANISHED (QRESYNC) always
        // gives UIDs; a UID EXPUNGE targeted a known UID set; a plain
        // EXPUNGE reports sequence numbers, which are not cache keys, so a
        // full-mailbox EXPUNGE conservatively drops the mailbox cache.
        $this->invalidateExpunged($mailbox, $ids, $uidExpunge, $result->untagged);

        // Signal external listeners such as an application-level
        // search-result cache with the removed set, independent of the
        // caller's `list` option. The set is UIDs when the server reported
        // them (VANISHED / UID EXPUNGE) and sequence numbers otherwise. The
        // event exposes which via ImapIdSet::isSequence().
        $removed = $this->collectExpunged($result->untagged, true);
        $this->dispatch(new MailboxExpunged($mailbox, $removed, $this->selectedUidValidity));

        return $this->collectExpunged($result->untagged, !empty($options['list']));
    }

    /**
     * Copy messages to another mailbox (RFC 3501 §6.4.7).
     *
     * @param array{ids?: MessageIdSet, create?: bool} $options
     *
     * @return MessageIdSet The destination UIDs from a `[COPYUID ...]`
     *                      response (UIDPLUS, RFC 4315), or empty when the
     *                      server does not report them.
     *
     * @throws ServerResponseException If the server rejects the COPY.
     */
    public function copy(string $source, string $dest, array $options = []): MessageIdSet
    {
        return $this->copyOrMove($dest, $options, move: false);
    }

    /**
     * Move messages to another mailbox.
     *
     * Uses the `MOVE` command (RFC 6851) when the server advertises it,
     * otherwise falls back to `COPY` followed by expunging the source
     * messages (flagging them `\Deleted` and running `UID EXPUNGE`).
     *
     * @param array{ids?: MessageIdSet, create?: bool} $options
     *
     * @return MessageIdSet The destination UIDs from a `[COPYUID ...]`
     *                      response, or empty when unreported.
     *
     * @throws ServerResponseException If the server rejects the command.
     */
    public function move(string $source, string $dest, array $options = []): MessageIdSet
    {
        return $this->copyOrMove($dest, $options, move: true);
    }

    /**
     * Append one or more messages to a mailbox (RFC 3501 §6.3.11).
     *
     * Each `$data` entry is
     * `['data' => string, 'flags' => list, 'internaldate' => DateTimeInterface]`
     * (only `data` is required). Alternatively `data` may be a CATENATE
     * (RFC 4469) parts list: `['catenate' => [['text' => '...'],
     * ['url' => 'imap://...'], ...]]`, assembled server-side when the
     * server advertises `CATENATE`.
     *
     * Extensions used when advertised: `MULTIAPPEND` (RFC 3502, all
     * messages in one command), `literal8`/`~{n}` (RFC 3516, automatic for
     * 8-bit bodies), and the `UTF8 (...)` wrapper (RFC 6855) once
     * UTF8=ACCEPT is enabled. `create: true` creates the mailbox and
     * retries on `[TRYCREATE]`.
     *
     * @param list<array{data?: string, catenate?: list<array{text?: string, url?: string}>, flags?: list<SystemFlag|string>, internaldate?: DateTimeInterface}> $data
     * @param array{create?: bool} $options
     *
     * @return MessageIdSet The new UIDs from `[APPENDUID ...]` responses
     *                      (UIDPLUS, RFC 4315), or empty when unreported.
     *
     * @throws CapabilityNotSupportedException If a `url` CATENATE part is
     *                                            used without server CATENATE.
     * @throws ServerResponseException          If the server rejects the APPEND.
     */
    public function append(string $mailbox, array $data, array $options = []): MessageIdSet
    {
        $this->connect();

        $create = !empty($options['create']);

        // MULTIAPPEND (RFC 3502): one command carrying every message.
        if (count($data) > 1 && $this->getCapability()->query('MULTIAPPEND')) {
            return $this->sendAppend($mailbox, $data, $create);
        }

        $uids = [];

        foreach ($data as $message) {
            foreach ($this->sendAppend($mailbox, [$message], $create)->toArray() as $uid) {
                $uids[] = $uid;
            }
        }

        return new ImapIdSet($uids, false);
    }

    /**
     * Send one APPEND command carrying one or more messages (the latter
     * only under MULTIAPPEND), handling the `[TRYCREATE]` retry.
     *
     * @param list<array<string, mixed>> $messages
     */
    private function sendAppend(string $mailbox, array $messages, bool $create): MessageIdSet
    {
        $arguments = [new ImapWireMailbox($mailbox, $this->mailboxNameCodec())];

        foreach ($messages as $message) {
            array_push($arguments, ...$this->appendMessageArguments($message));
        }

        try {
            $result = $this->interaction->send('APPEND', $arguments);
        } catch (ServerResponseException $e) {
            if ($create && $this->hasTryCreate($e)) {
                $this->createMailbox($mailbox);

                return $this->sendAppend($mailbox, $messages, false);
            }

            throw $e;
        }

        // MULTIAPPEND returns one [APPENDUID validity uid1,uid2,...] code.
        return $this->parseUidPlus($result->tagged, 'APPENDUID', 1);
    }

    /**
     * The wire arguments for one appended message: optional flags,
     * optional internaldate, then the message content (a plain literal, a
     * `UTF8 (...)` wrapper under UTF8=ACCEPT, or a `CATENATE (...)` list).
     *
     * @param array<string, mixed> $message
     *
     * @return list<ImapWireEncodable>
     */
    private function appendMessageArguments(array $message): array
    {
        $arguments = [];

        if (!empty($message['flags']) && is_array($message['flags'])) {
            $arguments[] = $this->flagsList($message['flags']);
        }

        if (isset($message['internaldate']) && $message['internaldate'] instanceof DateTimeInterface) {
            // RFC 3501 date-time: 1-Feb-1994 12:00:00 +0000, quoted.
            $arguments[] = new ImapWireString($message['internaldate']->format('j-M-Y H:i:s O'));
        }

        if (isset($message['catenate']) && is_array($message['catenate'])) {
            $arguments[] = new ImapWireAtom('CATENATE');
            $arguments[] = $this->catenateList($message['catenate']);

            return $arguments;
        }

        $content = new ImapWireString(is_string($message['data'] ?? null) ? $message['data'] : '');

        // RFC 6855 §4: under UTF8=ACCEPT, an appended message is wrapped as
        // UTF8 (...). literal8 (~{n}) is chosen automatically by the wire
        // layer for 8-bit content (RFC 3516).
        $arguments[] = $this->utf8Enabled()
            ? new ImapWireList([new ImapWireAtom('UTF8'), new ImapWireList([$content])])
            : $content;

        return $arguments;
    }

    /**
     * Build a CATENATE parts list (RFC 4469): `(TEXT {n}... URL url ...)`.
     * A `url` part requires the server to support CATENATE (it resolves
     * the URL server-side); this library does not fetch-and-inline URLs
     * client-side.
     *
     * @param list<array{text?: string, url?: string}> $parts
     *
     * @throws CapabilityNotSupportedException If a URL part is used but the
     *                                            server lacks CATENATE.
     */
    private function catenateList(array $parts): ImapWireList
    {
        $hasUrl = false;

        foreach ($parts as $part) {
            if (isset($part['url'])) {
                $hasUrl = true;
            }
        }

        if (!$this->getCapability()->query('CATENATE') && $hasUrl) {
            throw new CapabilityNotSupportedException(
                'A URL CATENATE part requires the server CATENATE extension (RFC 4469).'
            );
        }

        $list = new ImapWireList();

        foreach ($parts as $part) {
            if (isset($part['url'])) {
                $list->add(new ImapWireAtom('URL'));
                $list->add(new ImapWireString($part['url'], isAstring: true));
            } elseif (isset($part['text'])) {
                $list->add(new ImapWireAtom('TEXT'));
                $list->add(new ImapWireString($part['text']));
            }
        }

        return $list;
    }

    /**
     * Thread the mailbox (RFC 5256).
     *
     * Sends `[UID] THREAD <algorithm> <charset> <search-criteria>`. The
     * `criteria` option chooses the algorithm as a {@see ThreadAlgorithm}
     * case or a bare string (`ORDEREDSUBJECT`, `REFERENCES`, `REFS`),
     * defaulting to ORDEREDSUBJECT. The `search` option narrows the
     * threaded set with an {@see ImapSearchQuery}; without it, `ALL` is
     * threaded. `sequence` selects sequence-number vs UID ids.
     *
     * The server must advertise the chosen algorithm via `THREAD=<algo>`
     * (RFC 5256 §3); this does not implement the client-side
     * ORDEREDSUBJECT fallback the legacy driver offered.
     *
     * @param array{criteria?: ThreadAlgorithm|string, search?: ImapSearchQuery, sequence?: bool} $options
     *
     * @throws CapabilityNotSupportedException If the server does not advertise the algorithm.
     * @throws ServerResponseException         If the server rejects the THREAD.
     */
    public function thread(string $mailbox, array $options = []): ImapThreadResult
    {
        $this->connect();

        $algorithm = $this->threadAlgorithm($options['criteria'] ?? ThreadAlgorithm::OrderedSubject);

        if (!$this->getCapability()->query('THREAD', $algorithm)) {
            throw new CapabilityNotSupportedException(
                "The server does not advertise the '{$algorithm}' THREAD algorithm (RFC 5256)."
            );
        }

        $sequence = !empty($options['sequence']);
        $command = $sequence ? 'THREAD' : 'UID THREAD';

        $arguments = [new ImapWireAtom($algorithm)];
        $search = $options['search'] ?? null;

        // Charset is mandatory for THREAD (RFC 5256 §3). Under UTF-8 mode
        // the client MUST send UTF-8 (RFC 6855 §3); otherwise a query's
        // own charset, falling back to US-ASCII.
        $built = $search instanceof ImapSearchQuery ? $search->build() : ['charset' => null, 'criteria' => []];
        $arguments[] = new ImapWireAtom($this->utf8Enabled() ? 'UTF-8' : ($built['charset'] ?? 'US-ASCII'));

        if ($built['criteria'] === []) {
            $arguments[] = new ImapWireAtom('ALL');
        } else {
            array_push($arguments, ...$built['criteria']);
        }

        $result = $this->interaction->send($command, $arguments);

        return ImapThreadParser::parse($result->untagged, $sequence);
    }

    /**
     * Enable the QRESYNC extension (RFC 7162 §3.1) on this connection.
     *
     * QRESYNC requires CONDSTORE and enabling it implicitly enables
     * CONDSTORE too. Must be called after login (ENABLE is only valid in
     * the authenticated state). Returns true when the server acknowledged
     * it.
     *
     * @throws CapabilityNotSupportedException If the server does not advertise QRESYNC.
     */
    public function enableQresync(): bool
    {
        $this->connect();
        $capability = $this->getCapability();

        if ($capability->isEnabled('QRESYNC')) {
            return true;
        }

        if (!$capability->query('QRESYNC')) {
            throw new CapabilityNotSupportedException(
                'The server does not advertise QRESYNC (RFC 7162).'
            );
        }

        return in_array('QRESYNC', $this->negotiator->enable($capability, ['QRESYNC']), true);
    }

    /**
     * Report which of a set of UIDs have been expunged since a given
     * modification sequence, using QRESYNC's `VANISHED` FETCH modifier
     * (RFC 7162 §3.2.5).
     *
     * Sends `UID FETCH <ids> (UID) (VANISHED CHANGEDSINCE <modseq>)` and
     * collects the resulting `* VANISHED (EARLIER) ...` UIDs. Defaults to
     * checking every UID (`1:*`). Requires QRESYNC to be enabled first
     * (via {@see enableQresync()}), and the caller to have the mailbox
     * open.
     *
     * @param array{ids?: ImapIdSet} $options
     *
     * @throws CapabilityNotSupportedException If QRESYNC is not enabled.
     * @throws ServerResponseException         If the server rejects the FETCH.
     */
    public function vanished(string $mailbox, int $modseq, array $options = []): ImapIdSet
    {
        $this->connect();

        if (!$this->getCapability()->isEnabled('QRESYNC')) {
            throw new CapabilityNotSupportedException(
                'VANISHED requires QRESYNC to be enabled first (RFC 7162); call enableQresync().'
            );
        }

        $ids = $options['ids'] ?? new ImapIdSet(ImapIdSetToken::All, false);

        $arguments = [
            new ImapWireAtom((string) $ids),
            new ImapWireAtom('UID'),
            new ImapWireList([
                new ImapWireAtom('VANISHED'),
                new ImapWireAtom('CHANGEDSINCE'),
                new ImapWireNumber($modseq),
            ]),
        ];

        $result = $this->interaction->send('UID FETCH', $arguments);

        return ImapVanishedParser::parse($result->untagged);
    }

    /**
     * Take an opaque sync token capturing a mailbox's current state, for
     * a later {@see sync()} to diff against.
     *
     * The token records the mailbox's UIDVALIDITY, HIGHESTMODSEQ (0 when
     * the server lacks CONDSTORE) and UIDNEXT. It is a base64 string with
     * no guaranteed internal format; treat it as opaque.
     *
     * @throws ServerResponseException If the STATUS call fails.
     */
    public function getSyncToken(string $mailbox): string
    {
        $this->connect();

        $status = $this->status(
            $mailbox,
            StatusFlag::UidValidity->value | StatusFlag::UidNext->value | StatusFlag::HighestModSeq->value,
        );

        $parts = [
            'V' . (int) ($status->uidvalidity ?? 0),
            'H' . (int) ($status->highestmodseq ?? 0),
            'U' . (int) ($status->uidnext ?? 0),
        ];

        return base64_encode(implode(',', $parts));
    }

    /**
     * Report what changed in a mailbox since a {@see getSyncToken()} was
     * taken (the modern, cache-free equivalent of the legacy
     * `Horde_Imap_Client_Base::sync()`).
     *
     * Built entirely on the existing wire primitives:
     * `STATUS` for the current state, `SEARCH` for new messages
     * (`UID <token-uidnext>:*`) and flag changes (`MODSEQ <token-modseq>`,
     * CONDSTORE), and `vanished()` for expunged UIDs (QRESYNC). It holds
     * no cached state of its own; the caller supplies the token.
     *
     * @param list<SyncCriteria> $criteria Which change classes to report
     *                                     (default: all three).
     *
     * @throws SyncException           If the token is malformed or the
     *                                   mailbox's UIDVALIDITY changed
     *                                   (a full resync is then required).
     * @throws ServerResponseException If a server command fails.
     */
    public function sync(string $mailbox, string $token, array $criteria = []): ImapSyncResult
    {
        $this->connect();

        $parsed = $this->decodeSyncToken($token);
        $criteria = $criteria === []
            ? [SyncCriteria::NewMessages, SyncCriteria::FlagChanges, SyncCriteria::Vanished]
            : $criteria;

        $status = $this->status(
            $mailbox,
            StatusFlag::UidValidity->value | StatusFlag::UidNext->value | StatusFlag::HighestModSeq->value,
        );
        $currentUidValidity = (int) ($status->uidvalidity ?? 0);

        if ($parsed['V'] === 0 || $currentUidValidity !== $parsed['V']) {
            throw new SyncException(
                'The mailbox UIDVALIDITY has changed; a full resynchronization is required (RFC 3501 §2.3.1.1).'
            );
        }

        $empty = new ImapIdSet([], false);
        $newMsgs = $flagChanges = $vanished = $empty;

        if (in_array(SyncCriteria::NewMessages, $criteria, true) && $parsed['U'] > 0) {
            $query = (new ImapSearchQuery())->uidFrom($parsed['U']);
            $newMsgs = $this->search($mailbox, $query)->match;
        }

        // Flag changes and vanished both hinge on CONDSTORE/MODSEQ; with
        // no server modseq there is nothing reliable to diff.
        if ($parsed['H'] > 0) {
            if (in_array(SyncCriteria::FlagChanges, $criteria, true)) {
                $query = (new ImapSearchQuery())->modseq($parsed['H'] + 1);
                $flagChanges = $this->search($mailbox, $query)->match;
            }

            if (in_array(SyncCriteria::Vanished, $criteria, true) && $this->getCapability()->isEnabled('QRESYNC')) {
                $vanished = $this->vanished($mailbox, $parsed['H']);
            }
        }

        return new ImapSyncResult($newMsgs, $flagChanges, $vanished);
    }

    /**
     * Get the access control list of a mailbox (RFC 4314 §3.3, `GETACL`).
     *
     * @return array<string, ImapAcl> Rights keyed by identifier. A
     *                                negative right entry keeps its
     *                                leading `-` in the key.
     *
     * @throws CapabilityNotSupportedException If the server lacks ACL.
     * @throws ServerResponseException         If the server rejects the command.
     */
    public function getACL(string $mailbox): array
    {
        $this->requireCapability('ACL');
        $result = $this->interaction->send('GETACL', [new ImapWireMailbox($mailbox, $this->mailboxNameCodec())]);

        foreach ($result->untagged as $response) {
            if ($this->isUntaggedNamed($response, 'ACL')) {
                return $this->parseAclResponse($response->data);
            }
        }

        return [];
    }

    /**
     * Set the rights of an identifier on a mailbox (RFC 4314 §3.1,
     * `SETACL`).
     *
     * The `rights` option is the right string; prefix a right modifier
     * (`+`/`-`) as RFC 4314 §3.1 allows to add or remove rather than
     * replace.
     *
     * @param array{rights?: string} $options
     *
     * @throws CapabilityNotSupportedException If the server lacks ACL.
     */
    public function setACL(string $mailbox, string $identifier, array $options): void
    {
        $this->requireCapability('ACL');

        // SETACL returns no untagged data (RFC 4314 §3.1).
        $this->interaction->send('SETACL', [
            new ImapWireMailbox($mailbox, $this->mailboxNameCodec()),
            new ImapWireString($identifier, isAstring: true),
            new ImapWireString($options['rights'] ?? '', isAstring: true),
        ]);
    }

    /**
     * Remove an identifier's rights from a mailbox (RFC 4314 §3.2,
     * `DELETEACL`).
     *
     * @throws CapabilityNotSupportedException If the server lacks ACL.
     */
    public function deleteACL(string $mailbox, string $identifier): void
    {
        $this->requireCapability('ACL');

        // DELETEACL returns no untagged data (RFC 4314 §3.2).
        $this->interaction->send('DELETEACL', [
            new ImapWireMailbox($mailbox, $this->mailboxNameCodec()),
            new ImapWireString($identifier, isAstring: true),
        ]);
    }

    /**
     * List the rights that can be granted to an identifier on a mailbox
     * (RFC 4314 §3.7, `LISTRIGHTS`).
     *
     * @throws CapabilityNotSupportedException If the server lacks ACL.
     */
    public function listACLRights(string $mailbox, string $identifier): ImapAclRights
    {
        $this->requireCapability('ACL');
        $result = $this->interaction->send('LISTRIGHTS', [
            new ImapWireMailbox($mailbox, $this->mailboxNameCodec()),
            new ImapWireString($identifier, isAstring: true),
        ]);

        foreach ($result->untagged as $response) {
            if ($this->isUntaggedNamed($response, 'LISTRIGHTS')) {
                // data: LISTRIGHTS mailbox identifier required optional...
                $rights = array_values(array_filter(
                    array_slice($response->data, 3),
                    static fn ($value): bool => is_string($value),
                ));

                $required = $rights === [] ? '' : array_shift($rights);

                return new ImapAclRights(str_split($required), $rights);
            }
        }

        return new ImapAclRights();
    }

    /**
     * The current user's rights on a mailbox (RFC 4314 §3.8, `MYRIGHTS`).
     *
     * @throws CapabilityNotSupportedException If the server lacks ACL.
     */
    public function getMyACLRights(string $mailbox): ImapAcl
    {
        $this->requireCapability('ACL');
        $result = $this->interaction->send('MYRIGHTS', [new ImapWireMailbox($mailbox, $this->mailboxNameCodec())]);

        foreach ($result->untagged as $response) {
            if ($this->isUntaggedNamed($response, 'MYRIGHTS') && isset($response->data[2]) && is_string($response->data[2])) {
                return new ImapAcl($response->data[2]);
            }
        }

        return new ImapAcl();
    }

    /**
     * Set resource limits on a quota root (RFC 9208 §5.1, `SETQUOTA`).
     *
     * @param array<string, int> $resources Resource name => limit.
     *
     * @throws CapabilityNotSupportedException If the server lacks QUOTA.
     */
    public function setQuota(string $root, array $resources): void
    {
        $this->requireCapability('QUOTA');

        $limits = new ImapWireList();

        foreach ($resources as $name => $limit) {
            // RFC 9208 §5.1: a flat (resource limit resource limit ...)
            // list, not nested per-resource groups.
            $limits->add(new ImapWireAtom(strtoupper($name)));
            $limits->add(new ImapWireNumber($limit));
        }

        $this->interaction->send('SETQUOTA', [
            new ImapWireMailbox($root, $this->mailboxNameCodec()),
            $limits,
        ]);
    }

    /**
     * Get the resource usage and limits of a quota root (RFC 9208 §5.2,
     * `GETQUOTA`).
     *
     * @return array<string, array<string, array{usage: int, limit: int}>>
     *         Keyed by quota root, then resource name.
     *
     * @throws CapabilityNotSupportedException If the server lacks QUOTA.
     */
    public function getQuota(string $root): array
    {
        $this->requireCapability('QUOTA');
        $result = $this->interaction->send('GETQUOTA', [new ImapWireMailbox($root, $this->mailboxNameCodec())]);

        return $this->parseQuotaResponses($result->untagged);
    }

    /**
     * Get the quota roots that apply to a mailbox and their resource
     * usage (RFC 9208 §5.3, `GETQUOTAROOT`).
     *
     * @return array<string, array<string, array{usage: int, limit: int}>>
     *
     * @throws CapabilityNotSupportedException If the server lacks QUOTA.
     */
    public function getQuotaRoot(string $mailbox): array
    {
        $this->requireCapability('QUOTA');
        $result = $this->interaction->send('GETQUOTAROOT', [new ImapWireMailbox($mailbox, $this->mailboxNameCodec())]);

        return $this->parseQuotaResponses($result->untagged);
    }

    /**
     * Get metadata entries for a mailbox (RFC 5464 §4.2, `GETMETADATA`).
     * An empty `$mailbox` reads server metadata (`METADATA-SERVER`).
     *
     * @param list<string>                  $entries Entry names.
     * @param array{maxsize?: int, depth?: int|string} $options
     *
     * @return array<string, array<string, ?string>> Values keyed by
     *         mailbox, then entry name.
     *
     * @throws CapabilityNotSupportedException If the server lacks METADATA.
     */
    public function getMetadata(string $mailbox, array $entries, array $options = []): array
    {
        $this->requireMetadataCapability($mailbox);

        $arguments = [new ImapWireMailbox($mailbox, $this->mailboxNameCodec())];
        $cmdOptions = new ImapWireList();

        if (!empty($options['maxsize'])) {
            $cmdOptions->add(new ImapWireAtom('MAXSIZE'));
            $cmdOptions->add(new ImapWireNumber((int) $options['maxsize']));
        }

        if (!empty($options['depth'])) {
            $cmdOptions->add(new ImapWireAtom('DEPTH'));
            $cmdOptions->add(new ImapWireAtom((string) $options['depth']));
        }

        if (count($cmdOptions) > 0) {
            $arguments[] = $cmdOptions;
        }

        $entryList = new ImapWireList();
        foreach ($entries as $entry) {
            $entryList->add(new ImapWireString($entry, isAstring: true));
        }
        $arguments[] = $entryList;

        $result = $this->interaction->send('GETMETADATA', $arguments);

        return $this->parseMetadataResponses($result->untagged);
    }

    /**
     * Set metadata entries on a mailbox (RFC 5464 §4.3, `SETMETADATA`).
     * A null value removes an entry.
     *
     * @param array<string, ?string> $data Entry name => value.
     *
     * @throws CapabilityNotSupportedException If the server lacks METADATA.
     */
    public function setMetadata(string $mailbox, array $data): void
    {
        $this->requireMetadataCapability($mailbox);

        $entries = new ImapWireList();

        foreach ($data as $entry => $value) {
            $entries->add(new ImapWireString((string) $entry, isAstring: true));
            $entries->add($value === null ? new ImapWireNil() : new ImapWireNstring($value));
        }

        $this->interaction->send('SETMETADATA', [
            new ImapWireMailbox($mailbox, $this->mailboxNameCodec()),
            $entries,
        ]);
    }

    /**
     * Build an {@see ImapIdSet} from a caller-supplied ID argument.
     *
     * Mirrors {@see Pop3Client::getIdsOb()}
     * `null` yields an empty set.
     * An existing {@see MessageIdSet} is copied by value.
     * A string is parsed as an IMAP sequence string (`1:5,7`, including the special `1:*`/`*`/`$`
     * tokens).
     * An array or scalar becomes an explicit list.
     *
     * @param iterable<int|string>|string|int|ImapIdSetToken|MessageIdSet|null $ids
     */
    public function getIdsOb(mixed $ids = null, bool $sequence = false): MessageIdSet
    {
        if ($ids === null) {
            return new ImapIdSet([], $sequence);
        }

        if ($ids instanceof ImapIdSet) {
            return $sequence === $ids->isSequence()
                ? $ids
                : new ImapIdSet($ids->isSpecial() ? $ids->token() : $ids->toArray(), $sequence);
        }

        if ($ids instanceof MessageIdSet) {
            return new ImapIdSet($ids->toArray(), $sequence);
        }

        if ($ids instanceof ImapIdSetToken) {
            return new ImapIdSet($ids, $sequence);
        }

        if (is_string($ids)) {
            return ImapIdSet::fromSequenceString($ids, $sequence);
        }

        return new ImapIdSet(is_iterable($ids) ? $ids : [$ids], $sequence);
    }

    /**
     * Fetch message data for a set of ids.
     *
     * Mirrors {@see Pop3Client::fetch()}:
     * A generator yielding one result per message as the server sends it
     * keyed by sequence number (when `$ids` is a sequence set) or by UID (otherwise).
     * The `$query` must be an {@see ImapFetchQuery}.
     *
     * The caller is responsible for having opened `$mailbox` first (via
     * {@see openMailbox()})
     * `fetch()` does not implicitly `SELECT`.
     * An empty `$ids` set yields nothing and sends no command.
     *
     * When an {@see ImapCacheStore} was supplied, the cacheable fields
     * (envelope, flags, header-field groups, internaldate, size,
     * bodystructure: the same set the legacy driver cached) are written
     * through on every fetch, and served from cache when the query asks
     * only for those fields in UID mode. Cached flags are only trusted
     * while the mailbox's HIGHESTMODSEQ is unchanged (CONDSTORE, RFC 7162);
     * otherwise they are re-fetched. Body/text stream content (full
     * message, body sections, MIME headers, raw parts) is never cached.
     *
     * @param ImapFetchQuery|object $query An {@see ImapFetchQuery}.
     *
     * @return Generator<int|string, ImapFetchResult>
     *
     * @throws ImapProtocolException If `$query` is not an {@see ImapFetchQuery}.
     * @throws ServerResponseException If the server rejects the FETCH.
     */
    public function fetch(string $mailbox, MessageIdSet $ids, object $query): Generator
    {
        if (!$query instanceof ImapFetchQuery) {
            throw new ImapProtocolException('fetch() requires an ImapFetchQuery.');
        }

        $this->connect();

        if ($ids->isEmpty()) {
            return;
        }

        $sequenceMode = $ids instanceof ImapIdSet && $ids->isSequence();

        // Cache read-through is only possible in UID mode (the cache keys
        // on UID) and only when every requested field is cacheable (a
        // query wanting body/text content must hit the wire regardless).
        $wantUids = !$sequenceMode && $this->cache !== null && $this->cacheServiceable($query)
            ? $ids->toArray()
            : [];
        $servedFromCache = [];

        if ($wantUids !== []) {
            foreach ($this->readFetchCache($wantUids, $query) as $uid => $result) {
                $servedFromCache[$uid] = true;
                yield $uid => $result;
            }
        }

        // Whatever the cache could not serve (or, in sequence mode,
        // everything) is fetched from the wire.
        $wireIds = $servedFromCache === []
            ? $ids
            : new ImapIdSet(array_values(array_diff($wantUids, array_keys($servedFromCache))), false);

        if ($servedFromCache !== [] && $wireIds->isEmpty()) {
            return;
        }

        $items = $query->wireItems();

        // A UID FETCH returns the UID implicitly (RFC 3501 §6.4.8) so it
        // is only worth requesting explicitly in sequence mode and only
        // if the caller asked for it. In UID mode the parser already
        // has the UID from the response.
        if ($sequenceMode && $query->wantsUid()) {
            $items[] = 'UID';
        }

        // size() and imapDate() live in the shared FetchQueryFields trait
        // and only set a flag; translate them to their IMAP wire items
        // here (the trait has no concept of the wire form).
        if ($query->wantsSize()) {
            $items[] = 'RFC822.SIZE';
        }

        if ($query->wantsImapDate()) {
            $items[] = 'INTERNALDATE';
        }

        if ($items === []) {
            // A FETCH with an empty item list is invalid. Fall back to UID,
            // the cheapest always-valid item.
            $items[] = 'UID';
        }

        $command = $sequenceMode ? 'FETCH' : 'UID FETCH';
        $arguments = [
            new ImapWireAtom((string) $wireIds),
            new ImapWireList($items),
        ];

        $result = $this->interaction->send($command, $arguments);

        foreach ($result->untagged as $response) {
            $parsed = ImapFetchParser::parse($response, $query);

            if ($parsed === null) {
                continue;
            }

            // Write cacheable fields through (UID mode + cache present).
            if (!$sequenceMode && $this->cache !== null) {
                $this->writeFetchCache($parsed['result'], $query);
            }

            yield ($sequenceMode ? $parsed['seq'] : $parsed['result']->getUid()) => $parsed['result'];
        }
    }

    /**
     * Whether a query can be fully served from the metadata cache: it
     * wants at least one cacheable field and no stream content, and a
     * mailbox is open with a known UIDVALIDITY.
     */
    private function cacheServiceable(ImapFetchQuery $query): bool
    {
        if ($this->cache === null || $this->selectedUidValidity === 0 || $query->wantsStreamContent()) {
            return false;
        }

        return $query->wantsEnvelope()
            || $query->wantsFlags()
            || $query->wantsStructure()
            || $query->wantsModSeq()
            || $query->wantsSize()
            || $query->wantsImapDate()
            || $query->headerLabels() !== [];
    }

    /**
     * Serve the requested UIDs from the cache, yielding one
     * {@see ImapFetchResult} per fully-satisfiable UID. A UID missing any
     * requested field (or whose cached flags are stale under CONDSTORE) is
     * skipped so the caller fetches it from the wire.
     *
     * @param list<int> $uids
     *
     * @return Generator<int, ImapFetchResult>
     */
    private function readFetchCache(array $uids, ImapFetchQuery $query): Generator
    {
        $cached = $this->cache->get($this->selectedMailbox ?? '', $uids, [], $this->selectedUidValidity);

        foreach ($uids as $uid) {
            $fields = $cached[$uid] ?? null;

            if ($fields === null) {
                continue;
            }

            $result = $this->fetchResultFromCache($uid, $fields, $query);

            if ($result !== null) {
                yield $uid => $result;
            }
        }
    }

    /**
     * Rebuild an {@see ImapFetchResult} from cached fields, or null when a
     * requested field is absent (or flags are stale). The freshness of
     * cached flags is gated on the mailbox's current HIGHESTMODSEQ: if it
     * advanced past the modseq the flags were cached at, another session
     * may have changed them, so they are treated as missing (RFC 7162).
     *
     * @param array<string, mixed> $fields
     */
    private function fetchResultFromCache(int $uid, array $fields, ImapFetchQuery $query): ?ImapFetchResult
    {
        $result = new ImapFetchResult(0);
        $result->setUid($uid);

        if ($query->wantsEnvelope()) {
            if (!($fields['envelope'] ?? null) instanceof ImapEnvelope) {
                return null;
            }
            $result->setEnvelope($fields['envelope']);
        }

        if ($query->wantsStructure()) {
            if (!($fields['structure'] ?? null) instanceof Part) {
                return null;
            }
            $result->setStructure($fields['structure']);
        }

        if ($query->wantsSize()) {
            if (!isset($fields['size'])) {
                return null;
            }
            $result->setSize((int) $fields['size']);
        }

        if ($query->wantsImapDate()) {
            if (!($fields['imapdate'] ?? null) instanceof DateTimeImmutable) {
                return null;
            }
            $result->setImapDate($fields['imapdate']);
        }

        if ($query->wantsModSeq()) {
            if (!isset($fields['modseq'])) {
                return null;
            }
            $result->setModSeq((int) $fields['modseq']);
        }

        if ($query->wantsFlags()) {
            $flagModseq = isset($fields['flags_modseq']) ? (int) $fields['flags_modseq'] : 0;

            // Trust cached flags only if the mailbox HIGHESTMODSEQ has not
            // advanced since they were stored (CONDSTORE). Without a server
            // modseq at all, flags cannot be validated and are refetched.
            if (
                !isset($fields['flags'])
                || !is_array($fields['flags'])
                || $this->selectedHighestModSeq === 0
                || $flagModseq === 0
                || $flagModseq < $this->selectedHighestModSeq
            ) {
                return null;
            }

            $result->setFlags(array_values(array_filter($fields['flags'], 'is_string')));
        }

        // Restore each requested header-field group's raw text.
        foreach ($query->headerLabels() as $label) {
            $stored = $fields['headers'][$label] ?? null;

            if (!is_string($stored)) {
                return null;
            }

            $result->setHeaders($label, $stored);
        }

        return $result;
    }

    /**
     * Write a fetched result's cacheable fields through to the cache.
     * Only fields actually requested (and present) are stored; stream
     * content is never cached.
     */
    private function writeFetchCache(ImapFetchResult $result, ImapFetchQuery $query): void
    {
        $uid = $result->getUid();

        if ($this->selectedUidValidity === 0 || !is_int($uid) || $uid <= 0) {
            return;
        }

        $fields = [];

        if ($query->wantsEnvelope()) {
            $fields['envelope'] = $result->getEnvelope();
        }

        if ($query->wantsStructure()) {
            $fields['structure'] = $result->getStructure();
        }

        if ($query->wantsSize()) {
            $fields['size'] = $result->getSize();
        }

        if ($query->wantsImapDate()) {
            $fields['imapdate'] = $result->getImapDate();
        }

        $modseq = $result->getModSeq();

        if ($query->wantsModSeq() && $modseq !== null) {
            $fields['modseq'] = $modseq;
        }

        if ($query->wantsFlags()) {
            $fields['flags'] = $result->getFlags();
            // Stamp the modseq the flags are valid as of, for the freshness
            // gate on read. Prefer the message's own MODSEQ, else the
            // mailbox HIGHESTMODSEQ captured at SELECT.
            $fields['flags_modseq'] = $modseq ?? $this->selectedHighestModSeq;
        }

        // Header-field groups: store the raw text keyed by label. The
        // result reconstructs the HeaderCollection from it on read.
        foreach ($query->headerLabels() as $label) {
            $raw = $result->getRawHeaders($label);

            if ($raw !== null) {
                $fields['headers'][$label] = $raw;
            }
        }

        if ($fields !== []) {
            $this->cache->set($this->selectedMailbox ?? '', [$uid => $fields], $this->selectedUidValidity);
        }
    }

    private function finishLogin(): void
    {
        $this->loggedIn = true;

        // RFC 3501 §6.3.10: The capability list MAY change once
        // authenticated (e.g. LOGINDISABLED disappearing or new
        // extensions becoming visible). Don't trust data from pre-auth.
        //
        $this->capability = null;
        $this->capabilityFetched = false;

        $capability = $this->getCapability();
        // ENABLE is only valid once authenticated (RFC 5161 §3.1) so
        // rev2/UTF8=ACCEPT negotiation has to happen here.
        $this->negotiator->negotiateRev2($capability);
    }

    /**
     * @param list<string> $authMechanisms
     */
    private function loginSasl(array $authMechanisms): void
    {
        // Shares $this->tags with $this->interaction so AUTHENTICATE's
        // tag and any ordinary command's tag never collide on the same
        // connection.
        $channel = new ImapAuthChannel($this->connection, $this->tags);

        $this->authenticator ??= new SaslAuthenticator(
            $this->config,
            $this->credentials,
            new SocketChannelBindingProvider($this->client),
            $this->dispatcher,
        );

        $this->authenticator->authenticate(
            $channel,
            $authMechanisms,
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

        try {
            $this->interaction->send('LOGIN', [
                new ImapWireString($credentials->authcid(), isAstring: true),
                new ImapWireString($password, isAstring: true),
            ]);
        } catch (ServerResponseException $e) {
            throw new AuthenticationException($e->getMessage(), 0, $e);
        }
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

        $connection = new ImapConnection($this->client);
        $greeting = $connection->readResponse();

        if ($greeting->isBye() || !$greeting->isUntagged() || $greeting->status === null) {
            throw new ConnectionException(
                'Unexpected server greeting: ' . ($greeting->text !== '' ? $greeting->text : '(none)')
            );
        }

        $this->connection = $connection;
        $this->tags = new ImapCommandTag();
        $this->interaction = new ImapInteraction($connection, $this->tags);
        $this->negotiator = new ImapCapabilityNegotiator($this->interaction);

        // RFC 3501 §7.1: A greeting MAY piggyback its CAPABILITY data
        // sparing an explicit round trip.
        $capability = new ImapCapability();
        $this->capabilityFetched = ImapCapabilityNegotiator::mergeFromResponse($greeting, $capability);
        $this->capability = $capability;

        if ($greeting->isPreAuth()) {
            $this->loggedIn = true;
        }
    }

    private function defaultPort(): int
    {
        return $this->config->secure === SecureMode::Ssl ? 993 : 143;
    }

    private function maybeUpgradeTls(): void
    {
        if ($this->config->secure !== SecureMode::Tls || $this->client->isSecure()) {
            return;
        }

        if (!$this->getCapability()->query('STARTTLS')) {
            throw new ConnectionException(
                'Could not open a secure connection: the server does not advertise STARTTLS.'
            );
        }

        $this->interaction->send('STARTTLS');

        if (!$this->client->startTls()) {
            throw new ConnectionException('Could not open secure connection to the IMAP server.');
        }

        // RFC 3501 §6.2.1: Capabilities MUST be re-checked after
        // STARTTLS since a pre-TLS CAPABILITY response is unauthenticated
        // and could have been forged by an attacker.
        $this->capability = null;
        $this->capabilityFetched = false;
    }

    private function mailboxNameCodec(): ImapMailboxNameCodec
    {
        return $this->utf8Enabled()
            ? new ImapUtf8MailboxNameCodec()
            : new ImapModifiedUtf7Codec();
    }

    /**
     * Whether the connection is in UTF-8 mode: either `UTF8=ACCEPT` is
     * enabled (RFC 6855) or `IMAP4REV2`, which implies it (RFC 9051 §5.1).
     * Drives both mailbox-name encoding and SEARCH CHARSET suppression.
     */
    private function utf8Enabled(): bool
    {
        $capability = $this->capability ?? new ImapCapability();

        return $capability->isEnabled('IMAP4REV2') || $capability->isEnabled('UTF8=ACCEPT');
    }

    /**
     * @return list<string>
     */
    private function statusItems(int $flags): array
    {
        $all = ($flags & StatusFlag::All->value) !== 0;
        $items = [];

        if ($all || ($flags & StatusFlag::Messages->value) !== 0) {
            $items[] = 'MESSAGES';
        }

        if ($all || ($flags & StatusFlag::Recent->value) !== 0) {
            $items[] = 'RECENT';
        }

        if ($all || ($flags & StatusFlag::UidNext->value) !== 0) {
            $items[] = 'UIDNEXT';
        }

        if ($all || ($flags & StatusFlag::UidValidity->value) !== 0) {
            $items[] = 'UIDVALIDITY';
        }

        if ($all || ($flags & StatusFlag::Unseen->value) !== 0) {
            $items[] = 'UNSEEN';
        }

        // HIGHESTMODSEQ needs CONDSTORE (RFC 7162 §3.6). Request it when
        // asked explicitly, or under All only if the already-known
        // capability advertises it. `status()` must not trigger an extra
        // CAPABILITY round trip just to expand All, so this reads the
        // cached capability rather than fetching one.
        $wantModSeq = ($flags & StatusFlag::HighestModSeq->value) !== 0
            || ($all && $this->capability?->query('CONDSTORE') === true);

        if ($wantModSeq) {
            $items[] = 'HIGHESTMODSEQ';
        }

        return $items;
    }

    /**
     * @param list<ImapResponse> $untagged
     *
     * @return array<string, int|string>
     */
    private function parseStatusResponse(array $untagged): array
    {
        $result = [];

        foreach ($untagged as $response) {
            if (
                !$response->isUntagged()
                || count($response->data) < 3
                || !is_string($response->data[0])
                || strtoupper($response->data[0]) !== 'STATUS'
                || !is_array($response->data[2])
            ) {
                continue;
            }

            $result = array_merge($result, $this->parseStatusPairs($response->data[2]));
        }

        return $result;
    }

    /**
     * Build a `LIST-EXTENDED` command (RFC 5258 §3): optional select
     * options, the empty reference name, the pattern, and an optional
     * `RETURN` list. Subscription mode drives `SUBSCRIBED`; the extended
     * options add `CHILDREN`/`SPECIAL-USE` returns, `REMOTE`/
     * `RECURSIVEMATCH` selects, and a `STATUS` return (LIST-STATUS,
     * RFC 5819) gated on capability.
     *
     * @param array<string, mixed> $options
     *
     * @return array{name: string, arguments: list<ImapWireEncodable>}
     */
    private function buildExtendedListCommand(string $pattern, MailboxListMode $mode, array $options = []): array
    {
        $selectOptions = new ImapWireList();
        $returnOptions = new ImapWireList();

        switch ($mode) {
            case MailboxListMode::Subscribed:
            case MailboxListMode::SubscribedExists:
                $selectOptions->add(new ImapWireAtom('SUBSCRIBED'));
                $returnOptions->add(new ImapWireAtom('SUBSCRIBED'));
                break;

            case MailboxListMode::AllSubscribed:
            case MailboxListMode::Unsubscribed:
                $returnOptions->add(new ImapWireAtom('SUBSCRIBED'));
                break;

            case MailboxListMode::All:
                break;
        }

        if (!empty($options['remote'])) {
            $selectOptions->add(new ImapWireAtom('REMOTE'));
        }

        if (!empty($options['recursivematch'])) {
            $selectOptions->add(new ImapWireAtom('RECURSIVEMATCH'));
        }

        if (!empty($options['children'])) {
            $returnOptions->add(new ImapWireAtom('CHILDREN'));
        }

        if (!empty($options['special_use'])) {
            $returnOptions->add(new ImapWireAtom('SPECIAL-USE'));
        }

        // LIST-STATUS (RFC 5819): RETURN (STATUS (item ...)). Independent
        // of LIST-EXTENDED, but only meaningful with the RETURN syntax.
        if (!empty($options['status']) && $this->getCapability()->query('LIST-STATUS')) {
            $statusItems = $this->statusItems((int) $options['status']);

            if ($statusItems !== []) {
                $returnOptions->add(new ImapWireAtom('STATUS'));
                $returnOptions->add(new ImapWireList(array_map(
                    static fn (string $item): ImapWireEncodable => new ImapWireAtom($item),
                    $statusItems,
                )));
            }
        }

        $arguments = [];

        if (count($selectOptions) > 0) {
            $arguments[] = $selectOptions;
        }

        // The reference name (RFC 3501 §6.3.8) is left empty so the
        // pattern is interpreted against the root.
        $arguments[] = new ImapWireString('', isAstring: true);
        $arguments[] = new ImapWireMailbox($pattern, $this->mailboxNameCodec(), allowWildcards: true);

        if (count($returnOptions) > 0) {
            $arguments[] = new ImapWireAtom('RETURN');
            $arguments[] = $returnOptions;
        }

        return ['name' => 'LIST', 'arguments' => $arguments];
    }

    /**
     * Build a base RFC 3501 `LIST`/`LSUB` command. `LSUB` (RFC 3501
     * §6.3.9) serves the subscribed-only modes when the server lacks
     * `LIST-EXTENDED`.
     *
     * @return array{name: string, arguments: list<ImapWireEncodable>}
     */
    private function buildBaseListCommand(string $pattern, MailboxListMode $mode): array
    {
        $subscribedOnly = $mode === MailboxListMode::Subscribed
            || $mode === MailboxListMode::SubscribedExists;

        return [
            'name' => $subscribedOnly ? 'LSUB' : 'LIST',
            'arguments' => [
                new ImapWireString('', isAstring: true),
                new ImapWireMailbox($pattern, $this->mailboxNameCodec(), allowWildcards: true),
            ],
        ];
    }

    /**
     * Parse the untagged `LIST`/`LSUB` responses into the legacy result
     * shape.
     *
     * @param list<ImapResponse> $untagged
     *
     * @return array<int|string, mixed>
     */
    private function parseListResponses(
        array $untagged,
        MailboxListMode $mode,
        bool $flat,
        bool $wantAttributes,
        bool $extended,
    ): array {
        $result = [];

        foreach ($untagged as $response) {
            $entry = $this->parseListResponse($response, $mode, $flat, $wantAttributes, $extended);

            if ($entry === null) {
                continue;
            }

            if ($flat) {
                $result[] = $entry['mailbox'];
            } else {
                $result[$entry['mailbox']] = $entry;
            }
        }

        return $result;
    }

    /**
     * Parse one untagged `LIST`/`LSUB` line (RFC 3501 §7.2.2-7.2.3):
     * `LIST (attributes) delimiter mailbox`. Returns null for a line that
     * is not a LIST/LSUB response or that the requested `$mode` filters
     * out (an unsubscribed entry in a subscribed-only listing, and so on).
     *
     * @return array{mailbox: string, delimiter: ?string, attributes?: list<string>}|null
     */
    private function parseListResponse(
        ImapResponse $response,
        MailboxListMode $mode,
        bool $flat,
        bool $wantAttributes,
        bool $extended,
    ): ?array {
        if (
            !$response->isUntagged()
            || count($response->data) < 4
            || !is_string($response->data[0])
        ) {
            return null;
        }

        $type = strtoupper($response->data[0]);

        if ($type !== 'LIST' && $type !== 'LSUB') {
            return null;
        }

        if (!is_array($response->data[1])) {
            return null;
        }

        $rawAttributes = array_values(array_filter(
            $response->data[1],
            static fn ($value): bool => is_string($value),
        ));
        $delimiter = is_string($response->data[2]) ? $response->data[2] : null;

        if (!is_string($response->data[3])) {
            return null;
        }

        $mailbox = $this->mailboxNameCodec()->decode($response->data[3]);

        /** @var array<string, true> $attributes */
        $attributes = [];
        foreach ($rawAttributes as $attribute) {
            $attributes[strtolower($attribute)] = true;
        }

        // RFC 5258 §3.4: with LIST-EXTENDED, some attributes imply others.
        if ($extended) {
            if (isset($attributes['\\noinferiors'])) {
                $attributes['\\hasnochildren'] = true;
            }

            if (isset($attributes['\\nonexistent'])) {
                $attributes['\\noselect'] = true;
            }
        }

        if ($this->listEntryFilteredOut($mode, $attributes, $mailbox)) {
            return null;
        }

        $entry = ['mailbox' => $mailbox, 'delimiter' => $delimiter];

        if (!$flat && ($wantAttributes || $rawAttributes !== [])) {
            $entry['attributes'] = array_keys($attributes);
        }

        return $entry;
    }

    /**
     * Apply the RFC 5258 subscription/existence filtering the modes that
     * only make sense with LIST-EXTENDED impose.
     *
     * @param array<string, true> $attributes
     */
    private function listEntryFilteredOut(MailboxListMode $mode, array $attributes, string $mailbox): bool
    {
        // INBOX is always considered subscribed (RFC 3501 §5.1).
        $subscribed = isset($attributes['\\subscribed']) || strcasecmp($mailbox, 'INBOX') === 0;

        return match ($mode) {
            MailboxListMode::SubscribedExists =>
                isset($attributes['\\nonexistent']) || !$subscribed,
            MailboxListMode::Unsubscribed => $subscribed,
            default => false,
        };
    }

    /**
     * Fold the interleaved `* STATUS mailbox (...)` responses a
     * LIST-STATUS (RFC 5819) reply carries into the matching list
     * entries, under a `status` key.
     *
     * @param array<int|string, mixed> $list     Parsed LIST result, by ref.
     * @param list<ImapResponse>        $untagged
     */
    private function attachListStatus(array &$list, array $untagged): void
    {
        $codec = $this->mailboxNameCodec();

        foreach ($untagged as $response) {
            if (
                !$response->isUntagged()
                || count($response->data) < 3
                || !is_string($response->data[0])
                || strtoupper($response->data[0]) !== 'STATUS'
                || !is_string($response->data[1])
                || !is_array($response->data[2])
            ) {
                continue;
            }

            $mailbox = $codec->decode($response->data[1]);

            if (!isset($list[$mailbox]) || !is_array($list[$mailbox])) {
                continue;
            }

            $list[$mailbox]['status'] = $this->parseStatusPairs($response->data[2]);
        }
    }

    /**
     * Turn a flat `(KEY value KEY value ...)` STATUS attribute list into a
     * lowercase-keyed map, digits coerced to int.
     *
     * @param list<mixed> $pairs
     *
     * @return array<string, int|string>
     */
    private function parseStatusPairs(array $pairs): array
    {
        $out = [];
        $total = count($pairs);

        for ($i = 0; $i + 1 < $total; $i += 2) {
            if (!is_string($pairs[$i])) {
                continue;
            }

            $value = $pairs[$i + 1];
            $out[strtolower($pairs[$i])] = is_string($value) && ctype_digit($value) ? (int) $value : $value;
        }

        return $out;
    }

    /**
     * Parse a `NAMESPACE` response (RFC 2342 §5, RFC 5255 §3.4) into an
     * {@see ImapNamespaceList}.
     *
     * The response after the `NAMESPACE` word is a fixed triple of
     * parenthesized lists (personal, other, shared), each either `NIL`
     * or a list of `(prefix delimiter [extensions...])` entries.
     *
     * @param list<mixed> $data The whole untagged response tokens,
     *                          starting with the `NAMESPACE` word.
     */
    private function parseNamespaceResponse(array $data): ImapNamespaceList
    {
        $codec = $this->mailboxNameCodec();
        $namespaces = [];
        $types = [NamespaceType::Personal, NamespaceType::Other, NamespaceType::Shared];

        foreach ($types as $index => $type) {
            $group = $data[$index + 1] ?? null;

            if (!is_array($group)) {
                continue;
            }

            foreach ($group as $entry) {
                if (!is_array($entry) || $entry === [] || !is_string($entry[0])) {
                    continue;
                }

                $delimiter = (isset($entry[1]) && is_string($entry[1])) ? $entry[1] : null;

                $namespaces[] = new ImapNamespace(
                    name: $codec->decode($entry[0]),
                    type: $type,
                    delimiter: $delimiter,
                    translation: $this->namespaceTranslation($entry),
                );
            }
        }

        return new ImapNamespaceList($namespaces);
    }

    /**
     * Extract the RFC 5255 §3.4 TRANSLATION extension value from one
     * namespace entry's trailing `name value` extension pairs, if present.
     *
     * @param list<mixed> $entry
     */
    private function namespaceTranslation(array $entry): string
    {
        // Extensions begin after the prefix and delimiter (RFC 4466).
        for ($i = 2; $i + 1 < count($entry); $i += 2) {
            if (!is_string($entry[$i]) || strtoupper($entry[$i]) !== 'TRANSLATION') {
                continue;
            }

            $value = $entry[$i + 1];

            // The value is itself a parenthesized list of strings; take
            // the first (RFC 5255 §3.4).
            if (is_array($value) && isset($value[0]) && is_string($value[0])) {
                return $value[0];
            }

            if (is_string($value)) {
                return $value;
            }
        }

        return '';
    }

    /**
     * Map the requested {@see SearchResultType} set onto the ESEARCH
     * `RETURN` option atoms (RFC 4731 §3.1). `Match` maps to `ALL`;
     * `Save` maps to `SAVE` (RFC 5182). An empty or match-only request
     * still asks for `ALL` so the matching set always comes back.
     *
     * @param list<SearchResultType> $results
     *
     * @return list<ImapWireEncodable>
     */
    private function searchReturnOptions(array $results): array
    {
        $atoms = [];

        foreach ($results as $type) {
            $atom = match ($type) {
                SearchResultType::Count => 'COUNT',
                SearchResultType::Match => 'ALL',
                SearchResultType::Max => 'MAX',
                SearchResultType::Min => 'MIN',
                SearchResultType::Save => 'SAVE',
                SearchResultType::Relevancy => 'RELEVANCY',
            };

            $atoms[$atom] = true;
        }

        if ($atoms === []) {
            $atoms['ALL'] = true;
        }

        return array_map(
            static fn (string $atom): ImapWireEncodable => new ImapWireAtom($atom),
            array_keys($atoms),
        );
    }

    /**
     * Send a server-side `SORT`/`UID SORT` (RFC 5256), using the ESORT
     * `RETURN (...)` extension (RFC 5267) when advertised. Unlike SEARCH,
     * SORT's charset is mandatory and follows the sort-criteria list.
     *
     * @param array{charset: ?string, criteria: list<ImapWireEncodable>} $built
     * @param list<SortCriteria>       $sort
     * @param list<SearchResultType>   $results
     *
     * @throws CapabilityNotSupportedException If the server does not advertise SORT.
     */
    private function sortSearch(ImapSearchQuery $query, array $built, array $sort, array $results, bool $sequence): ImapSearchResult
    {
        if (!$this->getCapability()->query('SORT')) {
            throw new CapabilityNotSupportedException(
                'The server does not advertise SORT (RFC 5256).'
            );
        }

        $command = $sequence ? 'SORT' : 'UID SORT';
        $arguments = [];

        // ESORT (RFC 5267 §3.4) returns a compact result the same way
        // ESEARCH does, when advertised.
        if ($this->getCapability()->query('ESORT')) {
            $arguments[] = new ImapWireAtom('RETURN');
            $arguments[] = new ImapWireList($this->searchReturnOptions($results));
        }

        $arguments[] = new ImapWireList($this->sortCriteriaList($sort));

        // Charset is mandatory for SORT (RFC 5256 §3); UTF-8 mode forces
        // UTF-8 (RFC 6855 §3), otherwise the query charset or US-ASCII.
        $arguments[] = new ImapWireAtom($this->utf8Enabled() ? 'UTF-8' : ($built['charset'] ?? 'US-ASCII'));

        if ($built['criteria'] === []) {
            $arguments[] = new ImapWireAtom('ALL');
        } else {
            array_push($arguments, ...$built['criteria']);
        }

        $result = $this->interaction->send($command, $arguments);

        // A `* SORT ...` reply preserves the server's ordering, which
        // ImapIdSet keeps (it dedups in first-seen order, never sorts).
        return ImapSearchParser::parse($result->untagged, $sequence);
    }

    /**
     * Map the requested {@see SortCriteria} cases onto the SORT criteria
     * atoms (RFC 5256 §3, RFC 5957 DISPLAYFROM/DISPLAYTO). `REVERSE`
     * inverts the ordering of the criterion that follows it. Client-only
     * cases (`Sequence`, the DISPLAY fallbacks) are skipped since there is
     * no server-side atom for them.
     *
     * @param list<SortCriteria> $sort
     *
     * @return list<ImapWireEncodable>
     */
    private function sortCriteriaList(array $sort): array
    {
        $atoms = [];

        foreach ($sort as $criterion) {
            $atom = match ($criterion) {
                SortCriteria::Arrival => 'ARRIVAL',
                SortCriteria::Cc => 'CC',
                SortCriteria::Date => 'DATE',
                SortCriteria::From => 'FROM',
                SortCriteria::Reverse => 'REVERSE',
                SortCriteria::Size => 'SIZE',
                SortCriteria::Subject => 'SUBJECT',
                SortCriteria::To => 'TO',
                SortCriteria::DisplayFrom => 'DISPLAYFROM',
                SortCriteria::DisplayTo => 'DISPLAYTO',
                SortCriteria::Relevancy => 'RELEVANCY',
                default => null,
            };

            if ($atom !== null) {
                $atoms[] = new ImapWireAtom($atom);
            }
        }

        // A SORT with no criteria is invalid; fall back to ARRIVAL.
        return $atoms === [] ? [new ImapWireAtom('ARRIVAL')] : $atoms;
    }

    /**
     * Resolve a thread algorithm option to its wire atom.
     */
    private function threadAlgorithm(ThreadAlgorithm|string $criteria): string
    {
        if (is_string($criteria)) {
            return strtoupper($criteria);
        }

        return match ($criteria) {
            ThreadAlgorithm::OrderedSubject => 'ORDEREDSUBJECT',
            ThreadAlgorithm::References => 'REFERENCES',
            ThreadAlgorithm::Refs => 'REFS',
        };
    }

    /**
     * Decode a {@see getSyncToken()} string into its
     * UIDVALIDITY/HIGHESTMODSEQ/UIDNEXT parts.
     *
     * @return array{V: int, H: int, U: int}
     *
     * @throws SyncException If the token is not valid base64 or is
     *                        missing the UIDVALIDITY part.
     */
    private function decodeSyncToken(string $token): array
    {
        $decoded = base64_decode($token, true);

        if ($decoded === false) {
            throw new SyncException('Malformed sync token.');
        }

        $parsed = ['V' => 0, 'H' => 0, 'U' => 0];

        foreach (explode(',', $decoded) as $part) {
            if ($part === '') {
                continue;
            }

            $letter = $part[0];
            $value = substr($part, 1);

            if (isset($parsed[$letter]) && ctype_digit($value)) {
                $parsed[$letter] = (int) $value;
            }
        }

        if ($parsed['V'] === 0) {
            throw new SyncException('Malformed sync token: missing UIDVALIDITY.');
        }

        return $parsed;
    }

    /**
     * Throw unless the server advertises `$capability`. Used by the
     * optional ACL/QUOTA extension methods so each guards on its own
     * capability, per the independently-optional `*Aware` design.
     *
     * @throws CapabilityNotSupportedException
     */
    private function requireCapability(string $capability): void
    {
        $this->connect();

        if (!$this->getCapability()->query($capability)) {
            throw new CapabilityNotSupportedException(
                "The server does not advertise the {$capability} extension."
            );
        }
    }

    /**
     * METADATA (RFC 5464) has two capability tokens: `METADATA` for
     * mailbox annotations and `METADATA-SERVER` for server-wide ones (an
     * empty mailbox name). Accept either as appropriate.
     *
     * @throws CapabilityNotSupportedException
     */
    private function requireMetadataCapability(string $mailbox): void
    {
        $this->connect();
        $capability = $this->getCapability();

        if ($capability->query('METADATA')) {
            return;
        }

        if ($mailbox === '' && $capability->query('METADATA-SERVER')) {
            return;
        }

        throw new CapabilityNotSupportedException(
            'The server does not advertise the METADATA extension (RFC 5464).'
        );
    }

    private function isUntaggedNamed(ImapResponse $response, string $name): bool
    {
        return $response->isUntagged()
            && $response->data !== []
            && is_string($response->data[0])
            && strtoupper($response->data[0]) === $name;
    }

    /**
     * Parse a `* ACL mailbox (identifier rights)*` response (RFC 4314
     * §3.6). A negative-rights identifier keeps its leading `-`.
     *
     * @param list<mixed> $data
     *
     * @return array<string, ImapAcl>
     */
    private function parseAclResponse(array $data): array
    {
        $acl = [];
        // data: ACL mailbox [identifier rights]...
        $pairs = array_slice($data, 2);

        for ($i = 0; $i + 1 < count($pairs); $i += 2) {
            if (!is_string($pairs[$i]) || !is_string($pairs[$i + 1])) {
                continue;
            }

            $acl[$pairs[$i]] = new ImapAcl($pairs[$i + 1]);
        }

        return $acl;
    }

    /**
     * Parse `* QUOTA root (resource usage limit ...)` responses (RFC 9208
     * §5.1), ignoring the `* QUOTAROOT` lines a GETQUOTAROOT interleaves.
     *
     * @param list<ImapResponse> $untagged
     *
     * @return array<string, array<string, array{usage: int, limit: int}>>
     */
    private function parseQuotaResponses(array $untagged): array
    {
        $out = [];

        foreach ($untagged as $response) {
            if (!$this->isUntaggedNamed($response, 'QUOTA')) {
                continue;
            }

            $root = is_string($response->data[1] ?? null) ? $response->data[1] : '';
            $resources = $response->data[2] ?? null;
            $out[$root] = [];

            if (!is_array($resources)) {
                continue;
            }

            $total = count($resources);

            for ($i = 0; $i + 2 < $total; $i += 3) {
                if (!is_string($resources[$i])) {
                    continue;
                }

                $out[$root][strtolower($resources[$i])] = [
                    'usage' => (int) (is_string($resources[$i + 1]) ? $resources[$i + 1] : 0),
                    'limit' => (int) (is_string($resources[$i + 2]) ? $resources[$i + 2] : 0),
                ];
            }
        }

        return $out;
    }

    /**
     * Parse `* METADATA mailbox (entry value ...)` responses (RFC 5464
     * §4.4).
     *
     * @param list<ImapResponse> $untagged
     *
     * @return array<string, array<string, ?string>>
     */
    private function parseMetadataResponses(array $untagged): array
    {
        $codec = $this->mailboxNameCodec();
        $out = [];

        foreach ($untagged as $response) {
            if (!$this->isUntaggedNamed($response, 'METADATA') || !isset($response->data[1]) || !is_string($response->data[1])) {
                continue;
            }

            $mailbox = $codec->decode($response->data[1]);
            $pairs = $response->data[2] ?? null;

            if (!is_array($pairs)) {
                continue;
            }

            $out[$mailbox] ??= [];

            for ($i = 0; $i + 1 < count($pairs); $i += 2) {
                if (!is_string($pairs[$i])) {
                    continue;
                }

                $value = $pairs[$i + 1];
                $out[$mailbox][$pairs[$i]] = is_string($value) ? $value : null;
            }
        }

        return $out;
    }

    /**
     * Turn the store options into the ordered `(key, flagsList)` pairs a
     * STORE command needs: a single `FLAGS[.SILENT]` for a replace, or up
     * to one each of `+FLAGS[.SILENT]`/`-FLAGS[.SILENT]` for add/remove
     * (RFC 3501 §6.4.6).
     *
     * @param array<string, mixed> $options
     *
     * @return list<array{0: string, 1: ImapWireList}>
     */
    private function storeItems(array $options, bool $silent): array
    {
        $suffix = $silent ? '.SILENT' : '';
        $items = [];

        if (!empty($options['replace'])) {
            $items[] = ['FLAGS' . $suffix, $this->flagsList($options['replace'])];

            return $items;
        }

        if (!empty($options['add'])) {
            $items[] = ['+FLAGS' . $suffix, $this->flagsList($options['add'])];
        }

        if (!empty($options['remove'])) {
            $items[] = ['-FLAGS' . $suffix, $this->flagsList($options['remove'])];
        }

        return $items;
    }

    /**
     * Build a parenthesized flag list from {@see SystemFlag} cases or
     * plain keyword strings. `\Recent` is dropped: it is not a settable
     * flag (RFC 3501 §2.3.2).
     *
     * @param list<SystemFlag|string> $flags
     */
    private function flagsList(array $flags): ImapWireList
    {
        $list = new ImapWireList();

        foreach ($flags as $flag) {
            $name = $flag instanceof SystemFlag ? $flag->value : $flag;

            if (strcasecmp($name, '\\Recent') === 0) {
                continue;
            }

            $list->add(new ImapWireAtom($name));
        }

        return $list;
    }

    /**
     * Fold a tagged STORE completion's `[MODIFIED <set>]` code (RFC 7162
     * §3.1.3) into the running set of not-updated messages.
     */
    private function mergeModified(ImapIdSet $modified, ImapResponse $tagged, bool $sequence): ImapIdSet
    {
        $code = $tagged->responseCode;

        if ($code === null || strtoupper($code->name) !== 'MODIFIED' || !isset($code->data[0]) || !is_string($code->data[0])) {
            return $modified;
        }

        $extra = ImapIdSet::fromSequenceString($code->data[0], $sequence)->toArray();

        return new ImapIdSet([...$modified->toArray(), ...$extra], $sequence);
    }

    /**
     * Collect the messages reported expunged by an EXPUNGE/UID EXPUNGE
     * command, when the caller asked for the list.
     *
     * A plain connection reports `* n EXPUNGE` sequence numbers (RFC 3501
     * §7.4.1). Once QRESYNC is enabled the server reports `* VANISHED`
     * UIDs instead (RFC 7162 §3.2.10); the two forms never appear
     * together for one command. The returned set therefore holds sequence
     * numbers or UIDs depending on which the server sent, flagged
     * accordingly.
     *
     * @param list<ImapResponse> $untagged
     */
    private function collectExpunged(array $untagged, bool $wantList): ImapIdSet
    {
        if (!$wantList) {
            return new ImapIdSet([], true);
        }

        // Prefer VANISHED (QRESYNC) when present: it carries UIDs, which
        // are more useful than the sequence numbers a plain EXPUNGE gives.
        $vanished = ImapVanishedParser::parse($untagged);

        if (!$vanished->isEmpty()) {
            return $vanished;
        }

        $expunged = [];

        foreach ($untagged as $response) {
            if (
                $response->isUntagged()
                && count($response->data) >= 2
                && is_string($response->data[0])
                && ctype_digit($response->data[0])
                && is_string($response->data[1])
                && strtoupper($response->data[1]) === 'EXPUNGE'
            ) {
                $expunged[] = (int) $response->data[0];
            }
        }

        // EXPUNGE responses report sequence numbers (RFC 3501 §7.4.1).
        return new ImapIdSet($expunged, true);
    }

    /**
     * Drop expunged messages from the cache after an EXPUNGE/UID EXPUNGE.
     *
     * @param list<ImapResponse> $untagged
     */
    private function invalidateExpunged(string $mailbox, ?MessageIdSet $ids, bool $uidExpunge, array $untagged): void
    {
        if ($this->cache === null) {
            return;
        }

        // VANISHED (QRESYNC) carries UIDs even for a plain EXPUNGE.
        $vanished = ImapVanishedParser::parse($untagged);

        if (!$vanished->isEmpty()) {
            $this->cache->deleteMsgs($mailbox, $vanished->toArray());

            return;
        }

        // A targeted UID EXPUNGE removed exactly the requested UID set.
        if ($uidExpunge && $ids instanceof ImapIdSet && !$ids->isSpecial()) {
            $this->cache->deleteMsgs($mailbox, $ids->toArray());

            return;
        }

        // A plain EXPUNGE reports only sequence numbers, which are not
        // cache keys. Conservatively drop the whole mailbox cache.
        $this->cache->deleteMailbox($mailbox);
    }

    /**
     * Empty a mailbox: open it read-write, flag every message `\Deleted`,
     * expunge, and close. Used by {@see deleteMailbox()} to satisfy
     * servers that refuse to delete a non-empty mailbox.
     */
    private function emptyMailbox(string $mailbox): void
    {
        $this->openMailbox($mailbox, OpenMode::ReadWrite);
        $allUids = new ImapIdSet(ImapIdSetToken::All, false);
        $this->expunge($mailbox, ['ids' => $allUids, 'delete' => true]);
        $this->close();
    }

    /**
     * Parse the flag-change `* n FETCH (...)` responses a QRESYNC
     * SELECT/EXAMINE volunteers (RFC 7162 §3.2.5.2), keyed by UID. A
     * QRESYNC FETCH always carries the UID, so any response missing one
     * is skipped.
     *
     * @param list<ImapResponse> $untagged
     *
     * @return array<int, ImapFetchResult>
     */
    private function collectQresyncChanges(array $untagged): array
    {
        // The server chooses what to send; ask the parser for flags and
        // modseq, the QRESYNC flag-change payload (RFC 7162 §3.2.5.2).
        $query = (new ImapFetchQuery())->flags()->modseq();
        $changed = [];

        foreach ($untagged as $response) {
            $parsed = ImapFetchParser::parse($response, $query);

            if ($parsed === null) {
                continue;
            }

            $uid = $parsed['result']->getUid();

            if (is_int($uid) && $uid > 0) {
                $changed[$uid] = $parsed['result'];
            }
        }

        return $changed;
    }

    /**
     * Shared COPY/MOVE implementation. When moving without server-side
     * `MOVE` (RFC 6851), falls back to COPY plus expunging the source.
     *
     * @param array{ids?: MessageIdSet, create?: bool} $options
     */
    private function copyOrMove(string $dest, array $options, bool $move): MessageIdSet
    {
        $this->connect();

        $ids = $options['ids'] ?? null;

        if (!$ids instanceof ImapIdSet || $ids->isEmpty()) {
            return new ImapIdSet([], false);
        }

        $sequence = $ids->isSequence();
        $serverMove = $move && $this->getCapability()->query('MOVE');
        $verb = $serverMove ? 'MOVE' : 'COPY';
        $command = $sequence ? $verb : 'UID ' . $verb;

        $arguments = [
            new ImapWireAtom((string) $ids),
            new ImapWireMailbox($dest, $this->mailboxNameCodec()),
        ];

        try {
            $result = $this->interaction->send($command, $arguments);
        } catch (ServerResponseException $e) {
            // RFC 3502 / RFC 4315: a [TRYCREATE] code means the
            // destination is missing. Create it once and retry.
            if (!empty($options['create']) && $this->hasTryCreate($e)) {
                $this->createMailbox($dest);
                unset($options['create']);

                return $this->copyOrMove($dest, $options, $move);
            }

            throw $e;
        }

        $copyUid = $this->parseUidPlus($result->tagged, 'COPYUID', 2);

        // A client-side move (no server MOVE) has to delete the source
        // messages itself once the copy succeeded. That path runs through
        // expunge(), which invalidates the cache and emits MailboxExpunged.
        if ($move && !$serverMove) {
            $this->expunge($this->selectedMailbox ?? '', ['ids' => $ids, 'delete' => true]);
        } elseif ($serverMove) {
            // A server-side MOVE (RFC 6851) removes the source messages
            // atomically and reports them via untagged VANISHED/EXPUNGE on
            // the MOVE reply. Invalidate the source cache and signal the
            // same removal event the expunge path would so a MOVE that
            // does not advance HIGHESTMODSEQ cannot leave stale entries in
            // an external cache.
            $source = $this->selectedMailbox ?? '';
            $this->invalidateExpunged($source, $ids, !$sequence, $result->untagged);
            $removed = $this->collectExpunged($result->untagged, true);
            $this->dispatch(new MailboxExpunged($source, $removed, $this->selectedUidValidity));
        }

        return $copyUid;
    }

    /**
     * Pull the UID set out of a UIDPLUS response code (RFC 4315):
     * `[COPYUID validity srcset destset]` (the dest set is at index 2) or
     * `[APPENDUID validity uidset]` (the set is at index 1).
     */
    private function parseUidPlus(ImapResponse $tagged, string $name, int $setIndex): MessageIdSet
    {
        $code = $tagged->responseCode;

        if (
            $code === null
            || strtoupper($code->name) !== $name
            || !isset($code->data[$setIndex])
            || !is_string($code->data[$setIndex])
        ) {
            return new ImapIdSet([], false);
        }

        return ImapIdSet::fromSequenceString($code->data[$setIndex], false);
    }

    /**
     * Whether a rejected command carried a `[TRYCREATE]` response code
     * (RFC 3501 §6.4.7 / §6.3.11).
     */
    private function hasTryCreate(ServerResponseException $e): bool
    {
        return $e->responseCode !== null && strtoupper($e->responseCode->name) === 'TRYCREATE';
    }
}



