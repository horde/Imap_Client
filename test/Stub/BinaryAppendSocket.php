<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Stub;

use Horde_Imap_Client_Data_Capability_Imap;
use Horde_Imap_Client_Exception;
use Horde_Imap_Client_Ids;
use Horde_Imap_Client_Interaction_Command;
use Horde_Imap_Client_Mailbox;
use Horde_Imap_Client_Socket;

/** Capture APPEND arguments without opening a connection. */
class BinaryAppendSocket extends Horde_Imap_Client_Socket
{
    /** @var list<Horde_Imap_Client_Interaction_Command> */
    public array $commands = [];

    public ?Horde_Imap_Client_Exception $failure = null;

    public int $continuations = 0;

    public function withCapabilities(array $capabilities): void
    {
        $this->_init['capability'] = new Horde_Imap_Client_Data_Capability_Imap();
        foreach ($capabilities as $capability) {
            $this->_init['capability']->add($capability);
        }
    }

    public function appendMessages(array $messages, array $options = [])
    {
        return $this->_append(new Horde_Imap_Client_Mailbox('INBOX'), $messages, $options);
    }

    public function renderCommand(Horde_Imap_Client_Interaction_Command $command): string
    {
        $connection = new class {
            public string $bytes = '';
            public bool $client_debug = true;

            public function write($data, $eol = false): void
            {
                $this->bytes .= $data . ($eol ? "\r\n" : '');
            }

            public function writeLiteral($data, $length, $binary): void
            {
                $this->bytes .= stream_get_contents($data->stream, -1, 0);
            }
        };
        $this->_connection = $connection;
        try {
            if ($this->_processCmd($this->_pipeline($command), $command, $command)) {
                $connection->write('', true);
            }
            return $connection->bytes;
        } finally {
            $this->_connection = null;
        }
    }

    protected function _processCmdContinuation($pipeline, $noexception = false)
    {
        ++$this->continuations;
    }

    protected function _sendCmd($cmd)
    {
        $this->commands[] = $cmd;
        if ($this->failure !== null) {
            throw $this->failure;
        }
        $pipeline = $this->_pipeline($cmd);
        $pipeline->data['appenduid'] = new Horde_Imap_Client_Ids([count($this->commands)]);
        return $pipeline;
    }
}
