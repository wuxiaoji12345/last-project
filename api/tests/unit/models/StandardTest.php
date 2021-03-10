<?php namespace frontend\tests\models;


use api\models\Standard;
use api\models\User;

class StandardTest extends \Codeception\Test\Unit
{
    /**
     * @var \frontend\tests\UnitTester
     */
    protected $tester;

    /**
     * @var standard
     */
    protected $standard;
    
    protected function _before()
    {
        // 设置用户登录信息
        \Yii::$app->params['user_info'] = $this->swireUser();
        $standard = Standard::findOne(['id' => 1]);
        $this->standard = $standard;
    }

    protected function _after()
    {
    }

    public function swireUser()
    {
        $token = "b1afd55805024da88b3d864ffe235ba8";
        return User::getSwireUser($token);
    }

    // tests
    public function testSomeFeature()
    {

    }

    // 启用
    public function testEnable()
    {
        $this->standard->standard_status = Standard::STATUS_AVAILABLE;
        $this->assertTrue($this->standard->save());
    }

    // 禁用
    public function testDisable()
    {
        $this->standard->plan_status = Standard::STATUS_DISABLED;
        $this->assertTrue($this->standard->save());
    }
}