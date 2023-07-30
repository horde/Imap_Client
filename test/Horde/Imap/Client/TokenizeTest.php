<?php
/**
 * Copyright 2012-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category   Horde
 * @copyright  2011-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */

/**
 * Tests for the IMAP string tokenizer.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2011-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */
class Horde_Imap_Client_TokenizeTest extends Horde_Test_Case
{
    public function testTokenizeSimple()
    {
        $test1 = 'FOO BAR';
        $token1 = new Horde_Imap_Client_Tokenize($test1);

        $tmp = iterator_to_array($token1);

        $this->assertEquals(
            'FOO',
            $tmp[0]
        );

        $this->assertEquals(
            'BAR',
            $tmp[1]
        );
    }

    public function testTokenizeQuotes()
    {
        $test1 = 'FOO "BAR"';
        $token1 = new Horde_Imap_Client_Tokenize($test1);

        $tmp = iterator_to_array($token1);

        $this->assertEquals(
            'FOO',
            $tmp[0]
        );

        $this->assertEquals(
            'BAR',
            $tmp[1]
        );

        $test2 = '"\"BAR\""';
        $token2 = new Horde_Imap_Client_Tokenize($test2);

        $tmp = iterator_to_array($token2);

        $this->assertEquals(
            '"BAR"',
            $tmp[0]
        );
    }

    public function testTokenizeLiteral()
    {
        $test1 = 'FOO {3}BAR BAZ';
        $token1 = new Horde_Imap_Client_Tokenize($test1);

        $tmp = iterator_to_array($token1);

        $this->assertEquals(
            'FOO',
            $tmp[0]
        );

        $this->assertEquals(
            'BAR',
            $tmp[1]
        );

        $this->assertEquals(
            'BAZ',
            $tmp[2]
        );
    }

    public function testTokenizeNil()
    {
        $test1 = 'FOO NIL';
        $token1 = new Horde_Imap_Client_Tokenize($test1);
        $token1->rewind();

        $this->assertEquals(
            'FOO',
            $token1->next()
        );
        $this->assertNull($token1->next());
        $this->assertFalse($token1->next());
    }

    public function testTokenizeList()
    {
        $test1 = 'FOO ("BAR") BAZ';
        $token1 = new Horde_Imap_Client_Tokenize($test1);
        $token1->rewind();

        $this->assertEquals(
            'FOO',
            $token1->next()
        );

        $this->assertTrue($token1->next());

        $this->assertEquals(
            'BAR',
            $token1->next()
        );

        $this->assertFalse($token1->next());

        $this->assertEquals(
            'BAZ',
            $token1->next()
        );

        $this->assertFalse($token1->next());

        $test2 = '(BAR NIL NIL)';
        $token2 = new Horde_Imap_Client_Tokenize($test2);
        $token2->rewind();

        $this->assertTrue($token2->next());

        $this->assertEquals(
            'BAR',
            $token2->next()
        );

        $this->assertNull($token2->next());
        $this->assertNull($token2->next());
        $this->assertFalse($token2->next());

        $test3 = '(\Foo)';
        $token3 = new Horde_Imap_Client_Tokenize($test3);
        $token3->rewind();

        $this->assertTrue($token3->next());

        $this->assertEquals(
            '\\Foo',
            $token3->next()
        );

        $this->assertFalse($token3->next());
    }

    public function testTokenizeBadWhitespace()
    {
        $test1 = '  FOO  BAR ';
        $token1 = new Horde_Imap_Client_Tokenize($test1);

        $tmp = iterator_to_array($token1);

        $this->assertEquals(
            'FOO',
            $tmp[0]
        );

        $this->assertEquals(
            'BAR',
            $tmp[1]
        );
    }

