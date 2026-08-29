<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright  2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Stub;

use Horde_Imap_Client_Base;
use Horde_Imap_Client_Fetch_Results;
use Horde_Imap_Client_Ids;
use Horde_Imap_Client_Mailbox;

/**
 * Stub for testing Horde_Imap_Client_Base concrete logic.
 * All abstract methods are no-ops.
 *
 * @author     Horde LLC
 * @copyright  2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class Base extends Horde_Imap_Client_Base
{
    protected $_defaultPorts = [143, 993];

    public function setInit($key = null, $val = null): void
    {
        $this->_setInit($key, $val);
    }

    public function initCache($current = false): bool
    {
        return $this->_initCache($current);
    }

    /**
     * Expose the protected message deletion cache handler for testing.
     */
    public function deleteMsgs(
        Horde_Imap_Client_Mailbox $mailbox,
        Horde_Imap_Client_Ids $ids,
        array $opts = []
    ): Horde_Imap_Client_Ids {
        return $this->_deleteMsgs($mailbox, $ids, $opts);
    }

    protected function _initCapability() {}

    protected function _noop() {}

    protected function _getNamespaces()
    {
        return [];
    }

    protected function _connect() {}

    protected function _login()
    {
        return true;
    }

    protected function _logout() {}

    protected function _sendID($info) {}

    protected function _getID()
    {
        return [];
    }

    protected function _setLanguage($langs)
    {
        return [];
    }

    protected function _getLanguage($list)
    {
        return [];
    }

    protected function _openMailbox(
        Horde_Imap_Client_Mailbox $mailbox,
        $mode
    ) {}

    protected function _createMailbox(
        Horde_Imap_Client_Mailbox $mailbox,
        $opts
    ) {}

    protected function _deleteMailbox(Horde_Imap_Client_Mailbox $mailbox) {}

    protected function _renameMailbox(
        Horde_Imap_Client_Mailbox $old,
        Horde_Imap_Client_Mailbox $new
    ) {}

    protected function _subscribeMailbox(
        Horde_Imap_Client_Mailbox $mailbox,
        $subscribe
    ) {}

    protected function _listMailboxes($pattern, $mode, $options)
    {
        return [];
    }

    protected function _status($mboxes, $flags)
    {
        return [];
    }

    protected function _append(
        Horde_Imap_Client_Mailbox $mailbox,
        $data,
        $options
    ) {
        return new Horde_Imap_Client_Ids();
    }

    protected function _check() {}

    protected function _close($options) {}

    protected function _expunge($options) {}

    protected function _search($query, $options)
    {
        return [];
    }

    protected function _setComparator($comparator) {}

    protected function _getComparator()
    {
        return [];
    }

    protected function _thread($options) {}

    protected function _fetch(
        Horde_Imap_Client_Fetch_Results $results,
        $queries
    ) {}

    protected function _vanished(
        $modseq,
        Horde_Imap_Client_Ids $ids
    ) {}

    protected function _store($options)
    {
        return [];
    }

    protected function _copy(
        Horde_Imap_Client_Mailbox $dest,
        $options
    ) {
        return new Horde_Imap_Client_Ids();
    }

    protected function _setQuota(
        Horde_Imap_Client_Mailbox $root,
        $resources
    ) {}

    protected function _getQuota(Horde_Imap_Client_Mailbox $root)
    {
        return [];
    }

    protected function _getQuotaRoot(Horde_Imap_Client_Mailbox $mailbox)
    {
        return [];
    }

    protected function _getACL(Horde_Imap_Client_Mailbox $mailbox)
    {
        return [];
    }

    protected function _setACL(
        Horde_Imap_Client_Mailbox $mailbox,
        $identifier,
        $options
    ) {}

    protected function _deleteACL(
        Horde_Imap_Client_Mailbox $mailbox,
        $identifier
    ) {}

    protected function _listACLRights(
        Horde_Imap_Client_Mailbox $mailbox,
        $identifier
    ) {}

    protected function _getMyACLRights(Horde_Imap_Client_Mailbox $mailbox) {}

    protected function _getMetadata(
        Horde_Imap_Client_Mailbox $mailbox,
        $entries,
        $options
    ) {
        return [];
    }

    protected function _setMetadata(
        Horde_Imap_Client_Mailbox $mailbox,
        $data
    ) {}
}
