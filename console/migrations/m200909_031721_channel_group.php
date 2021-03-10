<?php

use api\models\share\ChannelSub;
use yii\db\Migration;

/**
 * Class m200909_031721_channel_group
 */
class m200909_031721_channel_group extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->db = Yii::$app->db2;
        // `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '删除标识：1有效，0无效',
        //  `created_at` int(11) NOT NULL COMMENT '添加时间',
        //  `updated_at` int(11) NOT NULL COMMENT '业务更新时间',
        //  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'db更新时间',
        $this->createTable('{{%channel_group}}', [
            'id' => 'INT(11) UNSIGNED NOT NULL AUTO_INCREMENT',
            'channel_code' => "varchar(64) NOT NULL DEFAULT '' COMMENT '渠道组code'",
            'channel_name' => "varchar(64) NOT NULL DEFAULT '' COMMENT '渠道组名称'",
            'status' => "tinyint(1) NOT NULL DEFAULT '1' COMMENT '删除标识：1有效，0无效'",
            'created_at' => "int(11) NOT NULL COMMENT '添加时间'",
            'updated_at' => "int(11) NOT NULL COMMENT '业务更新时间'",
            'update_time' => "timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'db更新时间'",
            'PRIMARY KEY (`id`)'
        ], "CHARACTER SET utf8mb4 ENGINE=InnoDB");

        $sql = "insert into sys_channel_group (channel_code, channel_name, created_at, updated_at) values ('Y1', '超市渠道',  1599708883, 1599708883);
                insert into sys_channel_group (channel_code, channel_name, created_at, updated_at) values ('YB', '便利店渠道',  1599708883, 1599708883);
                insert into sys_channel_group (channel_code, channel_name, created_at, updated_at) values ('YC', '卖场渠道',  1599708883, 1599708883);
                insert into sys_channel_group (channel_code, channel_name, created_at, updated_at) values ('YA', '食杂渠道',  1599708883, 1599708883);
                insert into sys_channel_group (channel_code, channel_name, created_at, updated_at) values ('YH', '小型超市渠道',  1599708883, 1599708883);
                insert into sys_channel_group (channel_code, channel_name, created_at, updated_at) values ('YD', '电子商务',  1599708883, 1599708883);
                insert into sys_channel_group (channel_code, channel_name, created_at, updated_at) values ('Y3', '餐饮渠道',  1599708883, 1599708883);
                insert into sys_channel_group (channel_code, channel_name, created_at, updated_at) values ('YE', '娱乐渠道',  1599708883, 1599708883);
                insert into sys_channel_group (channel_code, channel_name, created_at, updated_at) values ('Y8', '网吧渠道',  1599708883, 1599708883);
                insert into sys_channel_group (channel_code, channel_name, created_at, updated_at) values ('YF', '住宿渠道',  1599708883, 1599708883);
                insert into sys_channel_group (channel_code, channel_name, created_at, updated_at) values ('YG', '运输渠道',  1599708883, 1599708883);
                insert into sys_channel_group (channel_code, channel_name, created_at, updated_at) values ('Y5', '院校渠道',  1599708883, 1599708883);
                insert into sys_channel_group (channel_code, channel_name, created_at, updated_at) values ('Y6', '工矿渠道',  1599708883, 1599708883);
                insert into sys_channel_group (channel_code, channel_name, created_at, updated_at) values ('Y7', '批发渠道',  1599708883, 1599708883);
                insert into sys_channel_group (channel_code, channel_name, created_at, updated_at) values ('Y9', '合作伙伴',  1599708883, 1599708883);
                insert into sys_channel_group (channel_code, channel_name, created_at, updated_at) values ('YI', '自贩机',  1599708883, 1599708883);
                insert into sys_channel_group (channel_code, channel_name, created_at, updated_at) values ('Y4', '其他渠道',  1599708883, 1599708883);
