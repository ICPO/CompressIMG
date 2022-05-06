<?php


namespace app\models;

use Yii;

ini_set('max_execution_time', '600');

class CompressIMG
{

    private $default_img_folder;

    const IMG_QUALITY = 40;
    const IMG_WHITELIST_MIMETYPE = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
    const MKDIR_MODE = 0777;
    const PATH_TO_THUMBNAIL = 'assets/thumbnail'; // папка, куда сохраняются кешированные объекты (по умолчанию, если не указана в конфиге проекта)
    const DEFAULT_IMG_FOLDER = 'uploads'; // Если из path не удалось извлечь папку хранения картинок, то применится указанная в данной константе
    const CACHE_TIMEOUT = 2419200;
    const PATH_TO_NO_IMAGE_PICTURE = 'images/CompressIMG';// путь до заглушек для картинок относительно web. Например, CompressIMG
    const NO_IMG = 'no-image.png'; // Заглушка, если некорректно передан или не передан путь до файла
    const NO_MIME_ACCESS = 'no-access-mime.png'; // Заглушка, если mime не поддерживается
    const NO_MIME_VALID = 'no-valid-mime.png'; // Заглушка, если mime не прошел валидацию
    const INCORRECT_PATH = 'no-path.png'; // Заглушка, если путь до файла не был передан
    const EXCEPTION = 'exception.png'; // Заглушка, если в ходе выполнения скрипта возникла ошибка


    public function cache_as_webp($path = false, $quality = false, $resize = false)
    {
        $user_quality = 0;

        preg_match('~\/(\w+)\/~', $path, $matches);
        $this->default_img_folder = $matches[1] ? $matches[1] : false;
        if (!$this->default_img_folder) $this->default_img_folder = self::DEFAULT_IMG_FOLDER;
        $path = str_replace('@webroot', yii::getAlias('@webroot'), $path);

        if ($path) {
            if (file_exists($path) && !is_dir($path)) {
                if ($quality) {
                    $user_quality = $quality;
                } else {
                    $user_quality = self::IMG_QUALITY;
                }
                return self::convert($path, $user_quality, $resize);
            } else {
                return '/' . self::PATH_TO_NO_IMAGE_PICTURE . '/' . self::NO_IMG;
            }
        } else {
            return '/' . self::PATH_TO_NO_IMAGE_PICTURE . '/' . self::INCORRECT_PATH;
        }
    }

    /**
     * Создает необходимые дирректории по пути до кешируемого объекта
     */
    private function genereDirectory($path_to_check, $path_to_save, $fullPath)
    {
        foreach ($fullPath as $folder) {
            $path_to_check .= '/' . $folder;
            if (is_dir($path_to_check)) {
                $path_to_save .= '/' . $folder;
                if (!is_dir($path_to_save)) mkdir($path_to_save, self::MKDIR_MODE);
            }
        }
        return $path_to_save;
    }

    /**
     * Возвращает путь до папки, куда будут сохраняться кешируемые объекты
     */
    private function getCacheDirectory()
    {
        if (!yii::$app->thumbnail->cacheAlias)
            return Yii::getAlias('@webroot/' . self::PATH_TO_THUMBNAIL);
        else
            return Yii::getAlias('@webroot/' . yii::$app->thumbnail->cacheAlias);
    }

    /**
     * Проверяет кеш изображения. true - кеш валидный, false - нет.
     *
     * todo - переделать проверку кеша, учитывая изменение качества подаваемого изображения.
     */
    private function checkIMGCache($correct_path_to_cache, $width, $height)
    {
        $cache_size = getimagesize($correct_path_to_cache);
        $cache_width = $cache_size[0];
        $cache_height = $cache_size[1];
        if (($width == $cache_width && $height == $cache_height) || $height == -1) {
            $ftime = filemtime($correct_path_to_cache);
            if (time() < $ftime + self::CACHE_TIMEOUT) {
                return false;
            }
        }
        return true;
    }

    private function createFrom($mime, $path){
        if ($mime == 'image/jpeg' || $mime == 'image/jpg') {
            $new_image = imagecreatefromjpeg($path);
        } elseif ($mime == 'image/webp') {
            $new_image = imagecreatefromwebp($path);
        } elseif ($mime == 'image/png') {
            $new_image = imagecreatefrompng($path);
        }else{
            $new_image = false;
        }
        return $new_image;
    }

    private function convert($path, $quality, $resize)
    {
        try {
            $size = getimagesize($path);
            $mime = $size['mime'];
            $width = $resize && is_array($resize) ? (int)$resize[0] : $size[0];
            if ($resize && is_array($resize) && isset($resize[1])) {
                $height = (int)$resize[1];
            } else {
                $height = $size[1];
            }

            $currentFileName = pathinfo($path)['basename'];
            $currentFileExtension = pathinfo($path)['extension'];

            $path_to_save = $this->getCacheDirectory();


            $dirIMG = preg_replace('/.*\/web\/(.*)/', '$1', $path);
            $fullPath = explode('/', $dirIMG);

            if (!is_dir($path_to_save)) mkdir($path_to_save, self::MKDIR_MODE);

            $path_to_check = Yii::getAlias('@webroot');
            $path_to_save = $this->genereDirectory($path_to_check, $path_to_save, $fullPath);


            if (in_array($mime, self::IMG_WHITELIST_MIMETYPE)) {
                $new_image = $this->createFrom($mime, $path);
                if (!$new_image) {
                    return preg_replace('/.*\/web(\/.*)/', '$1', $path);
                }

                $correct_path_to_cache = str_replace($currentFileExtension, 'webp', $path_to_save . '/' . $currentFileName);
                if (file_exists($correct_path_to_cache)) {
                    $check_cache = $this->checkIMGCache($correct_path_to_cache, $width, $height);
                    if (!$check_cache) {
                        return preg_replace('/.*\/web(\/.*)/', '$1', str_replace($currentFileExtension, 'webp', $path_to_save . '/' . $currentFileName));
                    }
                }

                $tmpImage = $this->formateIMG($new_image, $size[0], $size[1], $width, $height);
                imagewebp($tmpImage, str_replace($currentFileExtension, 'webp', $path_to_save . '/' . $currentFileName), $quality);

                imagedestroy($tmpImage);

                return preg_replace('/.*\/web(\/.*)/', '$1', str_replace($currentFileExtension, 'webp', $path_to_save . '/' . $currentFileName));

            } else {
                return '/' . self::PATH_TO_NO_IMAGE_PICTURE . '/' . self::NO_MIME_VALID;
            }
        } catch (\Exception $e) {
            return '/' . self::PATH_TO_NO_IMAGE_PICTURE . '/' . self::EXCEPTION;
        }
    }

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

}


?>