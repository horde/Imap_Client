# POP3 Capabilities

POP3 is a mostly frozen protocol. Few features were added after 2010.
Server support for those later features is thin. The protocol is in
maintenance mode with barely any active development.

This document covers two things:

1. What `Pop3Client` (the modern `src/` client) implements and what it
   deliberately does not.
2. Which server products are likely to work with each capability.

## RFC history

- **RFC 1939** (May 1996, Standard). The core protocol: `USER`, `PASS`,
  `APOP`, `STAT`, `LIST`, `RETR`, `DELE`, `NOOP`, `RSET`, `TOP`, `UIDL`,
  `QUIT`.
- **RFC 2449** (November 1998, Proposed Standard). Adds `CAPA`, the
  capability-discovery command.
- **RFC 2595** (June 1999, Proposed Standard). Adds `STLS`, the
  STARTTLS-equivalent upgrade command.
- **RFC 5034** (July 2007, Proposed Standard). Adds the `AUTH` command
  and SASL framing for POP3.
- **RFC 5721** (February 2010, Experimental). An early UTF-8 proposal.
  It was later obsoleted by RFC 6856.
- **RFC 6186** (March 2011, Proposed Standard). DNS SRV records for
  service discovery. This is a client/DNS concern, not a server
  protocol feature. It updates RFC 1939 only in name.
- **RFC 6856** (March 2013, Proposed Standard). Supersedes RFC 5721.
  Adds the `UTF8` command and a `UTF8` variant of the `USER` capability.

## Practices that never became RFCs

- **OAuth 2.0 / SASL XOAUTH2.** Not an RFC mechanism. Google introduced
  it first; Microsoft added it to Exchange Online in 2020. Since
  October 2022, Exchange Online requires it: basic auth no longer
  works there. The client-credentials OAuth grant does not apply to
  POP3; only delegated, user-context tokens do.
- **Microsoft NTLM over POP3** (`MS-POP3`/`MS-OXPOP3`). A proprietary
  Microsoft mechanism, still usable on-premises but deprecated even
  there.
- **Implicit TLS on port 995.** RFC 2595 expects `STLS` on port 110.
  In practice, Google, Microsoft and Apple all standardized on
  implicit TLS on 995 instead, with no plaintext fallback.
- **App-specific passwords.** A provisioning workaround from Google,
  Apple and Yahoo for clients without OAuth2 support. It is not a
  protocol change. It is just an ordinary password, so it needs no
  special library support.
- **POP3 decline.** Gmail dropped "Check mail from other accounts" in
  early 2026. Microsoft requires modern auth in Exchange Online.
  Tutanota and ProtonMail never implemented POP3 natively (ProtonMail
  offers a local bridge instead).
- **LIST+ draft.** An expired 2011 IETF draft
  (`draft-lehmann-morg-pop3listplus`) that proposed richer `LIST`
  metadata. It never became an RFC and saw no real adoption.

## What this library implements

| Capability | Source | Implemented | Notes |
|---|---|---|---|
| `USER`/`PASS` login | RFC 1939 | Yes | Fallback when no SASL mechanism fits. |
| `APOP` login | RFC 1939 | Yes | Used when the server greeting carries a timestamp. |
| `STAT`, `LIST`, `RETR`, `DELE`, `RSET`, `NOOP`, `QUIT` | RFC 1939 | Yes | Core mailbox operations. |
| `UIDL` | RFC 1939 | Yes | Drives UID-based fetch/store and `getIdsOb()`. |
| `TOP` | RFC 1939 | Yes | Used to fetch headers without a full `RETR`, with a `RETR` fallback. |
| `CAPA` | RFC 2449 | Yes | Queried once per connection, cached, cleared after STARTTLS. |
| `STLS` (STARTTLS) | RFC 2595 | Yes | Used when `SecureMode::Tls` is configured. |
| Implicit TLS on connect | Practice | Yes | Used when `SecureMode::Ssl` is configured (the default port is 995). |
| `AUTH` + SASL framing | RFC 5034 | Yes | Every mechanism below rides on this. |
| SASL PLAIN | RFC 4616 | Yes | |
| SASL LOGIN | De facto | Yes | Legacy fallback, still common. |
| SASL CRAM-MD5 | RFC 2195 | Yes | |
| SASL DIGEST-MD5 | RFC 2831 | Yes | Obsolete, kept for old servers. |
| SASL SCRAM-SHA-1/256/512 (+ `-PLUS`) | RFC 5802, RFC 7677, RFC 5056 | Yes | `-PLUS` variants use TLS channel binding. |
| SASL XOAUTH2 | Google practice | Yes | |
| SASL OAUTHBEARER | RFC 7628 | Yes | |
| SASL EXTERNAL | RFC 4422 §5.7 | Yes | TLS client-certificate auth. |
| SASL ANONYMOUS | RFC 4505 | Yes | |