    public function testTokenizeComplexFetchExample()
    {
        $test = <<<EOT
* 8 FETCH (UID 39210 BODYSTRUCTURE (("text" "plain" ("charset" "ISO-8859-1") NIL NIL "quoted-printable" 1559 40 NIL NIL NIL NIL)("text" "html" ("charset" "ISO-8859-1") NIL NIL {16}quoted-printable 25318 427 NIL NIL NIL NIL) {11}alternative ("boundary" "_Part_1_xMiAxODoyNjozNyAtMDQwMA==") NIL NIL NIL))
EOT;

        $token = new Horde_Imap_Client_Tokenize($test);
        $token->rewind();

        $this->assertEquals(
            '*',
            $token->next()
        );
        $this->assertEquals(
            '8',
            $token->next()
        );
        $this->assertEquals(
            'FETCH',
            $token->next()
        );

        $this->assertTrue($token->next());

        $this->assertEquals(
            'UID',
            $token->next()
        );
        $this->assertEquals(
            '39210',
            $token->next()
        );
        $this->assertEquals(
            'BODYSTRUCTURE',
            $token->next()
        );

        $this->assertTrue($token->next());
        $this->assertTrue($token->next());

        $this->assertEquals(
            'text',
            $token->next()
        );
        $this->assertEquals(
            'plain',
            $token->next()
        );

        $this->assertTrue($token->next());

        $this->assertEquals(
            'charset',
            $token->next()
        );
        $this->assertEquals(
            'ISO-8859-1',
            $token->next()
        );

        $this->assertFalse($token->next());

        $this->assertNull($token->next());
        $this->assertNull($token->next());
        $this->assertEquals(
            'quoted-printable',
            $token->next()
        );
        $this->assertEquals(
            1559,
            $token->next()
        );
        $this->assertEquals(
            40,
            $token->next()
        );
        $this->assertNull($token->next());
        $this->assertNull($token->next());
        $this->assertNull($token->next());
        $this->assertNull($token->next());
        $this->assertFalse($token->next());

        $this->assertTrue($token->next());

        $this->assertEquals(
            'text',
            $token->next()
        );
        $this->assertEquals(
            'html',
            $token->next()
        );

        $this->assertTrue($token->next());

        $this->assertEquals(
            'charset',
            $token->next()
        );
        $this->assertEquals(
            'ISO-8859-1',
            $token->next()
        );

        $this->assertFalse($token->next());

        $this->assertNull($token->next());
        $this->assertNull($token->next());
        $this->assertEquals(
            'quoted-printable',
            $token->next()
        );
        $this->assertEquals(
            25318,
            $token->next()
        );
        $this->assertEquals(
            427,
            $token->next()
        );
        $this->assertNull($token->next());
        $this->assertNull($token->next());
        $this->assertNull($token->next());
        $this->assertNull($token->next());
        $this->assertFalse($token->next());

        $this->assertEquals(
            'alternative',
            $token->next()
        );

        $this->assertTrue($token->next());

        $this->assertEquals(
            'boundary',
            $token->next()
        );
        $this->assertEquals(
            '_Part_1_xMiAxODoyNjozNyAtMDQwMA==',
            $token->next()
        );
        $this->assertFalse($token->next());

        $this->assertNull($token->next());
        $this->assertNull($token->next());
        $this->assertNull($token->next());
        $this->assertFalse($token->next());
        $this->assertFalse($token->next());
        $this->assertFalse($token->next());
    }

    public function testBug11450()
    {
        $test = '* NAMESPACE (("INBOX." ".")) (("user." ".")) (("" "."))';
        $token = new Horde_Imap_Client_Tokenize($test);
        $token->rewind();

        $this->assertEquals(
            '*',
            $token->next()
        );
        $this->assertEquals(
            'NAMESPACE',
            $token->next()
        );

        $this->assertTrue($token->next());
        $this->assertTrue($token->next());

        $this->assertEquals(
            'INBOX.',
            $token->next()
        );
        $this->assertEquals(
            '.',
            $token->next()
        );

        $this->assertFalse($token->next());
        $this->assertFalse($token->next());

        $this->assertTrue($token->next());
        $this->assertTrue($token->next());

        $this->assertEquals(
            'user.',
            $token->next()
        );
        $this->assertEquals(
            '.',
            $token->next()
        );

        $this->assertFalse($token->next());
        $this->assertFalse($token->next());

        $this->assertTrue($token->next());
        $this->assertTrue($token->next());

        $this->assertEquals(
            '',
            $token->next()
        );
        $this->assertEquals(
            '.',
            $token->next()
        );

        $this->assertFalse($token->next());
        $this->assertFalse($token->next());
    }

