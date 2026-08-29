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
 * End-to-end example: connect to a real POP3 server with the modern
 * `src/` client. ^Then log in, list the mailbox, fetch each headers
 * and size for each message. Finally delete messages and
 * expunge them only if explicitly requested.
 *
 * This talks to a live server. It is a manual smoke-test script not intended as an
 * automated test. The unit suite covers the same code paths against a
 * scripted fake socket (see `test/Unit/Src/Pop3ClientTest.php`).
 *
 * Usage:
 *   php doc/examples/pop3client.php --host=pop.example.test --user=alice \
 *       [--pass=secret] [--port=995] [--secure=ssl|tls|none] \
 *       [--delete=uid1,uid2] [--expunge]
 *
 * If --pass is omitted, the password is read from the POP3_PASSWORD
 * environment variable or interactively (without echo) if neither is
 * set. That way a real password never has to appear in your shell
 * history or `ps` output.
 *
 * --delete marks the given message UIDs (as reported by the listing,
 * comma-separated) for deletion via `DELE`. Deletion only becomes
 * permanent once the session ends with `QUIT` (RFC 1939 §5). By default
 * this script undoes the marks via `RSET` before logging out, so a plain
 * --delete run is a safe dry run. Pass --expunge too to actually commit
 * the deletion.
 *
 * Example listing only:
 *   php doc/examples/pop3client.php --host=pop.gmail.com --user=alice@gmail.com
 *
 * Example deleting two messages and committing the deletion:
 *   php doc/examples/pop3client.php --host=pop.example.test --user=alice \
 *       --delete=1,2 --expunge
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Horde\Imap\Client\ConnectionConfig;
use Horde\Imap\Client\Exception\AuthenticationException;
use Horde\Imap\Client\Exception\ConnectionException;
use Horde\Imap\Client\Exception\Pop3ProtocolException;
use Horde\Imap\Client\Pop3Client;
use Horde\Imap\Client\Pop3FetchQuery;
use Horde\Imap\Client\SecureMode;
use Horde\Imap\Client\StatusFlag;
use Horde\Imap\Client\SystemFlag;
use Horde\Sasl\Credentials\PasswordCredentials;
use Horde\Sasl\Credentials\PlainSecret;
use Horde\Sasl\Negotiation\SaslPolicy;

function usageAndExit(string $message = ''): never
{
    if ($message !== '') {
        fwrite(STDERR, $message . "\n\n");
    }

    fwrite(STDERR, <<<USAGE
    Usage: php doc/examples/pop3client.php --host=HOST --user=USER
               [--pass=PASSWORD] [--port=PORT] [--secure=ssl|tls|none]
               [--delete=UID1,UID2,...] [--expunge]

    --pass may be omitted. It falls back to the POP3_PASSWORD environment
    variable and furthermore to an interactive (non-echoing) prompt.

    USAGE);

    exit(1);
}

/**
 * Read a password without echoing it to the terminal (falls back to a
 * plain `fgets()` on platforms without `stty`, e.g. Windows).
 */
function readPasswordInteractively(): string
{
    fwrite(STDERR, 'POP3 password: ');

    if (stripos(PHP_OS, 'WIN') === 0) {
        return trim((string) fgets(STDIN));
    }

    $sttyOriginal = shell_exec('stty -g');
    shell_exec('stty -echo');
    $password = trim((string) fgets(STDIN));
    shell_exec('stty ' . trim((string) $sttyOriginal));
    fwrite(STDERR, "\n");

    return $password;
}

$options = getopt('', ['host:', 'user:', 'pass::', 'port::', 'secure::', 'delete::', 'expunge']);

$host = $options['host'] ?? usageAndExit('Missing required --host');
$user = $options['user'] ?? usageAndExit('Missing required --user');
$port = isset($options['port']) ? (int) $options['port'] : null;

$secure = match ($options['secure'] ?? 'ssl') {
    'ssl' => SecureMode::Ssl,
    'tls' => SecureMode::Tls,
    'none' => SecureMode::None,
    default => usageAndExit('--secure must be one of: ssl, tls, none'),
};

$password = $options['pass'] ?? getenv('POP3_PASSWORD') ?: readPasswordInteractively();

