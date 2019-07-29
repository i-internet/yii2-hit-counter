# Yii2 hit counter extention #

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

First download module . Run the command in the terminal:
```
composer require "coderius/yii2-hit-counter"
```

or add in composer.json
```
"coderius/yii2-hit-counter": "^1.0"
```
and run `composer update`

## Usage

Include module in app config file. In [advanced template](https://github.com/yiisoft/yii2-app-advanced) go to `common/main.php` and set to config array next params:

```
    $conf = [
        ...
    ];
    
    $conf['modules']['hitCounter'] = [
            'class' => 'coderius\hitCounter\Module',
        ];

    $conf['bootstrap'][] = 'coderius\hitCounter\config\Bootstrap';
```

In view file past hit counter widget:
-------------------------------------

```
<?= \coderius\hitCounter\widgets\hitCounter\HitCounterWidget::widget([]); ?>
```

