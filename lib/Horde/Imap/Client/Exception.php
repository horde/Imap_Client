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
 * Exception handler for the Horde_Imap_Client package.
 *
 * Additional server debug information MAY be found in the $details
 * property.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2008-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Imap_Client
 */
class Horde_Imap_Client_Exception extends Horde_Exception_Wrapped
{
    /* Error message codes. */

    /**
     * Unspecified error (DEFAULT).
     */
    public const UNSPECIFIED = 0;

    /**
     * There was an unrecoverable error in UTF7IMAP -> UTF8 conversion.
     */
    public const UTF7IMAP_CONVERSION = 3;

    /**
     * The server ended the connection.
     */
    public const DISCONNECT = 4;

    /**
     * The charset used in the search query is not supported on the
     * server. */
    public const BADCHARSET = 5;

    /**
     * There were errors parsing the MIME/RFC 2822 header of the part.
     */
    public const PARSEERROR = 6;

    /**
     * The server could not decode the MIME part (see RFC 3516).
     */
    public const UNKNOWNCTE = 7;

    /**
     * The comparator specified by setComparator() was not recognized by the
     * IMAP server
     */
    public const BADCOMPARATOR = 9;

    /**
     * RFC 7162 [3.1.2.2] - All mailboxes are not required to support
     * mod-sequences.
     */
    public const MBOXNOMODSEQ = 10;

    /**
     * Thrown if server denies the network connection.
     */
    public const SERVER_CONNECT = 11;

    /**
     * Thrown if read error for server response.
     */
    public const SERVER_READERROR = 12;

    /**
     * Thrown if read timeout occurs.
     */
    public const SERVER_READTIMEOUT = 28;

    /**
     * Thrown if write error in server interaction.
     */
    public const SERVER_WRITEERROR = 16;

    /**
     * Thrown on CATENATE if the URL is invalid.
     */
    public const CATENATE_BADURL = 13;

    /**
     * Thrown on CATENATE if the message was too big.
     */
    public const CATENATE_TOOBIG = 14;

    /**
     * Thrown on CREATE if special-use attribute is not supported.
     */
    public const USEATTR = 15;

    /**
     * The user did not have permissions to carry out the operation.
     */
    public const NOPERM = 17;

    /**
     * The operation was not successful because another user is holding
     * a necessary resource. The operation may succeed if attempted later.
     */
    public const INUSE = 18;

    /**
     * The operation failed because data on the server was corrupt.
     */
    public const CORRUPTION = 19;

    /**
     * The operation failed because it exceeded some limit on the server.
     */
    public const LIMIT = 20;

    /**
     * The operation failed because the user is over their quota.
     */
    public const OVERQUOTA = 21;

    /**
     * The operation failed because the requested creation object already
     * exists.
     */
    public const ALREADYEXISTS = 22;

    /**
     * The operation failed because the requested deletion object did not
     * exist.
     */
    public const NONEXISTENT = 23;

    /**
     * Setting metadata failed because the size of its value is too large.
     * The maximum octet count the server is willing to accept will be
     * in the exception message string.
     */
    public const METADATA_MAXSIZE = 24;

    /**
     * Setting metadata failed because the maximum number of allowed
     * annotations has already been reached.
     */
    public const METADATA_TOOMANY = 25;

    /**
     * Setting metadata failed because the server does not support private
     * annotations on one of the specified mailboxes.
     */
    public const METADATA_NOPRIVATE = 26;

    /**
     * Invalid metadata entry.
     */
    public const METADATA_INVALID = 27;


    // Login failures

    /**
     * Could not start mandatory TLS connection.
     */
    public const LOGIN_TLSFAILURE = 100;

    /**
     * Could not find an available authentication method.
     */
    public const LOGIN_NOAUTHMETHOD = 101;

    /**
     * Generic authentication failure.
     */
    public const LOGIN_AUTHENTICATIONFAILED = 102;

    /**
     * Remote server is unavailable.
     */
    public const LOGIN_UNAVAILABLE = 103;

    /**
     * Authentication succeeded, but authorization failed.
     */
    public const LOGIN_AUTHORIZATIONFAILED = 104;

    /**
     * Authentication is no longer permitted with this passphrase.
     */
    public const LOGIN_EXPIRED = 105;

    /**
     * Login requires privacy.
     */
    public const LOGIN_PRIVACYREQUIRED = 106;

    /**
     * Server verification failed (SCRAM authentication).
     */
    public const LOGIN_SERVER_VERIFICATION_FAILED = 107;


    // Mailbox access failures

    /**
     * Could not open/access mailbox
     */
    public const MAILBOX_NOOPEN = 200;

    /**
     * Could not complete the command because the mailbox is read-only
     */
    public const MAILBOX_READONLY = 201;


    // POP3 specific error codes

    /**
     * Temporary issue. Generally, there is no need to alarm the user for
     * errors of this type.
     */
    public const POP3_TEMP_ERROR = 300;

    /**
     * Permanent error indicated by server.
     */
    public const POP3_PERM_ERROR = 301;


    // Unsupported feature error codes

    /**
     * Function/feature is not supported on this server.
     */
    public const NOT_SUPPORTED = 400;


    /**
     * Raw error message (in English).
     *
     * @since 2.18.0
     *
     * @var string
     */
    public $raw_msg = '';

    /**
     * Constructor.
     *
     * @param string $message  Error message (non-translated).
     * @param int $code        Error code.
     */
    public function __construct($message = '', $code = 0)
    {
        parent::__construct($message, $code);

        $this->raw_msg = $this->message;
        try {
            $this->message = Horde_Imap_Client_Translation::t($this->message);
        } catch (Horde_Translation_Exception $e) {
        }
    }

    /**
     * Allow the error message to be altered.
     *
     * @param string $msg  Error message.
     */
    public function setMessage($msg)
    {
        $this->message = strval($msg);
    }

    /**
     * Allow the error code to be altered.
     *
     * @param integer $code  Error code.
     */
    public function setCode($code)
    {
        $this->code = intval($code);
    }

    /**
     * Perform substitution of variables in the error message.
     *
     * Needed to allow for correct translation of error message.
     *
     * @since 2.22.0
     *
     * @param array $args  Arguments used for substitution.
     */
    public function messagePrintf(array $args = [])
    {
        $this->raw_msg = vsprintf($this->raw_msg, $args);
        $this->message = vsprintf($this->message, $args);
    }

}
