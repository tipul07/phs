<?php
/**
 * Class QROptionsTest
 *
 * @filesource   QROptionsTest.php
 * @created      08.11.2018
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2018 smiley
 * @license      MIT
 *
 * @noinspection PhpUnusedLocalVariableInspection
 */
namespace chillerlan\QRCodeTest;

use chillerlan\QRCode\QRCode;
use PHPUnit\Framework\TestCase;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\QRCodeException;

/**
 * QROptions test
 */
class QROptionsTest extends TestCase
{
    /**
     * @see testVersionClamp()
     * @return int[][]
     * @internal
     */
    public function VersionProvider() : array
    {
        return [
            'values > 40 should be clamped to 40'        => [42, 40],
            'values < 1 should be clamped to 1'          => [-42, 1],
            'values in between shold not be touched'     => [21, 21],
            'value -1 should be treated as is (default)' => [QRCode::VERSION_AUTO, -1],
        ];
    }

    /**
     * Tests the $version clamping
     *
     * @dataProvider VersionProvider
     * @param int $version
     * @param int $expected
     */
    public function test_version_clamp(int $version, int $expected) : void
    {
        $o = new QROptions(['version' => $version]);

        $this::assertSame($expected, $o->version);
    }

    /**
     * @see testVersionMinMaxClamp()
     * @return int[][]
     * @internal
     */
    public function VersionMinMaxProvider() : array
    {
        return [
            'normal clamp'         => [5, 10, 5, 10],
            'exceeding values'     => [-42, 42, 1, 40],
            'min > max'            => [10, 5, 5, 10],
            'min > max, exceeding' => [42, -42, 1, 40],
        ];
    }

    /**
     * Tests the $versionMin/$versionMax clamping
     *
     * @dataProvider VersionMinMaxProvider
     * @param int $versionMin
     * @param int $versionMax
     * @param int $expectedMin
     * @param int $expectedMax
     */
    public function test_version_min_max_clamp(int $versionMin, int $versionMax, int $expectedMin, int $expectedMax) : void
    {
        $o = new QROptions(['versionMin' => $versionMin, 'versionMax' => $versionMax]);

        $this::assertSame($expectedMin, $o->versionMin);
        $this::assertSame($expectedMax, $o->versionMax);
    }

    /**
     * @see testMaskPatternClamp()
     * @return int[][]
     * @internal
     */
    public function MaskPatternProvider() : array
    {
        return [
            'exceed max'   => [42, 7, ],
            'exceed min'   => [-42, 0],
            'default (-1)' => [QRCode::MASK_PATTERN_AUTO, -1],
        ];
    }

    /**
     * Tests the $maskPattern clamping
     *
     * @dataProvider MaskPatternProvider
     * @param int $maskPattern
     * @param int $expected
     */
    public function test_mask_pattern_clamp(int $maskPattern, int $expected) : void
    {
        $o = new QROptions(['maskPattern' => $maskPattern]);

        $this::assertSame($expected, $o->maskPattern);
    }

    /**
     * Tests if an exception is thrown on an incorrect ECC level
     */
    public function test_invalid_ecc_level_exception() : void
    {
        $this->expectException(QRCodeException::class);
        $this->expectExceptionMessage('Invalid error correct level: 42');

        $o = new QROptions(['eccLevel' => 42]);
    }

    /**
     * @see testClampRGBValues()
     * @return int[][][]
     * @internal
     */
    public function RGBProvider() : array
    {
        return [
            'exceeding values' => [[-1, 0, 999], [0, 0, 255]],
            'too few values'   => [[1, 2], [255, 255, 255]],
            'too many values'  => [[1, 2, 3, 4, 5], [1, 2, 3]],
        ];
    }

    /**
     * Tests clamping of the RGB values for $imageTransparencyBG
     *
     * @dataProvider RGBProvider
     * @param array $rgb
     * @param array $expected
     */
    public function test_clamp_rgb_values(array $rgb, array $expected) : void
    {
        $o = new QROptions(['imageTransparencyBG' => $rgb]);

        $this::assertSame($expected, $o->imageTransparencyBG);
    }

    /**
     * Tests if an exception is thrown when a non-numeric RGB value was encoutered
     */
    public function test_invalid_rgb_value_exception() : void
    {
        $this->expectException(QRCodeException::class);
        $this->expectExceptionMessage('Invalid RGB value.');

        $o = new QROptions(['imageTransparencyBG' => ['r', 'g', 'b']]);
    }
}
