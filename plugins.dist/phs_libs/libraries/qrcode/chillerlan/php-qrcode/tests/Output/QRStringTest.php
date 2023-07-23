<?php
/**
 * Class QRStringTest
 *
 * @filesource   QRStringTest.php
 * @created      24.12.2017
 * @author       Smiley <smiley@chillerlan.net>
 * @copyright    2017 Smiley
 * @license      MIT
 */
namespace chillerlan\QRCodeTest\Output;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QRString;
use chillerlan\QRCodeExamples\MyCustomOutput;
use chillerlan\QRCode\Output\QROutputInterface;

/**
 * Tests the QRString output module
 */
class QRStringTest extends QROutputTestAbstract
{
    /**
     * @inheritDoc
     * @internal
     */
    public function types() : array
    {
        return [
            'json' => [QRCode::OUTPUT_STRING_JSON],
            'text' => [QRCode::OUTPUT_STRING_TEXT],
        ];
    }

    /**
     * @inheritDoc
     */
    public function test_set_module_values() : void
    {
        $this->options->moduleValues = [
            // data
            1024 => 'A',
            4    => 'B',
        ];

        $this->outputInterface = $this->getOutputInterface($this->options);
        $data = $this->outputInterface->dump();

        $this::assertStringContainsString('A', $data);
        $this::assertStringContainsString('B', $data);
    }

    /**
     * covers the custom output functionality via an example
     */
    public function test_custom_output() : void
    {
        $this->options->version = 5;
        $this->options->eccLevel = QRCode::ECC_L;
        $this->options->outputType = QRCode::OUTPUT_CUSTOM;
        $this->options->outputInterface = MyCustomOutput::class;

        $this::assertSame(
            file_get_contents(__DIR__.'/samples/custom'),
            (new QRCode($this->options))->render('test')
        );
    }

    /**
     * @inheritDoc
     * @internal
     */
    protected function getOutputInterface(QROptions $options) : QROutputInterface
    {
        return new QRString($options, $this->matrix);
    }
}
