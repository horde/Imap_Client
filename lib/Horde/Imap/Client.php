<?php

/**
 * Copyright 2008-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2008-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Imap_Client
 */

/**
 * Base class for Horde_Imap_Client package. Defines common constants for use
 * in the package.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2008-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Imap_Client
 */
class Horde_Imap_Client
{
    /* Constants for openMailbox() */
    public const OPEN_READONLY = 1;
    public const OPEN_READWRITE = 2;
    public const OPEN_AUTO = 3;

    /* Constants for listMailboxes() */
    public const MBOX_SUBSCRIBED = 1;
    public const MBOX_SUBSCRIBED_EXISTS = 2;
    public const MBOX_UNSUBSCRIBED = 3;
    public const MBOX_ALL = 4;
    /* @since 2.23.0 */
    public const MBOX_ALL_SUBSCRIBED = 5;

    /* Constants for status() */
    public const STATUS_MESSAGES = 1;
    public const STATUS_RECENT = 2;
    public const STATUS_UIDNEXT = 4;
    public const STATUS_UIDVALIDITY = 8;
    public const STATUS_UNSEEN = 16;
    public const STATUS_ALL = 32;
    public const STATUS_FIRSTUNSEEN = 64;
    public const STATUS_FLAGS = 128;
    public const STATUS_PERMFLAGS = 256;
    public const STATUS_HIGHESTMODSEQ = 512;
    public const STATUS_SYNCMODSEQ = 1024;
    public const STATUS_SYNCFLAGUIDS = 2048;
    public const STATUS_UIDNOTSTICKY = 4096;
    public const STATUS_UIDNEXT_FORCE = 8192;
    public const STATUS_SYNCVANISHED = 16384;
    /* @since 2.12.0 */
    public const STATUS_RECENT_TOTAL = 32768;
    /* @since 2.14.0 */
    public const STATUS_FORCE_REFRESH = 65536;

    /* Constants for search() */
    public const SORT_ARRIVAL = 1;
    public const SORT_CC = 2;
    public const SORT_DATE = 3;
    public const SORT_FROM = 4;
    public const SORT_REVERSE = 5;
    public const SORT_SIZE = 6;
    public const SORT_SUBJECT = 7;
    public const SORT_TO = 8;
    /* SORT_THREAD provided for completeness - it is not a valid sort criteria
     * for search() (use thread() instead). */
    public const SORT_THREAD = 9;
    /* Sort criteria defined in RFC 5957 */
    public const SORT_DISPLAYFROM = 10;
    public const SORT_DISPLAYTO = 11;
    /* SORT_SEQUENCE does a simple numerical sort on the returned
     * UIDs/sequence numbers. */
    public const SORT_SEQUENCE = 12;
    /* Fuzzy sort criteria defined in RFC 6203 */
    public const SORT_RELEVANCY = 13;
    /* @since 2.4.0 */
    public const SORT_DISPLAYFROM_FALLBACK = 14;
    /* @since 2.4.0 */
    public const SORT_DISPLAYTO_FALLBACK = 15;

    /* Search results constants */
    public const SEARCH_RESULTS_COUNT = 1;
    public const SEARCH_RESULTS_MATCH = 2;
    public const SEARCH_RESULTS_MAX = 3;
    public const SEARCH_RESULTS_MIN = 4;
    public const SEARCH_RESULTS_SAVE = 5;
    /* Fuzzy sort criteria defined in RFC 6203 */
    public const SEARCH_RESULTS_RELEVANCY = 6;

    /* Constants for thread() */
    public const THREAD_ORDEREDSUBJECT = 1;
    public const THREAD_REFERENCES = 2;
    public const THREAD_REFS = 3;

