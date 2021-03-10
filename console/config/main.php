<?php
$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php'
);

return [
    'id' => 'app-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'console\controllers',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'controllerMap' => [
        'fixture' => [
            'class' => 'yii\console\controllers\FixtureController',
            'namespace' => 'common\fixtures',
          ],
    ],
    'components' => [
        'errorHandler' => [
            'class' => 'common\libs\error\ConsoleErrorHandler',
        ],
        'log' => [
            'flushInterval'=> '10',
            'targets' => [
                [
                    'class' => 'common\libs\file_log\LOG',
                    'levels' => ['info'],
                    'exportInterval' => 10,
//                    'enableRotation' => false,   // 是否文件循环覆盖，为false时,下面2个配置无效
                    'maxFileSize' => 102400,     // 每个文件最大多少kb 100M
                    'maxLogFiles' => 10000,      // 每天几个日志文件
                    'microtime'=> true,
                    'categories' => ['api'],
                ],
                [
                    'class' => 'common\libs\file_log\LOG',
                    'levels' => ['error'],
                    'exportInterval' => 10,
                    'maxFileSize' => 102400,     // 每个文件最大多少kb 100M
                    'maxLogFiles' => 10000,      // 每天几个日志文件
                    'microtime'=> true,
                ],
                [
                    'class' => 'common\mail\zeroEmailTarget',
                    'mailer' => 'mailer',
                    'levels' => ['error'],
                    'message' => [
                        'from' => ['noreply@lingmou.ai'],
                        'to' => ['snapshot_dev@lingmou.ai'],
                        'subject' => '[MARKET_EXEC][CONSOLE][ERROR]',
                    ],
                    'except' => [
                        'yii\web\HttpException:404',
                        'yii\web\HttpException:401'
                    ],
                ],
            ],
        ],
    ],
    'params' => $params,
];
