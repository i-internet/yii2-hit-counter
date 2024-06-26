<?php
/*
 * @copyright Copyright (C) 2019 Sergio coderius <coderius>
 * @license This program is free software: the MIT License (MIT)
 */

namespace coderius\hitCounter\widgets\hitCounter;

use Yii;
use coderius\hitCounter\Module;
use coderius\hitCounter\entities\HitCounter;
use yii\base\Widget;
use yii\helpers\Json;
use yii\web\View;
use yii\web\JsExpression;
use yii\helpers\Html;
use yii\base\InvalidParamException;
use yii\helpers\Url;
use coderius\hitCounter\config\Enum;
use coderius\hitCounter\traits\RequestTrait;

/**
 * Widget.
 */
class HitCounterWidget extends Widget
{
        use RequestTrait;
    const COUNTER_VIEW_INVISIBLE = 'invisible';

    /**
     * Array with params which can be transferred by img src to controller.
     * Possible parameters:
     * - 'type'
     * - 'period'
     * 
     * Default params setted in init method:
     *  $counterOptions = [
     *      'type' => self::COUNTER_VIEW_INVISIBLE,
     *      'period' => Enum::PERIOD_DAY
     *  ];
     *
     * @var array
     */
    public $counterId;

    public $counterOptions = [];

    public $linkUrl;

    private $imgSrc;

    private $clientLinkOptions = [];

    /**
     * Relevant tag image attributes (style etc.)
     *
     * @var array
     */
    private $clientImgOptions = [];

    private $widgetId;

    private $counterName = "Hit counter";

     private $doNotRun = false;

    /**
     * Undocumented function
     *
     * @return array
     */
    protected static function counterViewTypes()
    {
        return [
            self::COUNTER_VIEW_INVISIBLE
        ];
    }

    /**
     * Return array with constants date period
     *
     * @return array
     */
    protected static function counterViewPeriod()
    {
        return [
            Enum::PERIOD_DAY,
            Enum::PERIOD_WEEK,
            Enum::PERIOD_MONTH,
        ];
    }
    public function updateVisitCount()
    {
        $ip = Yii::$app->request->getUserIP();
        $counterId = $this->counterId;
        $hostByIP = $this->getHostByAddr();

        // Selecting the existing record
        $sqlSelect = "SELECT id, visit_count FROM tbl_hit_counter 
                  WHERE serv_ip = :ip AND 
                        counter_id = :counterId AND 
                        serv_host_by_ip = :hostByIP AND
                        created_at BETWEEN :startDate AND :endDate
                  LIMIT 1";

        $existingRecord = Yii::$app->db->createCommand($sqlSelect, [
            ':ip' => $ip,
            ':counterId' => $counterId,
            ':hostByIP' => $hostByIP,
            ':startDate' => date('Y-m-d 00:00:00'),
            ':endDate' => date('Y-m-d 23:59:59')
        ])->queryOne();

        // If found, update the visit count
        if ($existingRecord) {
            $newVisitCount = $existingRecord['visit_count'] + 1;
            $sqlUpdate = "UPDATE tbl_hit_counter SET visit_count = :visitCount 
                      WHERE id = :id";

            Yii::$app->db->createCommand($sqlUpdate, [
                ':visitCount' => $newVisitCount,
                ':id' => $existingRecord['id']
            ])->execute();
        }
    }


