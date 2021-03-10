<?php

use api\models\FunctionPermission;
use api\models\Plan;
use api\models\PlanStoreRelation;
use yii\db\Migration;

/**
 * Class m201019_095852_v1_6_1_plan
 */
class m201019_095852_v1_6_1_plan extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn(Plan::tableName(),'screen_store_option','varchar(1000) NOT NULL default \'{}\' COMMENT \'售点筛选条件\' AFTER `editable`');
        $this->addColumn(Plan::tableName(),'delete_store_option','varchar(1000) NOT NULL default \'{}\' COMMENT \'售点删除条件\' AFTER `screen_store_option`');
//        $this->createIndex('unique_id',PlanStoreRelation::tableName(),['plan_id','store_id'],true);
        $this->insert(FunctionPermission::tableName(), [
            'id' => 108,
            'module' => '检查计划',
            'web_function_id' => '',
            'controller' => 'plan',
            'action' => 'excel-import-download',
            'action_url' => '/plan/excel-import-download',
            'function_id' => 'SEAP-PLAN-0002',
            'menu_function_id' => 'SEAP-PLAN-0001',
            'name' => '检查计划导入售点下载',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1603101675',
            'updated_at' => '1603101675'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'id' => 109,
            'module' => '检查计划',
            'web_function_id' => '',
            'controller' => 'plan',
            'action' => 'excel-import-fail-download',
            'action_url' => '/plan/excel-import-fail-download',
            'function_id' => 'SEAP-PLAN-0002',
            'menu_function_id' => 'SEAP-PLAN-0001',
            'name' => '检查计划导入失败售点下载',
            'note' => '查看/查询权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1603101675',
            'updated_at' => '1603101675'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'id' => 110,
            'module' => '检查计划',
            'web_function_id' => '',
            'controller' => 'plan',
            'action' => 'import-upload',
            'action_url' => '/plan/import-upload',
            'function_id' => 'SEAP-PLAN-0003',
            'menu_function_id' => 'SEAP-PLAN-0001',
            'name' => '导入成功数据',
            'note' => '编辑权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1603101675',
            'updated_at' => '1603101675'
        ]);
        $this->insert(FunctionPermission::tableName(), [
            'id' => 112,
            'module' => '检查计划',
            'web_function_id' => '',
            'controller' => 'plan',
            'action' => 'deploy-store',
            'action_url' => '/plan/deploy-store',
            'function_id' => 'SEAP-PLAN-0003',
            'menu_function_id' => 'SEAP-PLAN-0001',
            'name' => '售点分发完成设置',
            'note' => '编辑权限',
            'sys_used' => '0',
            'status' => '1',
            'created_at' => '1603101675',
            'updated_at' => '1603101675'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->delete(FunctionPermission::tableName(), ['id' => 108]);
        $this->delete(FunctionPermission::tableName(), ['id' => 109]);
        $this->delete(FunctionPermission::tableName(), ['id' => 110]);
        $this->delete(FunctionPermission::tableName(), ['id' => 112]);
        $this->dropColumn(Plan::tableName(), 'screen_store_option');
        $this->dropColumn(Plan::tableName(), 'delete_store_option');
        return true;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201019_095852_v_1_6_1_plan cannot be reverted.\n";

        return false;
    }
    */
}
