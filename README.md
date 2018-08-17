# yii2-file-behavior
Yii 2 file uploading

## Install via Composer

Run the following command

```bash
$ composer require oxy-coach/yii2-file-behavior "*"
```

or add

```bash
$ "oxy-coach/yii2-file-behavior": "*"
```

to the require section of your `composer.json` file.

## Migrations

Create migration by following command

```bash
$ yii migrate/create images
```

Open the `/path/to/migrations/m_xxxxxx_xxxxxx_images.php` file 
and add following code to `up()` method


```php
        $this->createTable('images', [
            'id' => $this->primaryKey(),
            'itemId' => $this->integer(11)->notNull(),
            'path' => $this->string(255),
            'extension' => $this->string(255)->notNull(),
            'sort' => $this->integer()->defaultValue(0),
        ]);
```

## Create model

Generate Active Record model for new `images` table

## Configuring

Attach the behavior to your model class:

```php
use oxycoach\imagebehavior\ImageBehavior;

\\ ...

    public $file;

    public function behaviors()
    {
        return [
            'ImageBehavior' => [
                'class' => ImageBehavior::class,
                'imageModel' => Images::class,
                'imageVariable' => 'file',
                'uploadPath' => '@upload',
                'webUpload' => '@webupload',
                'noImagePath' => '@webupload/no-photo.png',
                'multiple' => true,
                'sizes' => [
                    'original' => [
                        'folder' => 'images/original'
                    ],
                    'preview' => [
                        'folder' => 'images/preview',
                        'width' => 350,
                        'height' => 0, // "0" means auto-height
                    ],
                ],
            ],
        ];
    }

    public function rules()
    {
        [['file'], 'file', 'extensions' => ['png', 'jpg', 'jpeg', 'gif'], 'maxSize' => 1024*1024*1024, 'maxFiles' => 3],
    }
```
Add relation for Images model
```php
    /**
     * Images model relation
     * @return \yii\db\ActiveQuery
     */
    public function getImages()
    {
        return $this->hasMany(Images::class, ['itemId' => 'id'])
            ->alias('img')
            ->orderBy('img.sort ASC');
    }

```
> Note that relation name **MUST** be `images`, 

With that configuration, if file hash will be like `"6e3c797abee0ff2803ef1f952f187d2f"` there would be 2 images:

- `@upload/images/original/6e/3c/{id from image table}.jpg`
- `@upload/images/preview/6e/3c/{id from image table}.jpg`

for each image that would be uploaded

## View file

Example of view file

```html
<?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]) ?>
    <?= $form->field($model, 'file[]')->fileInput(['multiple' => true, 'accept' => 'image/*']) ?>
    <div class="form-group">
        <?= Html::submitButton('Save', ['class' => 'btn btn-success']) ?>
    </div>
<?php ActiveForm::end(); ?>
```  

> Note that if you need a single image uploading, you have to change `multiple` property for behavior to false, change your model file rule `maxFiles` property to 1, and also change your view form field to 

```php
<?= $form->field($model, 'file')->fileInput(['multiple' => false, 'accept' => 'image/*']) ?>
```

## Geting images

Get single image:

```html
<img src="<?= $model->getImage('original') ?>" alt="">
<img src="<?= $model->getImage('preview') ?>" alt="">
```

Get all images:

```html
<?php foreach ($model->getAllImages('original') as $image) { ?>
    <img src="<?= $image ?>" alt="">
<?php } ?>
```
