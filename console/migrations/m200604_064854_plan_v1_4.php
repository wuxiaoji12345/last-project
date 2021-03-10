<?php

use api\models\FunctionPermission;
use yii\db\Migration;
use api\models\Plan;

/**
 * Class m200604_064854_plan_update
 */
class m200604_064854_plan_v1_4 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        return $this->up();
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        return $this->down();

    }


    // Use up()/down() to run migration code without a transaction.
    public function up()
    {
        $this->addColumn(Plan::tableName(), 're_photo_time', 'int(11) NOT NULL DEFAULT 0 COMMENT "整改拍照次数" AFTER `reward_amount`');
        $this->addColumn(Plan::tableName(), 'set_store_type', 'tinyint(1) NOT NULL DEFAULT 0 COMMENT "售点设置方式：0默认配置、1、手工配置，2、ZFT同步" AFTER `reward_amount`');
        $this->insert(FunctionPermission::tableName(), [
            'module' => '检查计划',
            'web_function_id' => '',
            'controller' => 'plan',
            'action' => 'finish',
            'action_url' => '/plan/finish',
            'function_id' => 'SEAP-PLAN-0003',
            'menu_function_id' => 'SEAP-PLAN-0001',
            'name' => '完成设置',
            'note' => '编辑权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1586417067',
            'updated_at' => '1586417067'
        ]);
        return true;
    }

    public function down()
    {
        $this->dropColumn(Plan::tableName(), 're_photo_time');
        $this->dropColumn(Plan::tableName(), 'set_store_type');
        return true;
    }

}