    public function init()
    {
        parent::init();

        if(null === $this->counterId){
            $this->counterId = $this->getId();
        }
          $existingRecord = HitCounter::find()->where([
            'serv_ip' => Yii::$app->request->getUserIP(),
            'counter_id' => $this->counterId,
            'serv_host_by_ip' => $this->getHostByAddr()
        ])->andWhere([
            'between',
            'created_at',
            date('Y-m-d 00:00:00'),  // Start of the day
            date('Y-m-d 23:59:59')   // End of the day
        ])->limit(1)->one();
        if (!empty($existingRecord)) {
            $this->updateVisitCount();
            $this->doNotRun = true;
            return false;
        }

        // Set defaults in src img (with params to be transferred to the controller)
        $defCOpts = [
            'type' => self::COUNTER_VIEW_INVISIBLE,
            'period' => Enum::PERIOD_DAY
        ];

        $this->counterOptions = array_merge($defCOpts, $this->counterOptions);

        //Сheck valid values of counter type
        if (!in_array($this->counterOptions['type'], self::counterViewTypes())) {
            throw new InvalidParamException("Unknown counter view type '{$this->counterOptions['type']}'.");
        }

        //Сheck valid values of counter view period in generated counter image
        if (!array_key_exists($this->counterOptions['period'], self::counterViewPeriod())) {
            throw new InvalidParamException("Unknown counter view period '{$this->counterOptions['period']}'.");
        }
        
        $this->imgSrc = Url::toRoute(['/hitCounter/hit-counter/index'], true);
        $this->widgetId = $this->getId();
        
        $this->initClientLinkOptions();
        $this->initClientImgOptions();
    }

    //?netbeanse-xdebug
    public function run()
    {
        parent::run();
          if (!$this->doNotRun) {
            //Create counter by event hendler when trigger event in view component View::EVENT_END_PAGE
            Yii::debug('Starting make counter code in app view', __METHOD__);
            // $this->getView()->on(\yii\base\View::EVENT_END_PAGE, [$this, 'makeCounter']);
            return $this->makeCounter();
            Yii::debug('Ending make counter code in app view', __METHOD__);
          }
    }

    /**
     * Print counter code
     * 
     * @return string
     */
    protected function makeCounter()
    {
        $output = '';

        //Default counter set is invisible (self::COUNTER_VIEW_INVISIBLE)
        $type = $this->counterOptions['type'];

        //Since the attributes will be displayed inside javascript in view file, needed escape string
        $clientImgOptions = addslashes(Html::renderTagAttributes($this->clientImgOptions));
        
        //Render view file wich relevant counter type
        $output .= $this->render($type . "-counter.php", [
                'counterId' => $this->counterId,
                'imgSrc' => $this->imgSrc, 
                'clientImgOptions' => $clientImgOptions, //Style etc.
                'counterImgSrcQuery' => $this->counterOptionsToQueryStr()
            ]);

        $output .= $this->buildNoScriptHtml();
        
        //If isset wrapper link url, counter code put inside <a></a> tag
        //This may be necessary if the counter is visible and clickable so that you can go to the statistics page.
        $output = $this->linkUrl ? Html::a($output, $this->linkUrl, $this->clientLinkOptions) : $output;

        //Print html comments to counter output
        echo "<!-- {$this->counterName}-->" . $output . "<!-- / {$this->counterName} -->";
    }

    /**
     * Init image options relevant to counter type.
     *
     * @return void
     */
    protected function initClientImgOptions()
    {
        $this->clientImgOptions["alt"] = $this->counterName;

        switch ($this->counterOptions['type']) {
            case self::COUNTER_VIEW_INVISIBLE:
            $visOpts = ["style" => "width:1px; height:1px"];
            $visOpts["style"] .= $this->linkUrl ? "" : ";position:absolute; left:-9999px;";
            $this->clientImgOptions = array_merge($this->clientImgOptions, $visOpts);
                break;
        }
    }

    /**
     * Init wrap link options relevant to counter type.
     *
     * @return void
     */
    protected function initClientLinkOptions()
    {
        $defOpts = [];
        $defOpts['target'] = '_blank';
        if($this->counterOptions['type'] === self::COUNTER_VIEW_INVISIBLE) $defOpts['style'] = 'position:absolute; left:-9999px;';
        $this->clientLinkOptions = array_merge($defOpts, $this->clientLinkOptions);

    }

    /**
     * Return noscript tag with image tag
     *
     * @return string
     */
    protected function buildNoScriptHtml()
    {
        
        $tag = "noscript";
        $content = Html::img($this->imgSrc, $this->clientImgOptions);
        return Html::tag($tag, $content, []);
    }

    /**
     * Return query string for pass to src image params like period for generate count visits in hit counter image (if it most be an visible)
     *
     * @return string
     */
    protected function counterOptionsToQueryStr()
    {
        return http_build_query($this->counterOptions);
    }

}
