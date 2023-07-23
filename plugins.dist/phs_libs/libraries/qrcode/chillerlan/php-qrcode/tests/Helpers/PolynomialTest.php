<?php
/**
 * Class PolynomialTest
 *
 * @filesource   PolynomialTest.php
 * @created      09.02.2016
 * @author       Smiley <smiley@chillerlan.net>
 * @copyright    2015 Smiley
 * @license      MIT
 */
namespace chillerlan\QRCodeTest\Helpers;

use PHPUnit\Framework\TestCase;
use chillerlan\QRCode\QRCodeException;
use chillerlan\QRCode\Helpers\Polynomial;

/**
 * Polynomial coverage test
 */
final class PolynomialTest extends TestCase
{
    protected Polynomial $polynomial;

    public function test_gexp() : void
    {
        $this::assertSame(142, $this->polynomial->gexp(-1));
        $this::assertSame(133, $this->polynomial->gexp(128));
        $this::assertSame(2, $this->polynomial->gexp(256));
    }

    public function test_glog_exception() : void
    {
        $this->expectException(QRCodeException::class);
        $this->expectExceptionMessage('log(0)');

        $this->polynomial->glog(0);
    }

    protected function setUp() : void
    {
        $this->polynomial = new Polynomial;
    }
}
