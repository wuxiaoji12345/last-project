<?php

use yii\db\Migration;

/**
 * Class m201215_062359_update_scene_and_scene_type_v1_8_3
 */
class m201215_062359_update_scene_and_scene_type_v1_8_3 extends Migration
{
    public function init()
    {
        $this->db = 'db2';
        parent::init();
    }

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
//        $sql = "update `sys_scene` set `scene_code_name`='农夫山泉冰柜' where `scene_code`='NONGFUCOOLER';
//                update  `sys_scene` set `sort`=sort + 1 where sort >= 7 ;
//                insert into `sys_scene` ( `scene_type`, `scene_code`, `scene_code_name`, `scene_maxcount`, `scene_need_recognition`, `scene_status`, `sort`, `is_deleted`, `status`, `created_at`, `updated_at`) values ( '1', 'TONGYICOOLER', '统一冰柜', '0', '1', '1', '7', '0', '1', unix_timestamp(now()), unix_timestamp(now()));
//                update `sys_scene` set `scene_code_name`='其他厂商地堆' where `scene_code`='OTHERSGROUND';
//                update `sys_scene` set `scene_code_name`='其他厂商端架' where `scene_code`='OTHERSFRAME';
//                insert into `sys_scene` ( `scene_type`, `scene_code`, `scene_code_name`, `scene_maxcount`, `scene_need_recognition`, `scene_status`, `sort`, `is_deleted`, `status`, `created_at`, `updated_at`) values ( '5', 'OTHERSBOX', '其他厂商包柱', '0', '1', '1', '32', '0', '1', unix_timestamp(now()), unix_timestamp(now()));
//                insert into `sys_scene` ( `scene_type`, `scene_code`, `scene_code_name`, `scene_maxcount`, `scene_need_recognition`, `scene_status`, `sort`, `is_deleted`, `status`, `created_at`, `updated_at`) values ( '6', 'OTHERSSTATION', '其他厂商冰爽站', '0', '1', '1', '39', '0', '1', unix_timestamp(now()), unix_timestamp(now()));
//                insert into `sys_scene` ( `scene_type`, `scene_code`, `scene_code_name`, `scene_maxcount`, `scene_need_recognition`, `scene_status`, `sort`, `is_deleted`, `status`, `created_at`, `updated_at`) values ( '7', 'OTHERSMULTIPLE', '其他厂商多点陈列', '0', '1', '1', '46', '0', '1', unix_timestamp(now()), unix_timestamp(now()));
//                update `sys_scene` set `is_deleted`='1' where `scene_code`='MENU';
//                update `sys_scene_type` set `is_deleted`='1' where `id`='14';
//                update `sys_scene` set `scene_code_name`='其他类型二次陈列' where `scene_code`='OTHERSDISPLAY';
//                update `sys_scene_type` set `name`='其他类型二次陈列' where `id`='15';
//                update `sys_scene` set `is_deleted`='1' where `scene_code`='NORMALDISPLAY';
//                update `sys_scene_type` set `is_deleted`='1' where `id`='18';
//                update `sys_scene` set `scene_maxcount`='1',`scene_need_recognition`='0' where `scene_code`='SHOPBOARD'";
//
//        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m201215_062359_update_scene_and_scene_type_v1_8_3 cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201215_062359_update_scene_and_scene_type_v1_8_3 cannot be reverted.\n";

        return false;
    }
    */
}
