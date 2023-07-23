<?php
/**
 * Class QROutputAbstract
 *
 * @filesource   QROutputAbstract.php
 * @created      09.12.2015
 * @author       Smiley <smiley@chillerlan.net>
 * @copyright    2015 Smiley
 * @license      MIT
 */
namespace chillerlan\QRCode\Output;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\Settings\SettingsContainerInterface;

use function dirname;
use function sprintf;
use function in_array;
use function is_writable;
use function get_called_class;
use function file_put_contents;
use function call_user_func_array;

/**
 * common output abstract
 */
abstract class QROutputAbstract implements QROutputInterface
{
    /**
     * the current size of the QR matrix
     *
     * @see \chillerlan\QRCode\Data\QRMatrix::size()
     */
    protected int $moduleCount;

    /**
     * the current output mode
     *
     * @see \chillerlan\QRCode\QROptions::$outputType
     */
    protected string $outputMode;

    /** the default output mode of the current output module */
    protected string $defaultMode;

    /**
     * the current scaling for a QR pixel
     *
     * @see \chillerlan\QRCode\QROptions::$scale
     */
    protected int $scale;

    /** the side length of the QR image (modules * scale) */
    protected int $length;

    /** an (optional) array of color values for the several QR matrix parts */
    protected array $moduleValues;

    /** the (filled) data matrix object */
    protected QRMatrix $matrix;

    /** @var \chillerlan\Settings\SettingsContainerInterface|\chillerlan\QRCode\QROptions */
    protected SettingsContainerInterface $options;

    /**
     * QROutputAbstract constructor.
     * @param SettingsContainerInterface $options
     * @param QRMatrix $matrix
     */
    public function __construct(SettingsContainerInterface $options, QRMatrix $matrix)
    {
        $this->options = $options;
        $this->matrix = $matrix;
        $this->moduleCount = $this->matrix->size();
        $this->scale = $this->options->scale;
        $this->length = $this->moduleCount * $this->scale;

        $class = get_called_class();

        if (isset(QRCode::OUTPUT_MODES[$class]) && in_array($this->options->outputType, QRCode::OUTPUT_MODES[$class])) {
            $this->outputMode = $this->options->outputType;
        }

        $this->setModuleValues();
    }

    /**
     * Sets the initial module values (clean-up & defaults)
     */
    abstract protected function setModuleValues() : void;

    /**
     * @inheritDoc
     */
    public function dump(?string $file = null)
    {
        $file ??= $this->options->cachefile;

        // call the built-in output method with the optional file path as parameter
        // to make the called method aware if a cache file was given
        $data = call_user_func_array([$this, $this->outputMode ?? $this->defaultMode], [$file]);

        if ($file !== null) {
            $this->saveToFile($data, $file);
        }

        return $data;
    }

    /**
     * saves the qr data to a file
     *
     * @see file_put_contents()
     * @see \chillerlan\QRCode\QROptions::cachefile
     *
     * @param string $data
     * @param string $file
     * @throws \chillerlan\QRCode\Output\QRCodeOutputException
     */
    protected function saveToFile(string $data, string $file) : bool
    {
        if (!is_writable(dirname($file))) {
            throw new QRCodeOutputException(sprintf('Could not write data to cache file: %s', $file));
        }

        return (bool)file_put_contents($file, $data);
    }
}
