<?php
/**
 * Class QRMarkupTest
 *
 * @filesource   QRMarkupTest.php
 * @created      24.12.2017
 * @author       Smiley <smiley@chillerlan.net>
 * @copyright    2017 Smiley
 * @license      MIT
 */
namespace chillerlan\QRCodeTest\Output;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QRMarkup;
use chillerlan\QRCode\Output\QROutputInterface;

/**
 * Tests the QRMarkup output module
 */
class QRMarkupTest extends QROutputTestAbstract
{
    /**
     * @inheritDoc
     * @internal
     */
    public function types() : array
    {
        return [
            'html' => [QRCode::OUTPUT_MARKUP_HTML],
            'svg'  => [QRCode::OUTPUT_MARKUP_SVG],
        ];
    }

    /**
     * @inheritDoc
     */
    public function test_set_module_values() : void
    {
        $this->options->imageBase64 = false;
        $this->options->moduleValues = [
            // data
            1024 => '#4A6000',
            4    => '#ECF9BE',
        ];

        $this->outputInterface = $this->getOutputInterface($this->options);
        $data = $this->outputInterface->dump();
        $this::assertStringContainsString('#4A6000', $data);
        $this::assertStringContainsString('#ECF9BE', $data);
    }

    /**
     * @inheritDoc
     * @internal
     */
    protected function getOutputInterface(QROptions $options) : QROutputInterface
    {
        return new QRMarkup($options, $this->matrix);
    }
}
