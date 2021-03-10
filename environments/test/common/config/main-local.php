<?php
//$configPath = __DIR__. '/env.list';
//$dir = __DIR__;
$env = [];
//if(file_exists($configPath)){
//    $env = json_decode(file_get_contents($configPath), true);
//} else {
//    var_dump('配置文件不存在，请检查配置');
//    die();
//}
// mysql
$dbHost = isset($env['DB_HOST']) ? $env['DB_HOST'] : '49.234.34.77';
$dbPort = isset($env['DB_PORT']) ? $env['DB_PORT'] : '3307';
$dbName = isset($env['DB_NAME']) ? $env['DB_NAME'] : 'check_test';
$dbUser = isset($env['DB_USER']) ? $env['DB_USER'] : 'snapshot';
$dbPassword = isset($env['DB_PASSWORD']) ? $env['DB_PASSWORD'] : 'ZKp4L%*Bt!1pAeEk';

$dbHost2 = isset($env['DB_HOST_2']) ? $env['DB_HOST_2'] : '49.234.34.77';
$dbPort2 = isset($env['DB_PORT_2']) ? $env['DB_PORT_2'] : '3307';
$dbName2 = isset($env['DB_NAME_2']) ? $env['DB_NAME_2'] : 'shared_test';
$dbUser2 = isset($env['DB_USER_2']) ? $env['DB_USER_2'] : 'shared_db';
$dbPassword2 = isset($env['DB_PASSWORD_2']) ? $env['DB_PASSWORD_2'] : '=wr<C)xX&bc>]`5D';

// redis
$redisHost = isset($env['REDIS_HOST']) ? $env['REDIS_HOST'] : '192.168.3.4';
$redisPort = isset($env['REDIS_PORT']) ? $env['REDIS_PORT'] : 6379;
$redisDB = isset($env['REDIS_DB']) ? $env['REDIS_DB'] : 0;

return [
    'components' => [
        'db' => [
            'class' => 'yii\db\Connection',
            'dsn' => "mysql:host=$dbHost;port=$dbPort;dbname=$dbName",
            'username' => $dbUser,
            'password' => $dbPassword,
            'charset' => 'utf8mb4',
            'tablePrefix' => 'sys_',
        ],
        'db2' => [
            'class' => 'yii\db\Connection',
            'dsn' => "mysql:host=$dbHost2;port=$dbPort2;dbname=$dbName2",
            'username' => $dbUser2,
            'password' => $dbPassword2,
            'charset' => 'utf8mb4',
            'tablePrefix' => 'sys_',
        ],
        'redis' => [
            'class' => yii\redis\Connection::class,
            'hostname' => $redisHost,
            'port' => $redisPort,
            'database' => $redisDB,
            'retries' => 1,
        ],
        'mailer' => [
            'class' => 'yii\swiftmailer\Mailer',
            'viewPath' => '@common/mail',
            // send all mails to a file by default. You have to set
            // 'useFileTransport' to false and configure a transport
            // for the mailer to send real emails.
            'useFileTransport' => true,
        ],
        'remq' => [
            'class' => common\components\REMQ::class,
            'queue' => [
                'task' => 'YII_TASK',
                'scene_type' => 'ENGINE_SCENE_INPUT',
                'image_sku' => 'SNAPSHOT_INPUT',
                'video_sku' => 'ENGINE_VIDEO_INPUT',
                'similarity' => 'SIMILARITY_INPUT',
                'store_sign' => 'STORESIGN_INPUT',
                'plan_store_file' => 'PLAN_STORE_FILE',
            ],
            'timeout' => 30
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'common\libs\file_log\LOG',
                    'levels' => ['error', 'warning', 'info'],
                    'logVars' => [],
                    //表示以yii\db\或者app\models\开头的分类都会写入这个文件
                    'categories' => ['yii\db\*', 'app\models\*'],
                    //表示写入到文件
                    'logFile' => '@runtime/../runtime/logs/sql/' . date('Y-m-d') . '.log',
                ]
            ],
        ]
    ],
];
