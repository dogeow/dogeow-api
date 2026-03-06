<?php

namespace App\Utils;

class ImageColorHelper
{
    /**
     * 获取一张图片的主要颜色（平均颜色）
     *
     * @param  string  $imgPath  图片的本地路径或者在线路径
     * @param  bool  $asHex  是否返回 16 进制颜色
     */
    public static function getMainColor(string $imgPath, bool $asHex = true): string
    {
        $imageInfo = @getimagesize($imgPath);

        if ($imageInfo === false) {
            return $asHex ? '#000000' : 'rgb(0, 0, 0)';
        }

        $imgType = strtolower(substr(image_type_to_extension($imageInfo[2]), 1));
        $imageFun = 'imagecreatefrom' . ($imgType === 'jpg' ? 'jpeg' : $imgType);

        if (! function_exists($imageFun)) {
            return $asHex ? '#000000' : 'rgb(0, 0, 0)';
        }

        $image = @$imageFun($imgPath);

        if (! $image) {
            return $asHex ? '#000000' : 'rgb(0, 0, 0)';
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $rColorNum = $gColorNum = $bColorNum = 0;

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $rColorNum += $r;
                $gColorNum += $g;
                $bColorNum += $b;
            }
        }

        $totalPixels = $width * $height;
        $r = (int) round($rColorNum / $totalPixels);
        $g = (int) round($gColorNum / $totalPixels);
        $b = (int) round($bColorNum / $totalPixels);

        if ($asHex) {
            return self::rgbToHex($r, $g, $b);
        }

        return "rgb({$r}, {$g}, {$b})";
    }

    /**
     * RGB 颜色转 16 进制颜色
     */
    public static function rgbToHex(int $r, int $g, int $b): string
    {
        $r = dechex(max(0, min($r, 255)));
        $g = dechex(max(0, min($g, 255)));
        $b = dechex(max(0, min($b, 255)));

        $color = (strlen($r) < 2 ? '0' : '') . $r;
        $color .= (strlen($g) < 2 ? '0' : '') . $g;
        $color .= (strlen($b) < 2 ? '0' : '') . $b;

        return "#{$color}";
    }
}
