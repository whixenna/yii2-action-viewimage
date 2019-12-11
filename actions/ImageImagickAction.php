<?php
namespace whixenna\actionViewimage\actions;

use \Imagick;
use Yii;
use yii\base\Action;
use yii\base\UnknownClassException;
use yii\web\NotFoundHttpException;

/**
 * Class ImageImagickAction
 * simple action for view resized and cropped image
 * without dummy and other transformations
 * @property array $dimensions  - size presets as [name => (int, int[2]) size]
 */
class ImageImagickAction extends Action {
    public $dimensions = [];
    public $thumbnailFromTypes = [];
    public $croppedThumbnailFromTypes = [];
    public $filter = Imagick::FILTER_LANCZOS;
    public $ignoreZero = true;

    public function init() {
        parent::init();
        if (!class_exists('Imagick'))
            throw new UnknownClassException('Imagick not found');
    }

    /**
     * @param string $url   - relative path
     * @param string $type  - id of size preset (optional)
     * @param int $w        - resized width (optional)
     * @param int $h        - resized height (optional)
     */
    public function run ($url, $type = null, $w = null, $h = null) {
        $path = realpath(Yii::getAlias('@webroot') . $url);

        if (!is_file($path))
            throw new NotFoundHttpException(Yii::t('app', 'File {0} not found', [$url]));

        if (!$info = @getimagesize($path))
            throw new NotFoundHttpException(Yii::t('app', 'File {0} not found', [$url]));

        if (empty($w) && empty($h) && isset($this->dimensions[$type])) {
            $sizes = $this->dimensions[$type];
            if (is_array($sizes)) {
                $h = isset($sizes[1]) ? $sizes[1] : isset($sizes[0]) ? $sizes[0] : null;
                $w = isset($sizes[0]) ? $sizes[0] : null;
            } else {
                $w = $h = (int)$sizes;
            }
        }
        if (isset($w)) $w = (int)$w;
        if (isset($h)) $h = (int)$h;

        if ($this->ignoreZero) {
            if ($w === 0) $w = null;
            if ($h === 0) $h = null;
        }
        $useW = isset($w);
        $useH = isset($h);

        $image = new Imagick($path);

        if ($useW || $useH) {
            //missing sizes
            if (!$useW)
                $w = $image->getImageWidth() * ($image->getImageHeight() / $h);
            if (!$useH)
                $h = $image->getImageHeight() * ($image->getImageWidth() / $w);

            //cropped resize
            if (in_array($type, $this->croppedThumbnailFromTypes)) {
                $image->thumbnailImage($w, 0,false, false);
                $image->cropThumbnailImage($w, $h);
            }
            //simple crop
            else if (in_array($type, $this->thumbnailFromTypes)) {
                $image->cropThumbnailImage($w, $h);
            }
            //simple resize
            else {
                $image->resizeImage($w, $h, $this->filter, 1, true);
            }
        }
        Yii::$app->response->sendContentAsFile($image->getImageBlob(), '', [
            'mimeType' => $info['mime'],
            'inline' => true,
        ]);
        $image->destroy();
    }
}