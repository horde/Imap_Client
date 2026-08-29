<?php

declare(strict_types=1);

/**
 * Copyright 2011-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Stub;

use Horde_Imap_Client_Data_Capability_Imap;
use Horde_Imap_Client_Data_Thread;
use Horde_Imap_Client_Ids;
use Horde_Imap_Client_Interaction_Pipeline;
use Horde_Imap_Client_Interaction_Server;
use Horde_Imap_Client_Socket;
use Horde_Imap_Client_Tokenize;

/**
 * Stub for testing the IMAP Socket library.
 * Needed because we need to access protected methods.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2011-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class Socket extends Horde_Imap_Client_Socket
{
    public $fetch_results;

    private bool $captureSendCmd = false;

    public ?Horde_Imap_Client_Interaction_Pipeline $capturedPipeline = null;

    protected function _sendCmd($cmd)
    {
        if ($this->captureSendCmd) {
            $this->capturedPipeline = $cmd;
            return $cmd;
        }
        return parent::_sendCmd($cmd);
    }

    public function getThreadSort($data)
    {
        return new Horde_Imap_Client_Data_Thread($this->doServerResponse($this->_pipeline(), $data)->data['threadparse'], 'uid');
    }

    public function parseNamespace($data)
    {
        return $this->doServerResponse($this->_pipeline(), $data)->data['namespace'];
    }

    public function parseACL($data)
    {
        return $this->doServerResponse($this->_pipeline(), $data)->data['getacl'];
    }

    public function parseMyACLRights($data)
    {
        return $this->doServerResponse($this->_pipeline(), $data)->data['myrights'];
    }

    public function parseListRights($data)
    {
        return $this->doServerResponse($this->_pipeline(), $data)->data['listaclrights'];
    }

    /**
     * @param array $data  Options:
     *   - results: (Horde_Imap_Client_Fetch_Results)
     */
    public function parseFetch($data, array $opts = [])
    {
        $pipeline = $this->_pipeline();
        if (isset($opts['results'])) {
            $pipeline->fetch = $opts['results'];
        }
        $pipeline->data['modseqs_nouid'] = [];

        return $this->doServerResponse($pipeline, $data);
    }

    public function doServerResponse($pipeline, $data)
    {
        $server = Horde_Imap_Client_Interaction_Server::create(
            new Horde_Imap_Client_Tokenize($data)
        );
        $this->_serverResponse($pipeline, $server);
        return $pipeline;
    }

    public function doResponseCode($data)
    {
        $server = Horde_Imap_Client_Interaction_Server::create(
            new Horde_Imap_Client_Tokenize($data)
        );
        $this->_responseCode($this->_pipeline(), $server);
    }

    public function pipeline($cmd = null)
    {
        return $this->_pipeline($cmd);
    }

    public function fetch($mailbox, $query, array $options = [])
    {
        return $this->fetch_results;
    }

    public function doVanishedPipeline(int $modseq, Horde_Imap_Client_Ids $ids): Horde_Imap_Client_Interaction_Pipeline
    {
        $this->captureSendCmd = true;
        $this->capturedPipeline = null;
        $this->_init['capability'] = new Horde_Imap_Client_Data_Capability_Imap();
        $this->_vanished($modseq, $ids);
        return $this->capturedPipeline;
    }
}
