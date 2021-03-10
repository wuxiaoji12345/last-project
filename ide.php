<?php

use common\components\OCR;
use common\components\REMQ;
use common\components\REOS;
use yii\redis\Connection;

class Yii
{
    /**
     * @var MyApplication
     */
    public static $app;
}

/**
 * @property REMQ $remq
 * @property REOS $reos
 * @property OCR  $ocr
 * @property Connection   $redis
 */
class MyApplication
{
}