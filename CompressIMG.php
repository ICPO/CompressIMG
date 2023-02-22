<?php

namespace app\components;

use yii\base\Component;

/**
 * Конвертирование в webp файл с сжатием по качеству
 *
 * Class CompressIMG
 * @package app\components
 */
class CompressIMG extends Component
{
    public $cacheTimeout = 86400;
    public $whitelist = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
    public $cacheAlias = 'assets/thumbnail';
    public $quality = 40;


    /**
     * Кеширование/конвертирование в webp
     *
     * @param $path
     * @param false $r_width
     * @param false $r_height
     * @param bool $quality
     * @return string|string[]|null
     */
    public function cache_as_webp($path, $r_width = false, $r_height = false, $quality = false)
    {

        $path = $this->addSlash($path);
        $fullPath = \Yii::getAlias('@webroot') . $path;

        if (is_file($fullPath)) {
            return self::convert($fullPath, $r_width, $r_height, $quality);
        }
        return false;

    }

    /**
     * Основная логика
     *
     * @param $path
     * @param $r_width
     * @param $r_height
     * @param $quality
     * @return string|string[]|null
     */
    private function convert($path, $r_width, $r_height, $quality)
    {
        $info = $this->getImageInfo($path);
        $width = $info[0];
        $height = $info[1];
        $mimeType = $info['mime'];

        $newWidth = $r_width ? $r_width : $width;
        $newHeight = $r_height ? $r_height : $height;


        $currentFileName = pathinfo($path)['basename'];
        $currentFileExtension = pathinfo($path)['extension'];

        $regular = preg_replace('/.*\/web\/(.*)/', '$1', $path);
        $currentDirectoryFile = str_replace('/' . $currentFileName, '', $regular);
        $currentDirectoryFile = $this->addSlash($currentDirectoryFile);

        if (in_array($mimeType, $this->whitelist)) {

            $this->prepareDirectories(\Yii::getAlias('@webroot') . $this->addSlash($this->cacheAlias) . $currentDirectoryFile);
            $pathToCache = str_replace($currentFileExtension, 'webp', \Yii::getAlias('@webroot') . $this->addSlash($this->cacheAlias) . $currentDirectoryFile . '/' . $currentFileName);

            $newImage = $this->createFrom($mimeType, $path);
            if (!$newImage) {
                // return как есть путь до картинки
                return $this->cleanUrl($path);
            }

            if (file_exists($pathToCache)) {

                # check cache
                if (!$this->checkTimestamp($pathToCache) && $this->checkResize($path, $newHeight, $newWidth)) {
                    return $this->cleanUrl($pathToCache);
                }

            }
            $this->setWebp($newImage, $width, $height, $newWidth, $newHeight, $pathToCache, $quality);

            return $this->cleanUrl($pathToCache);

        }

        return $this->cleanUrl($path);
    }


    /**
     * Проверка на ресайз
     * 
     * @param $path
     * @param $newHeight
     * @param $newWidth
     * @return bool
     */
    private function checkResize($path, $newHeight, $newWidth)
    {
        $info = $this->getImageInfo($path);
        $width = $info[0];
        $height = $info[1];

        if ($width != $newWidth || $height != $newHeight) {
            return false;
        }

        return true;
    }

    /**
     * Проверяем дату создания + время кеширования. Если > текущего времени, то обновлять не нужно
     *
     * @param $path
     * @return bool
     */
    private function checkTimestamp($path)
    {
        $ftime = filemtime($path);
        if (time() < $ftime + $this->cacheTimeout) {
            return false;
        }
        return true;
    }

    /**
     * Убрать из url все лишнее, чтобы получить только путь до картинки относительно сайта. Т.е., например, /images/..
     *
     * @param $path
     * @return string|string[]|null
     */
    private function cleanUrl($path)
    {
        return preg_replace('/.*\/web(\/.*)/', '$1', $path);
    }

    /**
     * Добавить слеш перед строкой, если такового нет
     *
     * @param $string
     * @return mixed|string
     */
    private function addSlash($string)
    {
        if ($string[0] != '/') $string = '/' . $string;
        return $string;
    }

    /**
     * Создание необходимой дирректории
     *
     * @param $path_to_save
     */
    private function prepareDirectories($path_to_save)
    {
        if (!is_dir($path_to_save))
            mkdir($path_to_save, 0777, true);
    }

    /**
     * Информация по изображению
     *
     * @param $path
     * @return array|false
     */
    private function getImageInfo($path)
    {
        return getimagesize($path);
    }

    /**
     * На основании mime типа создать "каркас"
     *
     * @param $mime
     * @param $path
     * @return false|\GdImage|resource
     */
    private function createFrom($mime, $path)
    {
        if ($mime == 'image/jpeg' || $mime == 'image/jpg') {
            $new_image = imagecreatefromjpeg($path);
        } elseif ($mime == 'image/webp') {
            $new_image = imagecreatefromwebp($path);
        } elseif ($mime == 'image/png') {
            $new_image = imagecreatefrompng($path);
        } else {
            $new_image = false;
        }
        return $new_image;
    }

    /**
     * Формируем изображение
     *
     * @param $source
     * @param $source_width
     * @param $source_height
     * @param $new_width
     * @param $new_height
     * @return false|\GdImage|resource
     */
    private function formateIMG($source, $source_width, $source_height, $new_width, $new_height)
    {
        $tmpImage = imagecreatetruecolor($new_width, $new_height);
        imagealphablending($tmpImage, false);
        imagesavealpha($tmpImage, true);

        $trnsprnt = imagecolorallocatealpha($tmpImage, 0, 0, 0, 127);
        imagefilledrectangle($tmpImage, 0, 0, $new_width - 1, $new_height - 1, $trnsprnt);

        imagecopyresampled($tmpImage, $source, 0, 0, 0, 0, $new_width, $new_height, $source_width, $source_height);

        return $tmpImage;
    }

    /**
     * Преобразуем в webp
     *
     * @param $newImage
     * @param $width
     * @param $height
     * @param $newWidth
     * @param $newHeight
     * @param $pathToCache
     * @param $quality
     */
    private function setWebp($newImage, $width, $height, $newWidth, $newHeight, $pathToCache, $quality)
    {
        if (!$quality) $quality = $this->quality;
        $tmpImage = $this->formateIMG($newImage, $width, $height, $newWidth, $newHeight);
        imagewebp($tmpImage, $pathToCache, $quality);
        imagedestroy($tmpImage);
    }
}
