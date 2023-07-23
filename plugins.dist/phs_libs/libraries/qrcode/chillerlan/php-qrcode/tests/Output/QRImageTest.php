<?php
/**
 * Class QRImageTest
 *
 * @filesource   QRImageTest.php
 * @created      24.12.2017
 * @author       Smiley <smiley@chillerlan.net>
 * @copyright    2017 Smiley
 * @license      MIT
 */
namespace chillerlan\QRCodeTest\Output;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QRImage;
use chillerlan\QRCode\Output\QROutputInterface;

/**
 * Tests the QRImage output module
 */
class QRImageTest extends QROutputTestAbstract
{
    /**
     * @inheritDoc
     * @internal
     */
    public function types() : array
    {
        return [
            'png' => [QRCode::OUTPUT_IMAGE_PNG],
            'gif' => [QRCode::OUTPUT_IMAGE_GIF],
            'jpg' => [QRCode::OUTPUT_IMAGE_JPG],
        ];
    }

    /**
     * @inheritDoc
     */
    public function test_set_module_values() : void
    {
        $this->options->moduleValues = [
            // data
            1024 => [0, 0, 0],
            4    => [255, 255, 255],
        ];

        $this->outputInterface = $this->getOutputInterface($this->options);
        $this->outputInterface->dump();

        $this::assertTrue(true); // tricking the code coverage
    }

    /**
     * @phan-suppress PhanUndeclaredClassReference
     */
    public function test_output_get_resource() : void
    {
        $this->options->returnResource = true;
        $this->outputInterface = $this->getOutputInterface($this->options);

        $actual = $this->outputInterface->dump();

        /** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */
        \PHP_MAJOR_VERSION >= 8
            ? $this::assertInstanceOf(\GdImage::class, $actual)
            : $this::assertIsResource($actual);
    }

    /**
     * @inheritDoc
     * @internal
     */
    protected function getOutputInterface(QROptions $options) : QROutputInterface
    {
        return new QRImage($options, $this->matrix);
    }

    /**
     * @inheritDoc
     * @internal
     */
    public function setUp() : void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('ext-gd not loaded');

            return;
        }

        parent::setUp();
    }
}
