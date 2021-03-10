<?php

namespace api\models;


/**
 * This is the model class for table "sys_protocol_store".
 *
 * @property int $id 主键id
 * @property int $contract_id 协议ID
 * @property int $outlet_contract_id 客户协议ID
 * @property string $outlet_id 售点id
 * @property int $store_status 协议状态，10:已确认，30:终止, 90:删除
 * @property string $create_date 客户协议创建时间
 * @property string $update_date 客户协议修改时间
 * @property string $activation_list 生动化列表
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class ProtocolStore extends baseModel
{
    /**
     * 协议状态
     */
    const PROTOCOL_STATUS_ENABLE = 10;       // 10:已确认
    const PROTOCOL_STATUS_DISABLE = 30;      // 30:终止
    const PROTOCOL_STATUS_DELETE = 90;      // 90:删除

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'sys_protocol_store';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['contract_id', 'outlet_contract_id', 'store_status', 'status', 'created_at', 'updated_at'], 'integer'],
            [['activation_list'], 'string'],
            [['update_time'], 'safe'],
            [['outlet_id'], 'string', 'max' => 16],
            [['create_date', 'update_date'], 'string', 'max' => 20],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'contract_id' => '协议ID',
            'outlet_contract_id' => '客户协议ID',
            'outlet_id' => '售点id',
            'store_status' => '协议状态，10:已确认，30:终止, 90:删除',
            'create_date' => '客户协议创建时间',
            'update_date' => '客户协议修改时间',
            'activation_list' => '生动化列表',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    public static function getProtocolStoreList($bodyForm)
    {
        $query = self::find()->where(['contract_id' => $bodyForm['contract_id'], 'store_status' => ProtocolStore::PROTOCOL_STATUS_ENABLE])->asArray();
        $count = $query->count();
        // 分页
        $page = $bodyForm['page'];
        $pageSize = $bodyForm['page_size'];
        $query->offset(($page - 1) * $pageSize);
        $query->limit($pageSize);

        $data = $query->all();
        return ['count' => $count, 'list' => $data];
    }

    public static function removeDuplicate($contract_id)
    {
        $sql = 'DELETE 
                FROM
                    ' . ProtocolStore::tableName() . ' 
                WHERE
                    contract_id = :contract_id
                    AND id NOT IN ( SELECT id FROM ( SELECT min( id ) AS id FROM ' . ProtocolStore::tableName() . '  WHERE contract_id = :contract_id GROUP BY outlet_id ) AS b )';
        \Yii::$app->db->createCommand($sql, ['contract_id' => $contract_id])->execute();
    }
}
