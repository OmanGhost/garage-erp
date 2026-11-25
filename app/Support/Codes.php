<?php

namespace App\Support;

use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Picqer\Barcode\BarcodeGeneratorSVG;

/**
 * QR codes and Code-128 barcodes rendered as inline images (no external services).
 */
class Codes
{
    /** data: URI of an SVG QR code. */
    public static function qr(string $data, int $scale = 5): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => true,
            'scale' => $scale,
            'addQuietzone' => true,
        ]);

        return (new QRCode($options))->render($data);
    }

    /** data: URI of an SVG Code-128 barcode. */
    public static function barcode(string $code, int $widthFactor = 2, int $height = 40): string
    {
        $svg = (new BarcodeGeneratorSVG)->getBarcode($code, BarcodeGeneratorSVG::TYPE_CODE_128, $widthFactor, $height);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Oman/GCC e-invoice style QR payload (TLV, base64): seller, VAT no., timestamp, total, VAT.
     * Ready to adapt once the official Omani e-invoicing specification is published.
     */
    public static function invoiceTlv(string $seller, ?string $vatNo, string $timestamp, string $total, string $vat): string
    {
        $tlv = '';
        foreach ([1 => $seller, 2 => (string) $vatNo, 3 => $timestamp, 4 => $total, 5 => $vat] as $tag => $value) {
            $tlv .= chr($tag).chr(strlen($value)).$value;
        }

        return base64_encode($tlv);
    }
}
