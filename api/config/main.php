<?php


$params = array_merge(
    require(__DIR__ . '/../../common/config/params.php'),
    require(__DIR__ . '/../../common/config/params-local.php'),
    require(__DIR__ . '/params.php'),
    require(__DIR__ . '/params-local.php')
);

return [
    'id' => 'main',
    'charset' => 'utf-8',
    'language' => 'zh-CN',
    'timeZone' => 'Asia/Shanghai',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'api\controllers',
    'defaultRoute' => 'index',
    'components' => [
        'request' => [
            'csrfParam' => '_csrf-api',
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ]
        ],
        'response' => [
            'format' => yii\web\Response::FORMAT_JSON,
            'charset' => 'UTF-8',
            'class' => 'yii\web\Response',
            'on beforeSend' => function ($event) {
                if (Yii::$app->controller->module->id !== 'gii') {
                    \Yii::$app->response->format = yii\web\Response::FORMAT_JSON;
                    $response = $event->sender;
                    $response->data = [
                        'code' => isset($response->data['output']) ? $response->data['code'] : $response->getStatusCode(),
                        'message' => isset($response->data['output']) ? $response->data['msg'] : $response->statusText,
                        'data' => isset($response->data['output']) ? $response->data['data'] : $response->data,
                    ];
                    if ($response->data['code'] == 500) {
                        $exception = Yii::$app->errorHandler->exception;
//                        $ding = common\libs\ding\Ding::getInstance();
//                        $trace = array_slice($exception->getTrace(), 0, 3);
//                        $ding->sendTxt("路由：" . Yii::$app->requestedRoute . "\n"
//                            . "入参：" . json_encode(Yii::$app->request->post()) . "\n"
//                            . "报错：" . $exception->getMessage() . "\n"
//                            . "trace：" . json_encode($trace, JSON_UNESCAPED_UNICODE));
                        if ($exception instanceof \yii\db\Exception && YII_DEBUG) {
                            // db类异常，隐藏previous，里面有敏感参数，比如密码
                            $response->data['data']['previous'] = [];
                        }
                        Yii::info(json_encode($response->data, JSON_UNESCAPED_UNICODE), 'api');
                    }
                    $response->statusCode = 200;
                }
            },
        ],
        'user' => [
            'identityClass' => 'common\models\User',
            'enableAutoLogin' => true,
            'identityCookie' => ['name' => '_identity-api', 'httpOnly' => true],
        ],
        'session' => [
            'name' => 'api_session',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'common\libs\file_log\LOG',
                    'levels' => ['info'],
                    'exportInterval' => 10,
//                    'enableRotation' => false,   // 是否文件循环覆盖，为false时,下面2个配置无效
                    'maxFileSize' => 102400,     // 每个文件最大多少kb 100M
                    'maxLogFiles' => 10000,      // 每天几个日志文件
                    'microtime' => true,
                    'categories' => ['api'],
                    'except' => [
                        'yii\web\HttpException:404'
                    ],
                ],
                [
                    'class' => 'common\libs\file_log\LOG',
                    'levels' => ['error'],
                    'exportInterval' => 10,
                    'maxFileSize' => 102400,     // 每个文件最大多少kb 100M
                    'maxLogFiles' => 10000,      // 每天几个日志文件
                    'microtime' => true,
                ],
                [
                    'class' => 'common\mail\zeroEmailTarget',
                    'mailer' => 'mailer',
                    'levels' => ['error'],
                    'message' => [
                        'from' => ['noreply@lingmou.ai'],
                        'to' => ['snapshot_dev@lingmou.ai'],
                        'subject' => '[MARKET_EXEC][API][ERROR]',
                    ],
                    'except' => [
                        'yii\web\HttpException:404',
                        'yii\web\HttpException:401'
                    ],
                ],
            ],
        ],
        'errorHandler' => [
            'class' => 'common\libs\error\WebErrorHandler',
            'errorAction' => 'index/error',
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                '' => 'site/index'
            ],
        ],
    ],
    'params' => $params,
    'modules' => [
        'api' => [
            'class' => 'api\modules\api\Index'
        ],
    ],
];
