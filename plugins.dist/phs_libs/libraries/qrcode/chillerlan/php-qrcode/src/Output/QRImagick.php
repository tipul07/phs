<?php
/**
 * Class QRImagick
 *
 * @filesource   QRImagick.php
 * @created      04.07.2018
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2018 smiley
 * @license      MIT
 *
 * @noinspection PhpComposerExtensionStubsInspection
 */
namespace chillerlan\QRCode\Output;

use Imagick;
use ImagickDraw;
use ImagickPixel;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\QRCodeException;
use chillerlan\Settings\SettingsContainerInterface;

use function is_string;
use function extension_loaded;

/**
 * ImageMagick output module (requires ext-imagick)
 *
 * @see http://php.net/manual/book.imagick.php
 * @see http://phpimagick.com
 */
class QRImagick extends QROutputAbstract
{
    protected Imagick $imagick;

    /**
     * @inheritDoc
     */
    public function __construct(SettingsContainerInterface $options, QRMatrix $matrix)
    {
        if (!extension_loaded('imagick')) {
            throw new QRCodeException('ext-imagick not loaded'); // @codeCoverageIgnore
        }

        parent::__construct($options, $matrix);
    }

    /**
     * @inheritDoc
     *
     * @return string|\Imagick
     */
    public function dump(?string $file = null)
    {
        $file ??= $this->options->cachefile;
        $this->imagick = new Imagick;

        $this->imagick->newImage(
            $this->length,
            $this->length,
            new ImagickPixel($this->options->imagickBG ?? 'transparent'),
            $this->options->imagickFormat
        );

        $this->drawImage();

        if ($this->options->returnResource) {
            return $this->imagick;
        }

        $imageData = $this->imagick->getImageBlob();

        if ($file !== null) {
            $this->saveToFile($imageData, $file);
        }

        return $imageData;
    }

    /**
     * @inheritDoc
     */
    protected function setModuleValues() : void
    {
        foreach ($this::DEFAULT_MODULE_VALUES as $type => $defaultValue) {
            $v = $this->options->moduleValues[$type] ?? null;

            if (!is_string($v)) {
                $this->moduleValues[$type] = $defaultValue
                    ? new ImagickPixel($this->options->markupDark)
                    : new ImagickPixel($this->options->markupLight);
            } else {
                $this->moduleValues[$type] = new ImagickPixel($v);
            }
        }
    }

    /**
     * Creates the QR image via ImagickDraw
     */
    protected function drawImage() : void
    {
        $draw = new ImagickDraw;

        foreach ($this->matrix->matrix() as $y => $row) {
            foreach ($row as $x => $M_TYPE) {
                $draw->setStrokeColor($this->moduleValues[$M_TYPE]);
                $draw->setFillColor($this->moduleValues[$M_TYPE]);
                $draw->rectangle(
                    $x * $this->scale,
                    $y * $this->scale,
                    ($x + 1) * $this->scale,
                    ($y + 1) * $this->scale
                );
            }
        }

        $this->imagick->drawImage($draw);
    }
}