";
        $this->execute($sql);


        $this->addColumn(ChannelSub::tableName(), 'channel_group', "varchar(64) NOT NULL DEFAULT '' COMMENT '渠道组code' AFTER `main_id`");

        $sql = "
                    update sys_channel_sub set channel_group = 'Y1' where `code` = 'A10';
                    update sys_channel_sub set channel_group = 'Y1' where `code` = 'A12';
                    update sys_channel_sub set channel_group = 'YB' where `code` = 'A20';
                    update sys_channel_sub set channel_group = 'YB' where `code` = 'A21';
                    update sys_channel_sub set channel_group = 'YB' where `code` = 'A30';
                    update sys_channel_sub set channel_group = 'YB' where `code` = 'A31';
                    update sys_channel_sub set channel_group = 'YC' where `code` = 'A40';
                    update sys_channel_sub set channel_group = 'YA' where `code` = 'A50';
                    update sys_channel_sub set channel_group = 'YA' where `code` = 'A51';
                    update sys_channel_sub set channel_group = 'YA' where `code` = 'A52';
                    update sys_channel_sub set channel_group = 'YA' where `code` = 'A53';
                    update sys_channel_sub set channel_group = 'YA' where `code` = 'A60';
                    update sys_channel_sub set channel_group = 'YA' where `code` = 'A61';
                    update sys_channel_sub set channel_group = 'YA' where `code` = 'A62';
                    update sys_channel_sub set channel_group = 'YA' where `code` = 'A63';
                    update sys_channel_sub set channel_group = 'YA' where `code` = 'A64';
                    update sys_channel_sub set channel_group = 'YA' where `code` = 'A65';
                    update sys_channel_sub set channel_group = 'YA' where `code` = 'A66';
                    update sys_channel_sub set channel_group = 'YH' where `code` = 'A70';
                    update sys_channel_sub set channel_group = 'YH' where `code` = 'A72';
                    update sys_channel_sub set channel_group = 'YA' where `code` = 'B10';
                    update sys_channel_sub set channel_group = 'YA' where `code` = 'B11';
                    update sys_channel_sub set channel_group = 'YA' where `code` = 'B12';
                    update sys_channel_sub set channel_group = 'YA' where `code` = 'B20';
                    update sys_channel_sub set channel_group = 'YD' where `code` = 'B30';
                    update sys_channel_sub set channel_group = 'YD' where `code` = 'B31';
                    update sys_channel_sub set channel_group = 'YD' where `code` = 'B32';
                    update sys_channel_sub set channel_group = 'YD' where `code` = 'B33';
                    update sys_channel_sub set channel_group = 'YD' where `code` = 'B34';
                    update sys_channel_sub set channel_group = 'Y3' where `code` = 'C10';
                    update sys_channel_sub set channel_group = 'Y3' where `code` = 'C11';
                    update sys_channel_sub set channel_group = 'Y3' where `code` = 'C12';
                    update sys_channel_sub set channel_group = 'Y3' where `code` = 'C20';
                    update sys_channel_sub set channel_group = 'Y3' where `code` = 'C21';
                    update sys_channel_sub set channel_group = 'Y3' where `code` = 'C30';
                    update sys_channel_sub set channel_group = 'Y3' where `code` = 'C31';
                    update sys_channel_sub set channel_group = 'Y3' where `code` = 'C32';
                    update sys_channel_sub set channel_group = 'Y3' where `code` = 'C33';
                    update sys_channel_sub set channel_group = 'Y3' where `code` = 'C40';
                    update sys_channel_sub set channel_group = 'Y3' where `code` = 'C41';
                    update sys_channel_sub set channel_group = 'YE' where `code` = 'D10';
                    update sys_channel_sub set channel_group = 'YE' where `code` = 'D11';
                    update sys_channel_sub set channel_group = 'YE' where `code` = 'D12';
                    update sys_channel_sub set channel_group = 'YE' where `code` = 'D20';
                    update sys_channel_sub set channel_group = 'YE' where `code` = 'D30';
                    update sys_channel_sub set channel_group = 'Y8' where `code` = 'D40';
                    update sys_channel_sub set channel_group = 'Y8' where `code` = 'D46';
                    update sys_channel_sub set channel_group = 'YE' where `code` = 'D41';
                    update sys_channel_sub set channel_group = 'YE' where `code` = 'D42';
                    update sys_channel_sub set channel_group = 'YE' where `code` = 'D43';
                    update sys_channel_sub set channel_group = 'YE' where `code` = 'D44';
                    update sys_channel_sub set channel_group = 'YE' where `code` = 'D45';
                    update sys_channel_sub set channel_group = 'YE' where `code` = 'D50';
                    update sys_channel_sub set channel_group = 'YF' where `code` = 'E10';
                    update sys_channel_sub set channel_group = 'YF' where `code` = 'E11';
                    update sys_channel_sub set channel_group = 'YF' where `code` = 'E12';
                    update sys_channel_sub set channel_group = 'YG' where `code` = 'E20';
                    update sys_channel_sub set channel_group = 'YG' where `code` = 'E21';
                    update sys_channel_sub set channel_group = 'YG' where `code` = 'E22';
                    update sys_channel_sub set channel_group = 'Y5' where `code` = 'F10';
                    update sys_channel_sub set channel_group = 'Y5' where `code` = 'F11';
                    update sys_channel_sub set channel_group = 'Y5' where `code` = 'F12';
                    update sys_channel_sub set channel_group = 'Y5' where `code` = 'F13';
                    update sys_channel_sub set channel_group = 'Y5' where `code` = 'F20';
                    update sys_channel_sub set channel_group = 'Y5' where `code` = 'F21';
                    update sys_channel_sub set channel_group = 'Y5' where `code` = 'F22';
                    update sys_channel_sub set channel_group = 'Y5' where `code` = 'F23';
                    update sys_channel_sub set channel_group = 'Y5' where `code` = 'F24';
                    update sys_channel_sub set channel_group = 'Y5' where `code` = 'F25';
                    update sys_channel_sub set channel_group = 'Y5' where `code` = 'F26';
                    update sys_channel_sub set channel_group = 'Y6' where `code` = 'G10';
                    update sys_channel_sub set channel_group = 'Y6' where `code` = 'G11';
                    update sys_channel_sub set channel_group = 'Y6' where `code` = 'G12';
                    update sys_channel_sub set channel_group = 'Y6' where `code` = 'G20';
                    update sys_channel_sub set channel_group = 'Y6' where `code` = 'G21';
                    update sys_channel_sub set channel_group = 'Y6' where `code` = 'G30';
                    update sys_channel_sub set channel_group = 'Y6' where `code` = 'G40';
                    update sys_channel_sub set channel_group = 'Y7' where `code` = 'H10';
                    update sys_channel_sub set channel_group = 'Y7' where `code` = 'H11';
                    update sys_channel_sub set channel_group = 'YC' where `code` = 'H20';
                    update sys_channel_sub set channel_group = 'Y7' where `code` = 'H30';
                    update sys_channel_sub set channel_group = 'Y7' where `code` = 'H31';
                    update sys_channel_sub set channel_group = 'Y7' where `code` = 'H32';
                    update sys_channel_sub set channel_group = 'Y7' where `code` = 'H33';
                    update sys_channel_sub set channel_group = 'Y7' where `code` = 'H34';
                    update sys_channel_sub set channel_group = 'Y9' where `code` = 'H40';
                    update sys_channel_sub set channel_group = 'Y9' where `code` = 'H50';
                    update sys_channel_sub set channel_group = 'Y9' where `code` = 'H51';
                    update sys_channel_sub set channel_group = 'Y9' where `code` = 'H60';
                    update sys_channel_sub set channel_group = 'Y9' where `code` = 'H61';
                    update sys_channel_sub set channel_group = 'Y9' where `code` = 'H70';
                    update sys_channel_sub set channel_group = 'Y9' where `code` = 'H80';
                    update sys_channel_sub set channel_group = 'Y4' where `code` = 'I10';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J10';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J11';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J12';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J13';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J20';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J21';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J22';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J23';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J24';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J25';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J30';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J31';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J32';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J33';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J34';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J40';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J41';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J50';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J51';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J60';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J61';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J62';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J63';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J70';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J71';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J72';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J73';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J74';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J75';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J76';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J80';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J81';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J82';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J83';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J91';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'J92';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'K01';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'K02';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'K11';
                    update sys_channel_sub set channel_group = 'YI' where `code` = 'K21';
                ";
        $this->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->db = Yii::$app->db2;
        $this->dropTable('{{%channel_group}}');
        $this->dropColumn(ChannelSub::tableName(), 'channel_group');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200909_031721_channel_group cannot be reverted.\n";

        return false;
    }
    */
}
