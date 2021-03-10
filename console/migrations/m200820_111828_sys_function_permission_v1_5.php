<?php

use yii\db\Migration;
use api\models\FunctionPermission;

/**
 * Class m200820_111828_sys_function_permission_v1_5
 */
class m200820_111828_sys_function_permission_v1_5 extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->insert(FunctionPermission::tableName(), [
            'id' => 87,
            'module' => '检查项目',
            'web_function_id' => '',
            'controller' => 'rule',
            'action' => 'send-info-to-engine',
            'action_url' => '/rule/send-info-to-engine',
            'function_id' => 'SEAP-SURVEY-0006',
            'menu_function_id' => 'SEAP-SURVEY-0004',
            'name' => '发送规则引擎需要的数据',
            'note' => '编辑权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1586417067',
            'updated_at' => '1586417067'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200820_111828_sys_function_permission_v1_5 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200820_111828_sys_function_permission_v1_5 cannot be reverted.\n";

        return false;
    }
    */
}