## What this library does not implement

| Capability | Source | Why not |
|---|---|---|
| `UTF8` command, `UTF8 USER` | RFC 6856 | Almost no server advertises it. Add it if a target server needs it. |
| SRV-based autodiscovery | RFC 6186 | A DNS/client concern, not a wire-protocol capability. The caller resolves the host before constructing `ConnectionConfig`. |
| `LIST+` | Expired draft | Never standardized, no real server support. |
| NTLM (`MS-POP3`) | Microsoft proprietary | Proprietary and Microsoft-only. Deprecated even inside Exchange. |

App-specific passwords need no library support. They are ordinary
passwords from the client's point of view. `PasswordCredentials`
already covers them.

## Server compatibility matrix

Columns show whether each server product is likely to accept the
capability. "Plugin" means the mechanism needs extra, non-default
server software. "No evidence" means public documentation does not
confirm support either way.

| Capability | Dovecot | Cyrus IMAP | Exchange Online | Gmail | Stalwart | Courier-IMAP |
|---|---|---|---|---|---|---|
| `USER`/`PASS` | Yes | Yes | No (disabled Oct 2022) | No (disabled) | Yes | Yes |
| `APOP` | Yes | Yes | No | No | Yes | Yes |
| `CAPA` | Yes | Yes | Yes | Yes | Yes | Yes |
| `STLS` on port 110 | Yes | Yes | No (995 only) | No (995 only) | Yes | Yes |
| Implicit TLS on 995 | Yes | Yes | Yes | Yes | Yes | Yes |
| `TOP` | Yes | Yes | Yes | Yes | Yes | Yes |
| `UIDL` | Yes | Yes | Yes | Yes | Yes | Yes |
| SASL PLAIN | Yes | Yes | Yes | Yes | Yes | Yes |
| SASL LOGIN | Yes | Yes | Yes | Yes | Yes | Yes |
| SASL CRAM-MD5 | Yes | Yes | No | No | Yes | Yes |
| SASL DIGEST-MD5 | Yes | Yes | No | No | No evidence | Yes |
| SASL SCRAM-SHA-\* | Yes (2.3+) | Yes (recent) | No | No | Yes | No |
| SASL XOAUTH2 | Plugin | Plugin | Yes | Yes | Yes | No |
| SASL OAUTHBEARER | Plugin | Plugin | No evidence | No evidence | Yes | No |
| SASL EXTERNAL | Yes | Yes | No | No | Yes | No evidence |
| SASL ANONYMOUS | If configured | If configured | No | No | If configured | No |
| `UTF8` (RFC 6856) | No | No | No | No | Yes | No |

## Bottom line

Only one post-2010 feature has broad, real-world server support:
OAuth2/XOAUTH2. Even there, Dovecot and Cyrus need a third-party SASL
plugin; only Exchange Online, Gmail and Stalwart support it natively.
RFC 6856 (UTF-8) has barely spread past Stalwart.

Everything this library implements maps onto RFC 1939, RFC 2449,
RFC 2595, RFC 5034, and the mechanisms in `horde/sasl`. That already
covers every server in the matrix above, at least for one working
authentication path each.
