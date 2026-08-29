#!/usr/bin/env php
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

/**
 * A runnable, non-disruptive end-to-end demonstration of the modern
 * {@see Horde\Imap\Client\ImapClient}.
 *
 * This is a manual smoke test, not an automated one; the unit suite
 * (test/Unit/Src/ImapClient*Test) covers the same code paths against a
 * scripted socket. Every command it issues is read-only: it never
 * deletes, expunges, appends, or changes a flag. It connects, reports
 * capabilities and namespaces, lists mailboxes, reads INBOX status, opens
 * INBOX read-only (EXAMINE), fetches a few message envelopes and flags,
 * and runs one SEARCH.
 *
 * Usage:
 *   imapclient.php --host <host> --user <user> [--pass <pass>]
 *                  [--port <port>] [--secure ssl|tls|none] [--limit <n>]
 *
 * The password is taken from --pass, else the IMAP_PASSWORD environment
 * variable, else an interactive (non-echoing) prompt.
 *
 * Example, Dovecot over implicit TLS:
 *   imapclient.php --host imap.example.com --user alice --secure ssl
 *
 * Example, STARTTLS on the submission port:
 *   imapclient.php --host imap.example.com --user alice --port 143 --secure tls
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Horde\Imap\Client\ConnectionConfig;
use Horde\Imap\Client\Exception\AuthenticationException;
use Horde\Imap\Client\Exception\ConnectionException;
use Horde\Imap\Client\Exception\ImapProtocolException;
use Horde\Imap\Client\ImapClient;
use Horde\Imap\Client\ImapFetchQuery;
use Horde\Imap\Client\ImapSearchQuery;
use Horde\Imap\Client\MailboxListMode;
use Horde\Imap\Client\OpenMode;
use Horde\Imap\Client\SecureMode;
use Horde\Imap\Client\StatusFlag;
use Horde\Sasl\Credentials\PasswordCredentials;
use Horde\Sasl\Credentials\PlainSecret;
use Horde\Sasl\Negotiation\SaslPolicy;

/**
 * Print usage and exit.
 */
function usageAndExit(): never
{
    fwrite(STDERR, "Usage: imapclient.php --host <host> --user <user> [--pass <pass>]\n");
    fwrite(STDERR, "       [--port <port>] [--secure ssl|tls|none] [--limit <n>]\n");
    exit(1);
}

/**
 * Read a password from the terminal without echoing it.
 */
function readPasswordInteractively(): string
{
    fwrite(STDOUT, 'IMAP password: ');
    shell_exec('stty -echo');
    $password = rtrim((string) fgets(STDIN), "\n");
    shell_exec('stty echo');
    fwrite(STDOUT, "\n");

    return $password;
}

$options = getopt('', ['host:', 'user:', 'pass::', 'port::', 'secure::', 'limit::']);

$host = $options['host'] ?? null;
$user = $options['user'] ?? null;

if ($host === null || $user === null) {
    usageAndExit();
}

$password = $options['pass'] ?? getenv('IMAP_PASSWORD');
if ($password === false || $password === '') {
    $password = readPasswordInteractively();
}

$port = isset($options['port']) ? (int) $options['port'] : null;
$limit = isset($options['limit']) ? max(1, (int) $options['limit']) : 5;

$secure = match ($options['secure'] ?? 'ssl') {
    'tls' => SecureMode::Tls,
    'none' => SecureMode::None,
    default => SecureMode::Ssl,
};

$config = new ConnectionConfig(
    hostspec: (string) $host,
    port: $port,
    secure: $secure,
    // Use SaslPolicy::legacyCompatible() instead for older servers.
    saslPolicy: SaslPolicy::secureDefaults(),
);

$credentials = new PasswordCredentials((string) $user, new PlainSecret((string) $password));

$client = new ImapClient($config, $credentials);

try {
    $client->login();

    // Capabilities.
    $capability = $client->getCapability();
    echo 'Connected to ' . $host . "\n";
    echo 'IMAP4rev2: ' . ($capability->query('IMAP4REV2') ? 'yes' : 'no') . "\n";
    echo 'CONDSTORE: ' . ($capability->query('CONDSTORE') ? 'yes' : 'no') . "\n";
    echo 'QRESYNC:   ' . ($capability->query('QRESYNC') ? 'yes' : 'no') . "\n\n";

    // Namespaces (RFC 2342).
    $namespaces = $client->getNamespaces();
    echo 'Namespaces: ' . count($namespaces) . "\n";
    foreach ($namespaces as $namespace) {
        printf("  %-20s delimiter=%s\n", '"' . $namespace->name . '"', $namespace->delimiter ?? 'NIL');
    }
    echo "\n";

    // Mailbox list (top level).
    $mailboxes = $client->listMailboxes('%', MailboxListMode::All);
    echo 'Mailboxes (top level): ' . count($mailboxes) . "\n";
    foreach ($mailboxes as $entry) {
        echo '  ' . $entry['mailbox'] . "\n";
    }
    echo "\n";

    // INBOX status.
    $status = $client->status('INBOX', StatusFlag::Messages->value | StatusFlag::Unseen->value);
    printf("INBOX: %d messages, %d unseen\n\n", $status->messages ?? 0, $status->unseen ?? 0);

    // Open INBOX read-only and fetch a few envelopes + flags.
    $client->openMailbox('INBOX', OpenMode::Readonly);
    $query = (new ImapFetchQuery())->envelope()->flags()->size();

    printf("%-8s %-30s %-8s %s\n", 'UID', 'Subject', 'Size', 'Flags');
    echo str_repeat('-', 70) . "\n";

    $count = 0;
    foreach ($client->fetch('INBOX', $client->getIdsOb('1:' . $limit), $query) as $uid => $message) {
        $subject = $message->getEnvelope()->subject;
        printf(
            "%-8s %-30s %-8d %s\n",
            (string) $uid,
            mb_strimwidth($subject === '' ? '(no subject)' : $subject, 0, 30),
            $message->getSize(),
            implode(' ', $message->getFlags()),
        );
        $count++;
    }

    if ($count === 0) {
        echo "(mailbox is empty)\n";
    }

    // A simple SEARCH: unseen messages.
    $search = $client->search('INBOX', (new ImapSearchQuery())->flag('\\Seen', false));
    echo "\nUnseen message UIDs: " . implode(', ', $search->match->toArray() ?: ['(none)']) . "\n";

    $client->logout();
} catch (ConnectionException $e) {
    fwrite(STDERR, 'Connection failed: ' . $e->getMessage() . "\n");
    exit(1);
} catch (AuthenticationException $e) {
    fwrite(STDERR, 'Authentication failed: ' . $e->getMessage() . "\n");
    exit(1);
} catch (ImapProtocolException $e) {
    fwrite(STDERR, 'IMAP error: ' . $e->getMessage() . "\n");
    exit(1);
}
