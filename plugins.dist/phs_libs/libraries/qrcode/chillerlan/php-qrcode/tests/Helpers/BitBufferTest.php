<?php
/**
 * Class BitBufferTest
 *
 * @filesource   BitBufferTest.php
 * @created      08.02.2016
 * @author       Smiley <smiley@chillerlan.net>
 * @copyright    2015 Smiley
 * @license      MIT
 */
namespace chillerlan\QRCodeTest\Helpers;

use chillerlan\QRCode\QRCode;
use PHPUnit\Framework\TestCase;
use chillerlan\QRCode\Helpers\BitBuffer;

/**
 * BitBuffer coverage test
 */
final class BitBufferTest extends TestCase
{
    protected BitBuffer $bitBuffer;

    public function bitProvider() : array
    {
        return [
            'number'   => [QRCode::DATA_NUMBER, 16],
            'alphanum' => [QRCode::DATA_ALPHANUM, 32],
            'byte'     => [QRCode::DATA_BYTE, 64],
            'kanji'    => [QRCode::DATA_KANJI, 128],
        ];
    }

    /**
     * @dataProvider bitProvider
     * @param int $data
     * @param int $value
     */
    public function test_put(int $data, int $value) : void
    {
        $this->bitBuffer->put($data, 4);
        $this::assertSame($value, $this->bitBuffer->getBuffer()[0]);
        $this::assertSame(4, $this->bitBuffer->getLength());
    }

    public function test_clear() : void
    {
        $this->bitBuffer->clear();
        $this::assertSame([], $this->bitBuffer->getBuffer());
        $this::assertSame(0, $this->bitBuffer->getLength());
    }

    protected function setUp() : void
    {
        $this->bitBuffer = new BitBuffer;
    }
}
