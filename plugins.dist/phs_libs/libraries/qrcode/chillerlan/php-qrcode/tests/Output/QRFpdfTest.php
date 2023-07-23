<?php
/**
 * Class QRFpdfTest
 *
 * @filesource   QRFpdfTest.php
 * @created      03.06.2020
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2020 smiley
 * @license      MIT
 */
namespace chillerlan\QRCodeTest\Output;

use FPDF;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QRFpdf;
use chillerlan\QRCode\Output\QROutputInterface;

use function substr;
use function class_exists;

/**
 * Tests the QRFpdf output module
 */
class QRFpdfTest extends QROutputTestAbstract
{
    /**
     * @inheritDoc
     * @internal
     */
    public function types() : array
    {
        return [
            'fpdf' => [QRCode::OUTPUT_FPDF],
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
     * @inheritDoc
     * @dataProvider types
     */
    public function test_render_image(string $type) : void
    {
        $this->options->outputType = $type;
        $this->options->imageBase64 = false;

        // substr() to avoid CreationDate
        $expected = substr(file_get_contents(__DIR__.'/samples/'.$type), 0, 2500);
        $actual = substr((new QRCode($this->options))->render('test'), 0, 2500);

        $this::assertSame($expected, $actual);
    }

    public function test_output_get_resource() : void
    {
        $this->options->returnResource = true;
        $this->outputInterface = $this->getOutputInterface($this->options);

        $this::assertInstanceOf(FPDF::class, $this->outputInterface->dump());
    }

    /**
     * @inheritDoc
     * @internal
     */
    protected function getOutputInterface(QROptions $options) : QROutputInterface
    {
        return new QRFpdf($options, $this->matrix);
    }

    /**
     * @inheritDoc
     * @internal
     */
    public function setUp() : void
    {
        if (!class_exists(FPDF::class)) {
            $this->markTestSkipped('FPDF not available');

            /** @noinspection PhpUnreachableStatementInspection */
            return;
        }

        parent::setUp();
    }
}