    public function testFlushIterator()
    {
        $test = 'FOO (BAR (BAZ BAZ2) BAR2) FOO2';
        $token = new Horde_Imap_Client_Tokenize($test);

        $token->rewind();
        $token->next(); // FOO
        $token->next(); // Opening paren

        $this->assertEquals(
            array('BAR', 'BAR2'),
            $token->flushIterator()
        );
        $this->assertEquals(
            'FOO2',
            $token->next()
        );

        $token->rewind();
        $token->next(); // FOO
        $token->next(); // Opening paren

        $this->assertEquals(
            array(),
            $token->flushIterator(false)
        );
        $this->assertEquals(
            'FOO2',
            $token->next()
        );

        $token->rewind();
        $token->next(); // FOO
        $token->next(); // Opening paren

        $this->assertEquals(
            array(),
            $token->flushIterator(false, false)
        );
        $this->assertTrue($token->eos);
    }

    public function testLiteralLength()
    {
        $test = 'FOO';
        $token = new Horde_Imap_Client_Tokenize($test);

        $this->assertNull($token->getLiteralLength());

        $test = 'FOO {100}';
        $token = new Horde_Imap_Client_Tokenize($test);

        $len = $token->getLiteralLength();
        $this->assertNotNull($len);
        $this->assertFalse($len['binary']);
        $this->assertEquals(
            100,
            $len['length']
        );

        $test = 'FOO ~{100}';
        $token = new Horde_Imap_Client_Tokenize($test);

        $len = $token->getLiteralLength();
        $this->assertNotNull($len);
        $this->assertTrue($len['binary']);
        $this->assertEquals(
            100,
            $len['length']
        );

        // Test non-binary-marker tilde.
        $test = '* LIST () "" ~foo';
        $token = new Horde_Imap_Client_Tokenize($test);
        $token->rewind();

        $this->assertEquals(
            '*',
            $token->next()
        );
        $this->assertEquals(
            'LIST',
            $token->next()
        );
        $this->assertTrue($token->next());
        $this->assertFalse($token->next());
        $this->assertEquals(
            '',
            $token->next()
        );
        $this->assertEquals(
            '~foo',
            $token->next()
        );
    }

    public function testLiteralStream()
    {
        $token = new Horde_Imap_Client_Tokenize();
        $token->add('FOO {10}');
        $token->addLiteralStream('1234567890');
        $token->add(' BAR');

        $token->rewind();

        $this->assertEquals(
            'FOO',
            $token->next()
        );

        /* Internal stream is converted to string. */
        $this->assertEquals(
            '1234567890',
            $token->next()
        );

        $this->assertEquals(
            'BAR',
            $token->next()
        );

        $this->assertTrue($token->eos);

        /* Check to see stream is returned if nextStream() is called and
         * a literal is encountered. */
        $token->rewind();

        $this->assertEquals(
            'FOO',
            $token->nextStream()
        );

        $stream = $token->nextStream();
        $this->assertInstanceOf('Horde_Stream_Temp', $stream);
        $this->assertEquals(
            '1234567890',
            strval($stream)
        );

        $this->assertEquals(
            'BAR',
            $token->nextStream()
        );

        $token = new Horde_Imap_Client_Tokenize();
        $token->add('{200}' . str_repeat('Z', 200));
        $token->rewind();

        $this->assertEquals(
            str_repeat('Z', 200),
            $token->next()
        );

        $token->rewind();

        $stream = $token->nextStream();
        $this->assertInstanceOf('Horde_Stream_Temp', $stream);
        $this->assertEquals(str_repeat('Z', 200), strval($stream));
    }

    public function testClone()
    {
        $this->expectException('LogicException');

        $test = 'FOO BAR';
        $token = new Horde_Imap_Client_Tokenize($test);

        clone $token;
    }

    public function testSerialize()
    {
        $this->expectException('LogicException');

        $test = 'FOO BAR';
        $token = new Horde_Imap_Client_Tokenize($test);

        serialize($token);
    }

}
