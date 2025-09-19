<?php
/**
 * Barcode Rendering Helper
 * ------------------------
 * Invoked inside `admin/barcode.php` to output the selected barcode format as
 * inline HTML or an image tag. The script wires up the Picqer barcode
 * generator library, constrains rendering to the Code128 variants used across
 * the application, and echoes markup combined with a human-readable barcode
 * caption. Front-end scripts embed this endpoint inside iframes to preview and
 * print batches of labels without exposing the PHP barcode library directly in
 * the browser bundle.
 */
// admin/barcode_image.php
$barcode = $_GET['barcode'] ?? '';
$format = $_GET['format'] ?? 'png';
if (!$barcode) {
    echo '<div class="text-danger">No barcode value provided.</div>';
    exit;
}
$barcodeLib = __DIR__ . '/../libs/php-barcode-generator/src/';
// Core classes
require_once $barcodeLib . 'BarcodeGenerator.php';
require_once $barcodeLib . 'BarcodeGeneratorPNG.php';
require_once $barcodeLib . 'BarcodeGeneratorJPG.php';
require_once $barcodeLib . 'BarcodeGeneratorSVG.php';
require_once $barcodeLib . 'BarcodeGeneratorHTML.php';
require_once $barcodeLib . 'BarcodeBar.php';
require_once $barcodeLib . 'Barcode.php';
// Renderers
require_once $barcodeLib . 'Renderers/RendererInterface.php';
require_once $barcodeLib . 'Renderers/PngRenderer.php';
require_once $barcodeLib . 'Renderers/JpgRenderer.php';
require_once $barcodeLib . 'Renderers/SvgRenderer.php';
require_once $barcodeLib . 'Renderers/HtmlRenderer.php';
require_once $barcodeLib . 'Renderers/DynamicHtmlRenderer.php';
// Types (only Code128 and dependencies)
require_once $barcodeLib . 'Types/TypeInterface.php';
require_once $barcodeLib . 'Types/TypeCode128.php';
require_once $barcodeLib . 'Types/TypeCode128A.php';
require_once $barcodeLib . 'Types/TypeCode128B.php';
require_once $barcodeLib . 'Types/TypeCode128C.php';
// Exceptions
require_once $barcodeLib . 'Exceptions/BarcodeException.php';
require_once $barcodeLib . 'Exceptions/UnknownTypeException.php';
require_once $barcodeLib . 'Exceptions/InvalidCharacterException.php';
require_once $barcodeLib . 'Exceptions/InvalidLengthException.php';

function render_barcode($barcode, $format) {
    $numberHtml = '<div style="font-family:monospace;font-size:16px;letter-spacing:2px;margin-top:4px;text-align:center;">' . htmlspecialchars($barcode) . '</div>';
    switch ($format) {
        case 'jpg':
            $generator = new Picqer\Barcode\BarcodeGeneratorJPG();
            $barcodeImg = $generator->getBarcode($barcode, $generator::TYPE_CODE_128);
            return '<div style="display:inline-block;text-align:center">'
                . '<img src="data:image/jpg;base64,' . base64_encode($barcodeImg) . '">' . $numberHtml . '</div>';
        case 'svg':
            $generator = new Picqer\Barcode\BarcodeGeneratorSVG();
            return '<div style="display:inline-block;text-align:center">'
                . $generator->getBarcode($barcode, $generator::TYPE_CODE_128) . $numberHtml . '</div>';
        case 'html':
            $generator = new Picqer\Barcode\BarcodeGeneratorHTML();
            return '<div style="display:inline-block;text-align:center">'
                . $generator->getBarcode($barcode, $generator::TYPE_CODE_128) . $numberHtml . '</div>';
        case 'png':
        default:
            $generator = new Picqer\Barcode\BarcodeGeneratorPNG();
            $barcodeImg = $generator->getBarcode($barcode, $generator::TYPE_CODE_128);
            return '<div style="display:inline-block;text-align:center">'
                . '<img src="data:image/png;base64,' . base64_encode($barcodeImg) . '">' . $numberHtml . '</div>';
    }
}
echo render_barcode($barcode, $format); 