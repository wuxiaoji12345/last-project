<?php

namespace api\models;

use phpDocumentor\Reflection\Types\Self_;
use Yii;

/**
 * This is the model class for table "sys_sub_activity".
 *
 * @property int $id
 * @property int $standard_id 成功图像标准id
 * @property int $activation_id 生动化编号
 * @property string $activation_name 生动化名称
 * @property string $scenes_type_id 主场景id组
 * @property string $scenes_code 次场景code组
 * @property string $question_manual_ir IR问卷组
 * @property string $question_manual 非IR问卷组
 * @property string $image 标准示例图片
 * @property string $describe 描述
 */
class SubActivity extends baseModel
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'sys_sub_activity';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['standard_id', 'status', 'created_at', 'updated_at', 'activation_id'], 'integer'],
            [['describe'], 'string', 'max' => 5000],
            [['activation_name'], 'string', 'max' => 255],
            [['image', 'update_time'], 'safe'],
            [['scenes_type_id', 'scenes_code'], 'safe'],
            ['question_manual_ir', 'default', 'value' => ''],
            ['question_manual', 'default', 'value' => ''],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'standard_id' => '成功图像标准id',
            'activation_id' => '生动化编号',
            'activation_name' => '生动化编号',
            'scenes_type_id' => '主场景id组',
            'scenes_code' => '次场景code组',
            'question_manual_ir' => 'IR问卷组',
            'question_manual' => '非IR问卷组',
            'image' => '标准示例图片',
            'describe' => '描述',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    public function getRuleOutputInfo()
    {
        return $this->hasMany(RuleOutputInfo::class, ['sub_activity_id' => 'id']);
    }
    /**
     * 保存子活动详情
     * @param $data
     * @return array
     */
    public static function saveSubActivity($data)
    {
        $model = new self();
        $model->standard_id = $data['standard_id'];
        $model->activation_id = isset($data['activationID']) ? $data['activationID'] : 0;
        $model->scenes_type_id = $data['scenes_type_id'];
        $model->scenes_code = $data['scenes_code'];
        $model->question_manual_ir = json_encode($data['question_manual_ir']);
        $model->question_manual = json_encode($data['question_manual']);
        $model->image = json_encode($data['image']);
        $model->describe = $data['describe'];
        $model->activation_name = isset($data['sub_activity_name']) ? $data['sub_activity_name'] : '';
        $model->status = self::DEL_STATUS_NORMAL;
        if ($model->save()) {
            return [true, $model->attributes['id']];
        } else {
            return [false, $model->errors];
        }
    }

    /**
     * 修改子活动详情
     * @param $data
     * @return array
     */
    public static function editSubActivity($data)
    {
        $model = self::find()
            ->where(['id' => $data['sub_activity_id']])
            ->one(null, false);
        if ($model) {
            $change = 1;
            $data['sub_activity_name'] = isset($data['sub_activity_name']) ? $data['sub_activity_name'] : '';
            $activationID = isset($data['activationID']) ? $data['activationID'] : 0;
            if ($model->scenes_type_id == $data['scenes_type_id'] && $model->scenes_code == $data['scenes_code'] && $model->activation_id == $activationID &&
                $model->question_manual_ir == json_encode($data['question_manual_ir']) && $model->question_manual == json_encode($data['question_manual'])
            ) {
                $change = 0;
            }
            $not_import_change = 1;
            if ($model->describe == $data['describe'] && $model->activation_name == $data['sub_activity_name']) {
                $not_import_change = 0;
            }
            $model->standard_id = $data['standard_id'];
            $model->scenes_type_id = $data['scenes_type_id'];
            $model->scenes_code = $data['scenes_code'];
            $model->question_manual_ir = json_encode($data['question_manual_ir']);
            $model->question_manual = json_encode($data['question_manual']);
            $model->image = json_encode($data['image']);
            $model->describe = $data['describe'];
            $model->activation_id = $activationID;
            $model->status = self::DEL_STATUS_NORMAL;
            $model->activation_name = $data['sub_activity_name'];
            if ($model->save()) {
                return [true, $model->attributes['id'], $change, $not_import_change];
            } else {
                return [false, $model->getErrors()];
            }
        } else {
            return [false, '子活动不存在'];
        }
    }

    /**
     * 根据子活动ID获取成功图片信息
     *
     * User: hanhyu
     * Date: 2020/10/27
     * Time: 下午2:25
     *
     * @param $sub_activity_id
     *
     * @return Replan|SubActivity|array|\yii\db\ActiveRecord|null
     */
    public static function getImageInfoById($sub_activity_id)
    {
        return self::find()->select(['id', 'activation_name', 'scenes_code', 'describe', 'image'])
            ->where(['id' => $sub_activity_id])
            ->asArray()
            ->one();
    }
}
