<?php

namespace Modules\Enrollment\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Picqer\Barcode\BarcodeGeneratorPNG;

class QRCodeService
{
    /**
     * Generate QR code as base64 PNG
     */
    public function generate(array $data, string $signature, int $size = 200): string
    {
        $payload = json_encode([
            'data' => $data,
            'signature' => $signature,
        ]);

        try {
            $options = new QROptions([
                'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                'eccLevel' => QRCode::ECC_H,
                'scale' => max(1, (int) ($size / 25)),
                'imageBase64' => false,
                'addQuietzone' => true,
                'quietzoneSize' => 2,
            ]);

            $qrcode = new QRCode($options);
            $imageData = $qrcode->render($payload);

            return base64_encode($imageData);
        } catch (\Exception $e) {
            \Log::warning('QR code generation failed, using fallback', ['error' => $e->getMessage()]);
            return $this->generatePlaceholderQR($size);
        }
    }

    /**
     * Generate barcode as base64 PNG
     */
    public function generateBarcode(string $data, int $widthFactor = 2, int $height = 50): string
    {
        try {
            $generator = new BarcodeGeneratorPNG();
            $barcode = $generator->getBarcode($data, BarcodeGeneratorPNG::TYPE_CODE_128, $widthFactor, $height);

            return base64_encode($barcode);
        } catch (\Exception $e) {
            \Log::warning('Barcode generation failed, using fallback', ['error' => $e->getMessage()]);
            return $this->generatePlaceholderBarcode($data);
        }
    }

    /**
     * Generate placeholder QR code for development/fallback
     */
    private function generatePlaceholderQR(int $size): string
    {
        $image = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);

        // Fill with white background
        imagefilledrectangle($image, 0, 0, $size, $size, $white);

        // Draw border
        imagerectangle($image, 0, 0, $size - 1, $size - 1, $black);

        // Draw corner squares (QR code style)
        $cornerSize = (int) ($size * 0.25);
        $innerSize = (int) ($cornerSize * 0.6);
        $offset = (int) (($cornerSize - $innerSize) / 2);

        // Top-left corner
        imagefilledrectangle($image, 5, 5, 5 + $cornerSize, 5 + $cornerSize, $black);
        imagefilledrectangle($image, 5 + $offset, 5 + $offset, 5 + $offset + $innerSize, 5 + $offset + $innerSize, $white);
        imagefilledrectangle($image, 5 + $offset + 3, 5 + $offset + 3, 5 + $offset + $innerSize - 3, 5 + $offset + $innerSize - 3, $black);

        // Top-right corner
        $x = $size - 5 - $cornerSize;
        imagefilledrectangle($image, $x, 5, $x + $cornerSize, 5 + $cornerSize, $black);
        imagefilledrectangle($image, $x + $offset, 5 + $offset, $x + $offset + $innerSize, 5 + $offset + $innerSize, $white);
        imagefilledrectangle($image, $x + $offset + 3, 5 + $offset + 3, $x + $offset + $innerSize - 3, 5 + $offset + $innerSize - 3, $black);

        // Bottom-left corner
        $y = $size - 5 - $cornerSize;
        imagefilledrectangle($image, 5, $y, 5 + $cornerSize, $y + $cornerSize, $black);
        imagefilledrectangle($image, 5 + $offset, $y + $offset, 5 + $offset + $innerSize, $y + $offset + $innerSize, $white);
        imagefilledrectangle($image, 5 + $offset + 3, $y + $offset + 3, 5 + $offset + $innerSize - 3, 5 + $offset + $innerSize - 3, $black);

        // Draw "QR" text in center
        imagestring($image, 5, (int) ($size / 2 - 10), (int) ($size / 2 - 10), 'QR', $black);

        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        return base64_encode($imageData);
    }

    /**
     * Generate placeholder barcode for development/fallback
     */
    private function generatePlaceholderBarcode(string $data): string
    {
        $width = 200;
        $height = 50;

        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);

        imagefilledrectangle($image, 0, 0, $width, $height, $white);

        // Draw barcode-like lines based on data
        $x = 10;
        $dataLen = strlen($data);

        for ($i = 0; $i < $dataLen && $x < $width - 10; $i++) {
            $char = ord($data[$i]);
            $barWidth = ($char % 3) + 1;
            $spaceWidth = (($char >> 2) % 3) + 1;

            // Draw bar
            imagefilledrectangle($image, $x, 5, $x + $barWidth, $height - 5, $black);
            $x += $barWidth + $spaceWidth;
        }

        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        return base64_encode($imageData);
    }
}
