<?php
namespace oxycoach\imagebehavior;

use Yii;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\base\Behavior;
use yii\helpers\Url;
use yii\helpers\VarDumper;
use yii\web\UploadedFile;

class ImageBehavior extends Behavior
{
    public $imageModel;
    public $imageVariable;
    public $uploadPath;
    public $webUpload;
    public $sizes;
    public $noImagePath;
    public $multiple = false;

    private $files;
    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if ($this->imageModel === null) {
            throw new InvalidConfigException('The "imageClass" property must be set.');
        }
        if ($this->imageVariable === null) {
            throw new InvalidConfigException('The "imageVariable" property must be set.');
        }
        if ($this->uploadPath === null) {
            throw new InvalidConfigException('The "uploadPath" property must be set.');
        }
        if ($this->webUpload === null) {
            throw new InvalidConfigException('The "webUpload" property must be set.');
        }
        if ($this->sizes === null) {
            throw new InvalidConfigException('The "sizes" property must be set.');
        }
        if ($this->noImagePath === null) {
            throw new InvalidConfigException('The "noImagePath" property must be set.');
        }

    }

    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
        ];
    }

    /**
     * Set $files property for cases when
     * save images out of owner Active Form
     * @param $files
     * @return bool
     */
    public function setFile($files)
    {
        if ($this->files && $this->files instanceof UploadedFile){
            return false;
        }
        $this->files = $files;
        return true;
    }

    /**
     * Invoked before model validation
     */
    public function beforeValidate()
    {
        /** @var ActiveRecord $model */
        $model = $this->owner;

        if (!$this->files) { // $file can be set via setFile method

            $this->files = UploadedFile::getInstances($model, $this->imageVariable);
            if ($this->files instanceof UploadedFile) {
                $model->{$this->imageVariable} = $this->files;
            }
        }
    }

    /**
     * Invoked before model deletes
     */
    public function beforeDelete()
    {
        $this->deleteImages();
    }

    /**
     * Invoked after model saves
     */
    public function afterSave()
    {
        foreach ($this->files as $index => $file) {
            $this->saveImage($file, $index);
        }
    }


    /**
     * Delete all images
     */
    public function deleteImages()
    {
        $model = $this->owner;
        $images = $model->images;

        foreach ($images as $image) {
            $sizes = $this->sizes;
            foreach ($sizes as $size) {
                $folder = $size['folder'];
                @unlink($this->getUploadPath($folder) . $image->path . '/' . $image->id . '.' . $image->extension);
            }
            $image->delete();
        }
    }


    /**
     * Delete image by id
     * @param int $id
     */
    public function deleteImage(int $id)
    {
        $model = $this->owner;
        $image = $model->getImages()->where(['id' => $id])->one();

        if ($image) {
            $sizes = $this->sizes;
            foreach ($sizes as $size) {
                $folder = $size['folder'];
                @unlink($this->getUploadPath($folder) . $image->path . '/' . $image->id . '.' . $image->extension);
            }
            $image->delete();
        }
    }

    /**
     * Get sinlge (or first) image
     * @param string $folder
     * @return bool|string
     */
    public function getImage($folder = 'original')
    {
        $model = $this->owner;
        $folderPath = $this->sizes[$folder]['folder'];

        // getting images models from relation
        $images = $model->images;

        if (!isset($images[0])) {
            return Yii::getAlias($this->noImagePath);
        }

        $img = $images[0];

        return $this->getWebUploadPath($folderPath) . $img->path . '/' . $img->id . '.' . $img->extension;
    }

    /**
     * Get all images in array
     * @param string $folder
     * @return array
     */
    public function getAllImages(string $folder = 'original')
    {
        $model = $this->owner;
        $folderPath = $this->sizes[$folder]['folder'];

        // getting images models from relation
        $images = $model->images;
        $result = [];

        if (!count($images)) {
            return [Yii::getAlias($this->noImagePath)];
        }

        foreach ($images as $image) {
            $result[$image['id']] = $this->getWebUploadPath($folderPath) . $image['path'] . '/' . $image['id'] . '.' . $image['extension'];
        }

        return $result;
    }

    /**
     * Images sorting
     * @param arrray $positions - array of images id's
     */
    public function sortImages(array $positions)
    {
        $model = $this->owner;

        $positions = array_flip($positions);

        foreach ($model->images as $image) {
            $image->sort = $positions[$image->id];
            $image->save();
        }
    }

    /**
     * Image save
     * @param $file
     * @param int $index
     * @return bool
     * @throws \ImagickException
     */
    protected function saveImage(UploadedFile $file, $index = 0)
    {
        if ($file === null || $file === '') return false;

        $hash = md5_file($file->tempName);
        $path = '/' . $hash[0] . $hash[1] . '/' . $hash[2] . $hash[3] ;

        $imageId = $this->saveToDb($path, $file->extension, $index);

        foreach ($this->sizes as $size) {
            $folder = $size['folder'];
            $dir = $this->getUploadPath($folder . $path) ;

            if (!file_exists(Yii::getAlias($dir))) {
                mkdir(Yii::getAlias($dir), 0777, true);
            }

            $filePath = $dir . '/' . $imageId . '.' . $file->extension;

            if (isset($size['width']) && isset($size['height'])) {
                $file->saveAs($filePath, false);
                $this->resizeImage($filePath, $size['width'], $size['height'], $filePath);
            } else {
                $file->saveAs($filePath, false);
            }
        }
    }

    /**
     * Image resize
     * @param string $originalFilePath
     * @param int $width - resize width
     * @param int $height - resize height
     * @param string $newPath
     * @throws \ImagickException
     */
    protected function resizeImage(string $originalFilePath, int $width, int $height, string $newPath)
    {
        $image = new \Imagick($originalFilePath);
        $geo = $image->getImageGeometry();

        $height = (int) $height;

        // no resize if resize width or height is bigger than photo sizes
        if ($width < $geo['width'] || $height < $geo['height']) {
            $bestfit = ($height) ? true : false;
            $image->thumbnailImage($width, $height, $bestfit);
        }

        $image->writeImage($newPath);
        $image->destroy();
    }

    /**
     * Save record to DB
     * @param string $path
     * @param string $extension
     * @param int $index
     * @return bool
     */
    protected function saveToDb(string $path, string $extension, int $index)
    {
        $model = $this->owner;
        $class = $this->imageModel;
        $pkName = $model::primaryKey()[0];

        if ($this->multiple === false) {
            $imageModel = $model->getImages()->one();
            if ($imageModel !== null) {
                $this->deleteImage($imageModel->id);
            }
        }

        $imageModel = new $class();
        $imageModel->itemId = $model->{$pkName};
        $imageModel->path = $path;
        $imageModel->extension = $extension;
        $imageModel->sort = $index;

        if ($imageModel->save()) {
            return $imageModel->id;
        } else {
            VarDumper::dump($imageModel->errors, 3, true);
            die();
        }

        return false;
    }


    /**
     * Get source path to upload folder
     * @param string $folder
     * @return bool|string
     */
    protected function getUploadPath(string $folder)
    {
        return $this->getPath($this->uploadPath, $folder);
    }


    /**
     * Get web path with folder for frontend
     * @param string $folder
     * @return bool|string
     */
    protected function getWebUploadPath(string $folder)
    {
        return $this->getPath($this->webUpload, $folder);
    }


    /**
     * Get path with folder
     * @param string $path
     * @param string $folder
     * @return bool|string
     */
    protected function getPath(string $path, string $folder)
    {
        $folderPath = ($folder) ? '/' . $folder : '' ;
        return Yii::getAlias($path . $folderPath);
    }

}
