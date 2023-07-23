<?php
/**
 * Class QROutputTestAbstract
 *
 * @filesource   QROutputTestAbstract.php
 * @created      24.12.2017
 * @author       Smiley <smiley@chillerlan.net>
 * @copyright    2017 Smiley
 * @license      MIT
 */
namespace chillerlan\QRCodeTest\Output;

use const PHP_OS_FAMILY;
use const PHP_VERSION_ID;

use chillerlan\QRCode\QRCode;
use PHPUnit\Framework\TestCase;
use chillerlan\QRCode\Data\Byte;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\Output\QRCodeOutputException;

use function mkdir;
use function in_array;
use function file_exists;

/**
 * Test abstract for the several (built-in) output modules,
 * should also be used to test custom output modules
 */
abstract class QROutputTestAbstract extends TestCase
{
    /** @internal  */
    protected string $builddir = __DIR__.'/../../.build/output_test';

    /** @internal  */
    protected QROutputInterface $outputInterface;

    /** @internal  */
    protected QROptions $options;

    /** @internal  */
    protected QRMatrix $matrix;

    /**
     * covers the module values settings
     */
    abstract public function test_set_module_values() : void;

    /*
     * additional, non-essential, potentially inaccurate coverage tests
     */

    /**
     * @see testStringOutput()
     * @return string[][]
     * @internal
     */
    abstract public function types() : array;

    /**
     * Returns a QROutputInterface instance with the given options and using $this->matrix
     *
     * @internal
     * @param QROptions $options
     */
    abstract protected function getOutputInterface(QROptions $options) : QROutputInterface;

    /**
     * Validate the instance of the interface
     */
    public function test_instance() : void
    {
        $this::assertInstanceOf(QROutputInterface::class, $this->outputInterface);
    }

    /**
     * Tests if an exception is thrown when trying to write a cache file to an invalid destination
     */
    public function test_save_exception() : void
    {
        $this->expectException(QRCodeOutputException::class);
        $this->expectExceptionMessage('Could not write data to cache file: /foo/bar.test');

        $this->options->cachefile = '/foo/bar.test';
        $this->outputInterface = $this->getOutputInterface($this->options);
        $this->outputInterface->dump();
    }

    /**
     * coverage of the built-in output modules
     *
     * @dataProvider types
     * @param string $type
     */
    public function test_string_output(string $type) : void
    {
        $this->options->outputType = $type;
        $this->options->cachefile = $this->builddir.'/test.'.$type;
        $this->options->imageBase64 = false;

        $this->outputInterface = $this->getOutputInterface($this->options);
        $data = $this->outputInterface->dump(); // creates the cache file

        $this::assertSame($data, file_get_contents($this->options->cachefile));
    }

    /**
     * covers the built-in output modules, tests against pre-rendered data
     *
     * @dataProvider types
     * @param string $type
     */
    public function test_render_image(string $type) : void
    {
        // may fail on CI, different PHP (platform) versions produce different output
        // the samples were generated on php-7.4.3-Win32-vc15-x64
        if (
            (PHP_OS_FAMILY !== 'Windows' || PHP_VERSION_ID >= 80100)
            && in_array($type, [QRCode::OUTPUT_IMAGE_JPG, QRCode::OUTPUT_IMAGICK, QRCode::OUTPUT_MARKUP_SVG])
        ) {
            $this::markTestSkipped('may fail on CI');

            /** @noinspection PhpUnreachableStatementInspection */
            return;
        }

        $this->options->outputType = $type;

        $this::assertSame(
            trim(file_get_contents(__DIR__.'/samples/'.$type)),
            trim((new QRCode($this->options))->render('test'))
        );
    }

    /**
     * Attempts to create a directory under /.build and instances several required objects
     *
     * @internal
     */
    protected function setUp() : void
    {
        if (!file_exists($this->builddir)) {
            mkdir($this->builddir, 0777, true);
        }

        $this->options = new QROptions;
        $this->matrix = (new Byte($this->options, 'testdata'))->initMatrix(0);
        $this->outputInterface = $this->getOutputInterface($this->options);
    }
}