if ($password === '') {
    usageAndExit('No password provided.');
}

$deleteUids = isset($options['delete'])
    ? array_filter(array_map('trim', explode(',', (string) $options['delete'])))
    : [];
$expunge = array_key_exists('expunge', $options);

// Most public POP3 servers today (Gmail included) only offer SASL PLAIN
// over an already-TLS-secured connection which secureDefaults() already
// allows. Switch to SaslPolicy::legacyCompatible() here if you're
// talking to an old server that only offers PLAIN/LOGIN without TLS.
$config = new ConnectionConfig(
    hostspec: (string) $host,
    port: $port,
    secure: $secure,
    saslPolicy: SaslPolicy::secureDefaults(),
);

$credentials = new PasswordCredentials((string) $user, new PlainSecret($password));

$client = new Pop3Client($config, $credentials);

try {
    echo "Connecting to {$host} as {$user}...\n";
    $client->login();
    echo "Logged in.\n\n";

    $status = $client->status(
        'INBOX',
        StatusFlag::Messages->value | StatusFlag::UidNext->value,
    );
    echo "Mailbox: {$status->messages} message(s), next UID {$status->uidnext}.\n\n";

    $query = (new Pop3FetchQuery())->headerText()->uid()->size()->seq()->imapDate();

    $listing = [];

    if ($status->messages > 0) {
        printf("%-6s %-10s %-8s %-20s %s\n", 'SEQ', 'UID', 'SIZE', 'DATE', 'SUBJECT');
        printf("%-6s %-10s %-8s %-20s %s\n", '---', '---', '----', '----', '-------');

        foreach ($client->fetch('INBOX', $client->getIdsOb(), $query) as $uid => $message) {
            $listing[] = $uid;

            $subject = 'no header';

            foreach (explode("\r\n", (string) $message->getHeaderText()) as $line) {
                if (stripos($line, 'Subject:') === 0) {
                    $subject = trim(substr($line, strlen('Subject:')));

                    break;
                }
            }

            printf(
                "%-6s %-10s %-8d %-20s %s\n",
                (string) $message->getSeq(),
                (string) $uid,
                $message->getSize(),
                $message->getImapDate()->format('Y-m-d H:i'),
                $subject,
            );
        }
    } else {
        echo "Mailbox is empty.\n";
    }

    echo "\n";

    if ($deleteUids !== []) {
        $unknown = array_diff($deleteUids, array_map('strval', $listing));

        if ($unknown !== []) {
            usageAndExit('Unknown UID(s) in --delete: ' . implode(', ', $unknown));
        }

        echo 'Marking for deletion: ' . implode(', ', $deleteUids) . "\n";

        $client->store('INBOX', [
            'ids' => $client->getIdsOb($deleteUids),
            'add' => [SystemFlag::Deleted],
        ]);

        if ($expunge) {
            // POP3 has no partial expunge (RFC 1939 §5). Committing the
            // deletions ends the session via QUIT.
            $expunged = $client->expunge('INBOX', ['list' => true]);
            echo 'Expunged (via QUIT): ' . implode(', ', array_map('strval', $expunged->toArray())) . "\n";

            exit(0);
        }

        // Without --expunge, undo the marks via RSET (RFC 1939 §5) making
        // this run a safe dry run. QUIT would otherwise commit the
        // deletion for real and POP3 has no way to undelete just some
        // of the marked messages afterwards.
        echo "Not committing (pass --expunge to actually delete). Undoing the marks via RSET.\n\n";
        $client->store('INBOX', ['remove' => [SystemFlag::Deleted]]);
    }

    $client->logout();
    echo "Logged out.\n";
} catch (ConnectionException $e) {
    fwrite(STDERR, 'Connection failed: ' . $e->getMessage() . "\n");

    exit(1);
} catch (AuthenticationException $e) {
    fwrite(STDERR, 'Authentication failed: ' . $e->getMessage() . "\n");

    exit(1);
} catch (Pop3ProtocolException $e) {
    fwrite(STDERR, 'POP3 protocol error: ' . $e->getMessage() . "\n");

    exit(1);
}