    /* Fetch criteria constants. */
    public const FETCH_STRUCTURE = 1;
    public const FETCH_FULLMSG = 2;
    public const FETCH_HEADERTEXT = 3;
    public const FETCH_BODYTEXT = 4;
    public const FETCH_MIMEHEADER = 5;
    public const FETCH_BODYPART = 6;
    public const FETCH_BODYPARTSIZE = 7;
    public const FETCH_HEADERS = 8;
    public const FETCH_ENVELOPE = 9;
    public const FETCH_FLAGS = 10;
    public const FETCH_IMAPDATE = 11;
    public const FETCH_SIZE = 12;
    public const FETCH_UID = 13;
    public const FETCH_SEQ = 14;
    public const FETCH_MODSEQ = 15;
    /* @since 2.11.0 */
    public const FETCH_DOWNGRADED = 16;

    public const FETCH_FLAGS_ORIGINAL_CASE = 999;

    /* Namespace constants. @deprecated */
    public const NS_PERSONAL = 1;
    public const NS_OTHER = 2;
    public const NS_SHARED = 3;

    /* ACL constants (RFC 4314 [2.1]). */
    public const ACL_LOOKUP = 'l';
    public const ACL_READ = 'r';
    public const ACL_SEEN = 's';
    public const ACL_WRITE = 'w';
    public const ACL_INSERT = 'i';
    public const ACL_POST = 'p';
    public const ACL_CREATEMBOX = 'k';
    public const ACL_DELETEMBOX = 'x';
    public const ACL_DELETEMSGS = 't';
    public const ACL_EXPUNGE = 'e';
    public const ACL_ADMINISTER = 'a';
    // Old constants (RFC 2086 [3]; RFC 4314 [2.1.1])
    public const ACL_CREATE = 'c';
    public const ACL_DELETE = 'd';

    /* System flags. */
    // RFC 3501 [2.3.2]
    public const FLAG_ANSWERED = '\\answered';
    public const FLAG_DELETED = '\\deleted';
    public const FLAG_DRAFT = '\\draft';
    public const FLAG_FLAGGED = '\\flagged';
    public const FLAG_RECENT = '\\recent';
    public const FLAG_SEEN = '\\seen';
    // RFC 3503 [3.3]
    public const FLAG_MDNSENT = '$mdnsent';
    // RFC 5550 [2.8]
    public const FLAG_FORWARDED = '$forwarded';
    // RFC 5788 registered keywords:
    // http://www.ietf.org/mail-archive/web/morg/current/msg00441.html
    public const FLAG_JUNK = '$junk';
    public const FLAG_NOTJUNK = '$notjunk';

    /* Special-use mailbox attributes (RFC 6154 [2]). */
    public const SPECIALUSE_ALL = '\\All';
    public const SPECIALUSE_ARCHIVE = '\\Archive';
    public const SPECIALUSE_DRAFTS = '\\Drafts';
    public const SPECIALUSE_FLAGGED = '\\Flagged';
    public const SPECIALUSE_JUNK = '\\Junk';
    public const SPECIALUSE_SENT = '\\Sent';
    public const SPECIALUSE_TRASH = '\\Trash';

    /* Constants for sync(). */
    public const SYNC_UIDVALIDITY = 0;
    public const SYNC_FLAGS = 1;
    public const SYNC_FLAGSUIDS = 2;
    public const SYNC_NEWMSGS = 4;
    public const SYNC_NEWMSGSUIDS = 8;
    public const SYNC_VANISHED = 16;
    public const SYNC_VANISHEDUIDS = 32;
    public const SYNC_ALL = 64;

    /**
     * Capability dependencies.
     *
     * @deprecated
     *
     * @var array
     */
    public static $capability_deps = [
        // RFC 7162 [3.2]
        'QRESYNC' => [
            // QRESYNC requires CONDSTORE, but the latter is implied and is
            // not required to be listed.
            'ENABLE',
        ],
        // RFC 5182 [2.1]
        'SEARCHRES' => [
            'ESEARCH',
        ],
        // RFC 5255 [3.1]
        'LANGUAGE' => [
            'NAMESPACE',
        ],
        // RFC 5957 [1]
        'SORT=DISPLAY' => [
            'SORT',
        ],
    ];

}
