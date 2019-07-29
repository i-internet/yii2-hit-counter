<?php
/*
 * @copyright Copyright (C) 2019 Sergio coderius <coderius>
 * @license This program is free software: the MIT License (MIT)
 */

namespace coderius\hitCounter;

use Yii;
use yii\web\GroupUrlRule;

class Module extends \yii\base\Module
{
    /** @var string module name */
    public static $moduleName = 'hitCounter';

    /** @var string|null */
    public $userIdentityClass = null;

    // public $controllerNamespace = 'coderius\comments\controllers';

    public function init()
    {
        parent::init();

        \Yii::configure($this, require __DIR__ . '/config/main.php');

        //иначе ломает консольные комманды
        if (Yii::$app instanceof yii\web\Application) {
            if ($this->userIdentityClass === null) {
                $this->userIdentityClass = \Yii::$app->getUser()->identityClass;
            }
        }

//        var_dump(Module::t('messages', 'No comments yet.'));die;
    }

    /**
     * Adds UrlManager rules.
     *
     * @param Application $app the application currently running
     */
    public function addUrlManagerRules($app)
    {
        $app->urlManager->addRules([new GroupUrlRule([
            'prefix' => $this->id,
            'rules' => require __DIR__ . '/config/_routes.php',
        ])], true);

        // var_dump(\Yii::$app->urlManager);
        // var_dump($this->id.'s');
        // die;
    }

    /**
     * @return static
     */
    public static function selfInstance()
    {
        return \Yii::$app->getModule(static::$moduleName);
    }

    /**
     * Get default model classes.
     */
    public function getDefaultModels()
    {
        return [
            'HitCounter' => \coderius\hitCounter\models\HitCounter::className(),
        ];
    }

    public function model($name)
    {
        $models = $this->getDefaultModels();
        if (array_key_exists($name, $models)) {
            return $models[$name];
        }

        return false;
    }
}
