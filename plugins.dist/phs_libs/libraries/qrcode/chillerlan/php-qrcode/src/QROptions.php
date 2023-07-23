<?php
/**
 * Class QROptions
 *
 * @filesource   QROptions.php
 * @created      08.12.2015
 * @author       Smiley <smiley@chillerlan.net>
 * @copyright    2015 Smiley
 * @license      MIT
 */
namespace chillerlan\QRCode;

use chillerlan\Settings\SettingsContainerAbstract;

/**
 * The QRCode settings container
 *
 * @property int $version
 * @property int $versionMin
 * @property int $versionMax
 * @property int $eccLevel
 * @property int $maskPattern
 * @property bool $addQuietzone
 * @property int $quietzoneSize
 * @property null|string $dataModeOverride
 * @property string $outputType
 * @property null|string $outputInterface
 * @property null|string $cachefile
 * @property string $eol
 * @property int $scale
 * @property string $cssClass
 * @property float $svgOpacity
 * @property string $svgDefs
 * @property int $svgViewBoxSize
 * @property string $textDark
 * @property string $textLight
 * @property string $markupDark
 * @property string $markupLight
 * @property bool $returnResource
 * @property bool $imageBase64
 * @property bool $imageTransparent
 * @property array $imageTransparencyBG
 * @property int $pngCompression
 * @property int $jpegQuality
 * @property string $imagickFormat
 * @property null|string $imagickBG
 * @property string $fpdfMeasureUnit
 * @property null|array $moduleValues
 */
class QROptions extends SettingsContainerAbstract
{
    use QROptionsTrait;
}
