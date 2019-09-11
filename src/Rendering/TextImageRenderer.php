<?php

/**
 * This file is part of the Rathouz libraries (http://rathouz.cz)
 * Copyright (c) 2016 Tomas Rathouz <trathouz at gmail.com>
 */

namespace Rathouz\TextImage\Rendering;

use Rathouz\TextImage\TextImage;
use Rathouz\Tools\Objects;


/**
 * TextImageRenderer render the TextImage object.
 *
 * @author Tomas Rathouz <trathouz at gmail.com>
 */
class TextImageRenderer
{
    /**
     * Render image.
     * @param TextImage $textImage
     * @return Objects\Image
     */
    public static function render(TextImage $textImage)
    {
        $path = $textImage->getBackgroundImage(); 
        $image =
            $path != '' ? 
            self::createImageFromFile($path) :
            self::createEmptyImage(
                $textImage->getFullWidth(),
                $textImage->getFullHeight(),
                $textImage->getBorder(),
                $textImage->getBackgroundColor(),
                $textImage->getBorderColor(),
                $textImage->getTransparentBackground()
        );
        $textOffset = $textImage->getTextOffset();
        foreach ($textImage->getLines() as $line) {
            $image = self::addTextToImage(
                $image,
                $line,
                $textImage->getFontPath(),
                $textImage->getFontSize(),
                $textImage->getTextColor(),
                $textOffset
            );
            if ($textImage->getStripText() === TRUE) {
                break;
            }

            $textOffset[TextImage::OPT_TOP] += $textImage->getLineHeight();
        }

        if ($textImage->getWatermarkImage() !== '') {
            self::addWatermarkImage($image, $textImage);
        }

        if ($textImage->getWatermarkText() !== '') {
            self::addWatermarkText($image, $textImage);
        }

        return self::generateRealImage($image, $textImage->getFormat());
    }

    /**
     * @param string
     * @return resource|bool;
     */
    protected static function createImageFromFile($path)
    {
        // If path is a png image
        if (substr($path, -4) === '.png') {
            return imagecreatefrompng($path);
        }

        return imagecreatefromjpeg($path);
    }

    /**
     * @param resource
     * @param TextImage
     * @param string
     * @return resource
     */
    private static function addWatermarkImage($resource, TextImage $textImage)
    {
        $path = $textImage->getWatermarkImage();

        $image = substr($path, -4) === '.png' ? 
            imagecreatefrompng($path) :
            imagecreatefromjpeg($path);

        $transparency = imagecolorallocatealpha($image, 0, 0, 0, 127);

        // rotate, last parameter preserves alpha when true
        $watermark = imagerotate($image, 0, $transparency, 1);
        imagealphablending($watermark, false);
        
        // set the flag to save full alpha channel information
        imagesavealpha($watermark, true);

        $watermarkWidth = imagesx($watermark);
        $watermarkHeight = imagesy($watermark);

        $imageWidth = imagesx($resource);
        $imageHeight = imagesy($resource);

        imagecopy(
            $resource,
            $watermark,
            abs($imageWidth - $watermarkWidth)/2,
            abs($imageHeight - $watermarkHeight)/2,
            0,
            0,
            $watermarkWidth,
            $watermarkHeight,
        );

        return $resource;
    }

    protected static function addWatermarkText($resource, TextImage $textImage)
    {
        $color = Objects\Color::allocateToImage(
            $resource, 
            $textImage->getTextColor()
        );

        $imageWidth = imagesx($resource);
        $imageHeight = imagesy($resource);

        $sizes = imagettfbbox(
            $textImage->getFontSize(),
            $textImage->getWatermarkTextAngle(), 
            $textImage->getFontPath(), 
            $textImage->getWatermarkText()
        );

        $textWidth = abs($sizes[4]-$sizes[0]);
        $textHeight = abs($sizes[5]-$sizes[1]);

        imagettftext(
            $resource,
            $textImage->getFontSize(),
            $textImage->getWatermarkTextAngle(),
            abs($imageWidth - $textWidth)/2,
            abs($imageHeight - $textHeight)/2,
            $color,
            $textImage->getFontPath(),
            $textImage->getWatermarkText()
        );

        return $resource;
    }

    /**
     * Create empty GD image.
     * @param int $width
     * @param int $height
     * @param array $border
     * @param Objects\Color $backgroundColor
     * @param Objects\Color $borderColor
     * @param bool $transparentBackground
     * @return resource
     */
    private static function createEmptyImage($width, $height, array $border, Objects\Color $backgroundColor, Objects\Color $borderColor, $transparentBackground)
    {
        $image = imagecreatetruecolor($width, $height);
        
        if ($transparentBackground === TRUE) {
            imagealphablending($image, false);
            $transparency = imagecolorallocatealpha($image, 0, 0, 0, 127);
            imagefill($image, 0, 0, $transparency);
            imagesavealpha($image, true);
        } else {
            $backColor = Objects\Color::allocateToImage($image, $backgroundColor);
            $bordColor = Objects\Color::allocateToImage($image, $borderColor);

            // Border
            imagefilledrectangle($image, 0, 0, $width, $height, $bordColor);
            // Background
            imagefilledrectangle(
                $image,
                $border[TextImage::OPT_LEFT],
                $border[TextImage::OPT_TOP],
                ($width - $border[TextImage::OPT_RIGHT]),
                ($height - $border[TextImage::OPT_BOTTOM]),
                $backColor
            );
        }
        
        return $image;
    }


    /**
     * Add text to GD image.
     * @param resource $image
     * @param string $text
     * @param string $font
     * @param int $fontSize
     * @param Objects\Color $textColor
     * @param array $offset
     * @return resource
     */
    private static function addTextToImage($image, $text, $font, $fontSize, Objects\Color $textColor, array $offset)
    {
        $color = Objects\Color::allocateToImage($image, $textColor);
        imagettftext($image, $fontSize, 0, $offset[TextImage::OPT_LEFT], $offset[TextImage::OPT_TOP], $color, $font, $text);
        return $image;
    }


    /**
     * Generate real image.
     * @param resource $image
     * @param string $format
     * @return \App\Libraries\Image
     */
    private static function generateRealImage($image, $format)
    {
        $tempFile = tmpfile();
        $metaDatas = stream_get_meta_data($tempFile);
        $tmpFilename = $metaDatas['uri'];
        fclose($tempFile);
        switch ($format) {
            case Objects\Image::PNG:
                imagepng($image, $tmpFilename);
                break;
            case Objects\Image::JPG:
                imagejpeg($image, $tmpFilename);
                break;
            case Objects\Image::GIF:
                imagegif($image, $tmpFilename);
                break;
            default:
                $format = Objects\Image::PNG;
                imagepng($image, $tmpFilename);
                break;
        }

        $formatedFilename = $tmpFilename.".".$format;
        @rename($tmpFilename, $formatedFilename);
        imagedestroy($image);
        return new Objects\Image($formatedFilename, $format);
    }


}
