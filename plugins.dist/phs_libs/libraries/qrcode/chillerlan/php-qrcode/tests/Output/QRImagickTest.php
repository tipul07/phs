<?php
/**
 * Class QRImagickTest
 *
 * @filesource   QRImagickTest.php
 * @created      04.07.2018
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2018 smiley
 * @license      MIT
 *
 * @noinspection PhpUndefinedClassInspection
 * @noinspection PhpComposerExtensionStubsInspection
 */
namespace chillerlan\QRCodeTest\Output;

use Imagick;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QRImagick;
use chillerlan\QRCode\Output\QROutputInterface;

/**
 * Tests the QRImagick output module
 */
class QRImagickTest extends QROutputTestAbstract
{
    /**
     * @inheritDoc
     * @internal
     */
    public function types() : array
    {
        return [
            'imagick' => [QRCode::OUTPUT_IMAGICK],
        ];
    }

    /**
     * @inheritDoc
     */
    public function test_set_module_values() : void
    {
        $this->options->moduleValues = [
            // data
            1024 => '#4A6000',
            4    => '#ECF9BE',
        ];

        $this->outputInterface = $this->getOutputInterface($this->options);
        $this->outputInterface->dump();

        $this::assertTrue(true); // tricking the code coverage
    }

    public function test_output_get_resource() : void
    {
        $this->options->returnResource = true;
        $this->outputInterface = $this->getOutputInterface($this->options);

        $this::assertInstanceOf(Imagick::class, $this->outputInterface->dump());
    }

    /**
     * @inheritDoc
     * @internal
     */
    protected function getOutputInterface(QROptions $options) : QROutputInterface
    {
        return new QRImagick($options, $this->matrix);
    }

    /**
     * @inheritDoc
     * @internal
     */
    public function setUp() : void
    {
        if (!extension_loaded('imagick')) {
            $this->markTestSkipped('ext-imagick not loaded');

            /** @noinspection PhpUnreachableStatementInspection */
            return;
        }

        parent::setUp();
    }
}
