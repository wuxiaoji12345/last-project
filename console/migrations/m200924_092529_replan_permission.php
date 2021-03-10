<?php

use api\models\FunctionPermission;
use yii\db\Migration;

/**
 * Class m200924_092529_replan_permission
 */
class m200924_092529_replan_permission extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->insert(FunctionPermission::tableName(), [
            'id' => '107',
            'module' => '统计重跑',
            'web_function_id' => 'MENU_0010/ACTION_0004',
            'controller' => '',
            'action' => '',
            'action_url' => '',
            'function_id' => 'STATICS-FUN-0004',
            'menu_function_id' => 'STATICS-FUN-0004',
            'name' => '筛选条件选择',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1600676775',
            'updated_at' => '1600676775'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200924_092529_replan_permission cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200924_092529_replan_permission cannot be reverted.\n";

        return false;
    }
    */
}
