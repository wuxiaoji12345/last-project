<?php

namespace api\models;

use Yii;

/**
 * This is the model class for table "{{%plan_batch_tmp}}".
 *
 * @property int $id
 * @property string $file_id 文件唯一标识
 * @property int $plan_batch_id 批量检查计划
 * @property string $contract_code 协议编号
 * @property string $store_id 售点编号
 * @property int $check_status 校验是否通过，0未校验，1通过，2失败
 * @property int $import_status 导入状态，是否生成检查计划，0未导入 1导入成功 2导入失败
 * @property int $status 删除标记0删除，1有效
 * @property int $created_at  创建时间
 * @property int $updated_at 更新时间
 * @property string $update_time db更新时间
 */
class PlanBatchTmp extends baseModel
{
    const CHECK_STATUS_DEFAULT = 0;
    const CHECK_STATUS_PASS = 1;                // 导入成功
    const CHECK_STATUS_FAIL = 2;                // 导入失败

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%plan_batch_tmp}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['file_id'], 'required'],
            [['plan_batch_id', 'check_status', 'import_status', 'status', 'created_at', 'updated_at'], 'integer'],
            [['update_time'], 'safe'],
            [['file_id'], 'string', 'max' => 80],
            [['contract_code', 'note'], 'string', 'max' => 255],
            [['store_id'], 'string', 'max' => 16],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'file_id' => '文件唯一标识',
            'plan_batch_id' => '批量检查计划',
            'contract_code' => '协议编号',
            'store_id' => '售点编号',
            'check_status' => '校验是否通过，0未校验，1通过，2失败',
            'note' => '检查不通过错误原因',
            'import_status' => '导入状态，是否生成检查计划，0未导入 1导入成功 2导入失败',
            'status' => '删除标记0删除，1有效',
            'created_at' => ' 创建时间',
            'updated_at' => '更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    /**
     * 协议门店关系导入失败的list
     * @param $bodyForm
     * @return array
     */
    public static function getExcelImportFailList($bodyForm)
    {
        $query = self::getExcelImportFailQuery($bodyForm);
        $count = $query->count();
        // 分页
        $page = $bodyForm['page'];
        $pageSize = $bodyForm['page_size'];
        $query->offset(($page - 1) * $pageSize);
        $query->limit($pageSize);

        $data = $query->all();
        return ['count' => $count, 'list' => $data];
    }

    /**
     * 协议门店关系导入失败的query
     * @param $bodyForm
     * @return bQuery|\yii\db\ActiveQuery
     */
    public static function getExcelImportFailQuery($bodyForm)
    {
        return PlanBatchTmp::find()->where([
            'file_id' => $bodyForm['file_id'],
            'check_status' => self::CHECK_STATUS_FAIL,
        ])->asArray();
    }
}
