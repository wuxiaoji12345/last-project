<?php

use yii\db\Migration;
use api\models\Plan;
use api\models\PlanStoreTmp;
use api\models\PlanStoreRelation;

/**
 * Class m201019_081719_sys_protocol_client_level_v1_6
 */
class m201019_081719_sys_protocol_client_level_v1_6 extends Migration
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
        $sql = "CREATE TABLE `sys_protocol_client_level` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `client_level` int(11) unsigned NOT NULL COMMENT '协议客户级别',
  `swire_describe` varchar(255) NOT NULL DEFAULT '' COMMENT '太古协议客户级别描述',
  `smart_describe` varchar(255) NOT NULL DEFAULT '' COMMENT 'SmartMEDI前端显示字段',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '删除标记0删除，1有效',
  `created_at` int(11) NOT NULL COMMENT '添加时间',
  `updated_at` int(11) NOT NULL COMMENT '业务更新时间',
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'db更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COMMENT='协议客户级别表';";
        $sql .="INSERT INTO `sys_protocol_client_level` VALUES (1, 1, '金', 'TOP-金', 1, 1580817027, 1580817027, '2020-10-19 16:12:31');
INSERT INTO `sys_protocol_client_level` VALUES (2, 2, '银', 'TOP-银', 1, 1580817028, 1580817028, '2020-10-19 16:12:31');
INSERT INTO `sys_protocol_client_level` VALUES (3, 3, '铜', 'TOP-铜', 1, 1580817029, 1580817029, '2020-10-19 16:12:31');
INSERT INTO `sys_protocol_client_level` VALUES (4, 4, '钻石', 'TOP-钻石', 1, 1580817030, 1580817030, '2020-10-19 16:12:31');
INSERT INTO `sys_protocol_client_level` VALUES (5, 5, '白金', 'TOP-白金', 1, 1580817031, 1580817031, '2020-10-19 16:12:31');
INSERT INTO `sys_protocol_client_level` VALUES (6, 6, '特殊客户', '特殊客户', 1, 1580817032, 1580817032, '2020-10-19 16:12:31');
INSERT INTO `sys_protocol_client_level` VALUES (7, 7, '红燎客户', '红燎客户', 1, 1580817033, 1580817033, '2020-10-19 16:12:31');
INSERT INTO `sys_protocol_client_level` VALUES (8, 8, '旺街客户', '旺街客户', 1, 1580817034, 1580817034, '2020-10-19 16:12:31');
INSERT INTO `sys_protocol_client_level` VALUES (9, 13, '签约KA', '签约KA', 1, 1580817035, 1580817035, '2020-10-19 16:12:31');
INSERT INTO `sys_protocol_client_level` VALUES (10, 14, '临时活动客户', '临时活动客户', 1, 1580817036, 1580817036, '2020-10-19 16:12:31');
INSERT INTO `sys_protocol_client_level` VALUES (11, 15, 'O2O平台网店', 'O2O平台网店', 1, 1580817037, 1580817037, '2020-10-19 16:12:31');
INSERT INTO `sys_protocol_client_level` VALUES (12, 16, 'B2C平台网店', 'B2C平台网店', 1, 1580817038, 1580817038, '2020-10-19 16:12:31');
INSERT INTO `sys_protocol_client_level` VALUES (13, 99, '其他', '其他', 1, 1580817039, 1580817039, '2020-10-19 16:12:31');";
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%protocol_client_level}}');

        return true;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201019_081719_sys_protocol_client_level_v1_6 cannot be reverted.\n";

        return false;
    }
    */
}
