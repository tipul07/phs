<?php
/**
 * Class DatainterfaceTestAbstract
 *
 * @filesource   DatainterfaceTestAbstract.php
 * @created      24.11.2017
 * @author       Smiley <smiley@chillerlan.net>
 * @copyright    2017 Smiley
 * @license      MIT
 */
namespace chillerlan\QRCodeTest\Data;

use ReflectionClass;
use chillerlan\QRCode\QRCode;
use PHPUnit\Framework\TestCase;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Data\QRDataInterface;
use chillerlan\QRCode\Data\QRCodeDataException;

use function str_repeat;

/**
 * The data interface test abstract
 */
abstract class DatainterfaceTestAbstract extends TestCase
{
    /** @internal */
    protected ReflectionClass $reflection;

    /** @internal */
    protected QRDataInterface $dataInterface;

    /** @internal */
    protected string $testdata;

    /** @internal */
    protected array  $expected;

    /**
     * Returns a data interface instance
     *
     * @internal
     * @param QROptions $options
     */
    abstract protected function getDataInterfaceInstance(QROptions $options) : QRDataInterface;

    /**
     * Verifies the data interface instance
     */
    public function test_instance() : void
    {
        $this::assertInstanceOf(QRDataInterface::class, $this->dataInterface);
    }

    /**
     * Tests ecc masking and verifies against a sample
     */
    public function test_mask_ecc() : void
    {
        $this->dataInterface->setData($this->testdata);

        $maskECC = $this->reflection->getMethod('maskECC');
        $maskECC->setAccessible(true);

        $this::assertSame($this->expected, $maskECC->invoke($this->dataInterface));
    }

    /**
     * @see testInitMatrix()
     * @internal
     * @return int[][]
     */
    public function MaskPatternProvider() : array
    {
        return [[0], [1], [2], [3], [4], [5], [6], [7]];
    }

    /**
     * Tests initializing the data matrix
     *
     * @dataProvider MaskPatternProvider
     * @param int $maskPattern
     */
    public function test_init_matrix(int $maskPattern) : void
    {
        $this->dataInterface->setData($this->testdata);

        $matrix = $this->dataInterface->initMatrix($maskPattern);

        $this::assertInstanceOf(QRMatrix::class, $matrix);
        $this::assertSame($maskPattern, $matrix->maskPattern());
    }

    /**
     * Tests getting the minimum QR version for the given data
     */
    public function test_get_minimum_version() : void
    {
        $this->dataInterface->setData($this->testdata);

        $getMinimumVersion = $this->reflection->getMethod('getMinimumVersion');
        $getMinimumVersion->setAccessible(true);

        $this::assertSame(1, $getMinimumVersion->invoke($this->dataInterface));
    }

    /**
     * Tests if an exception is thrown when the data exceeds the maximum version while auto detecting
     */
    public function test_get_minimum_version_exception() : void
    {
        $this->expectException(QRCodeDataException::class);
        $this->expectExceptionMessage('data exceeds');

        $this->dataInterface = $this->getDataInterfaceInstance(new QROptions(['version' => QRCode::VERSION_AUTO]));
        $this->dataInterface->setData(str_repeat($this->testdata, 1337));
    }

    /**
     * Tests if an exception is thrown on data overflow
     */
    public function test_code_length_overflow_exception() : void
    {
        $this->expectException(QRCodeDataException::class);
        $this->expectExceptionMessage('code length overflow');

        $this->dataInterface->setData(str_repeat($this->testdata, 1337));
    }

    /**
     * @internal
     */
    protected function setUp() : void
    {
        $this->dataInterface = $this->getDataInterfaceInstance(new QROptions(['version' => 4]));
        $this->reflection = new ReflectionClass($this->dataInterface);
    }
}
