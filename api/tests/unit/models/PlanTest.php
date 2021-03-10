<?php namespace frontend\tests\models;

use api\models\Plan;
use api\models\Standard;
use api\models\User;
use Codeception\Test\Unit;
use Yii;
use yii\web\Request;

class PlanTest extends Unit
{
    /**
     * @var \frontend\tests\UnitTester
     */
    protected $tester;

    /**
     * @var Plan
     */
    protected $plan;

    protected function _before()
    {
        // 设置用户登录信息
        Yii::$app->params['user_info'] = $this->swireUser();
        $plan = Plan::findOne(['id' => 152]);
        $this->plan = $plan;
    }

    protected function _after()
    {
    }

    public function swireUser()
    {
        $token = "b1afd55805024da88b3d864ffe235ba8";
        return User::getSwireUser($token);
    }

    public function testSwireUser()
    {
        $user = $this->swireUser();
        $this->assertNotNull($user);
        Yii::$app->params['user_info'] = $user;
    }

    /**
     * 创建检查计划
     */
    public function testCreate()
    {
        $plan = new Plan();
        $standard = Standard::findOne(['id' => 3]);
        $plan->standard_id = $standard->id;
        $plan->load([
            'start_time' => '2020-07-25',
            'end_time' => '2020-07-25',
            'company_code' => '3006',
            'user_id' => '1',
        ], '');
        $flag = $plan->save();
        $this->assertTrue($flag);
        $this->plan = $plan;
    }

    // 启用
    public function testEnable()
    {
        $this->plan->plan_status = Plan::PLAN_STATUS_ENABLE;
        $this->assertTrue($this->plan->save());
    }

    // 禁用
    public function testDisable()
    {
        $this->plan->plan_status = Plan::PLAN_STATUS_DISABLE;
        $this->assertTrue($this->plan->save());
    }

    public function testDelete(){
        $this->plan->plan_status = Plan::PLAN_STATUS_DISABLE;
        $this->plan->save();
        $this->assertGreaterThan(0, $this->plan->delete());
    }
}