<?php


namespace api\models\apiModels;

use api\models\share\CheckStoreQuestion;
use api\models\share\CheckStoreScene;
use api\models\Tools;
use Yii;
use api\models\CheckStoreList;
use api\models\Plan;
use api\models\share\Store;
use yii\db\ActiveRecord;

/**
 * @property string store_id
 * @property string tool_id
 * @property string task_id
 * @property string start_date
 * @property string end_date
 * Class storeCheckDataModel
 * @package api\models\apiModels
 */
class storeCheckDataModel extends apiBaseModel
{
    public $tool_id;
    public $store_id;
    public $task_id;
    public $start_date;
    public $end_date;

    public function rules()
    {
        return [
//            [['tool_id'], 'each', 'rule' => ['integer']],
            [['tool_id'], 'required'],
            ['store_id', 'string'],
            [['task_id', 'start_date', 'end_date'], 'safe'],
            ['store_id', 'validateStore']
        ];
    }

    public function attributeLabels()
    {
        return [
            'tool_id' => '执行工具',
            'store_id' => '售点id',
            'task' => '批次号',
            'start_date' => '开始时间',
            'end_date' => '结束时间',
        ];
    }

    /**
     * 会加载默认时间，如果tool_id是数组或tool_id 是 sfa，开始时间如果入参为空，是今天 0点，结束时间没有默认值
     * @param array $data
     * @param null $formName
     * @return bool
     */
    public function load($data, $formName = null)
    {
        if (is_array($data['tool_id']) || $data['tool_id'] == Tools::TOOL_ID_SFA) {
            if (isset($data['start_date']) && $data['start_date'] != '') {
                $data['start_date'] = date('Y-m-d 00:00:00', strtotime($data['start_date']));
            } else {
                $data['start_date'] = date('Y-m-d 00:00:00');
            }
            if (isset($data['end_date']) && $data['end_date'] != null && $data['end_date'] != '') {
                $data['end_date'] = date('Y-m-d 23:59:59', strtotime($data['end_date']));
            }
        } else {
            $data['start_date'] = date('Y-m-d', strtotime($data['start_date']));
            if (isset($data['end_date']) && $data['end_date'] != null && $data['end_date'] != '') {
                $data['end_date'] = date('Y-m-d', strtotime($data['end_date']));
            }
        }
        return parent::load($data, $formName);
    }

    /**
     * 生成售点检查数据
     * 先插入一条批次售点数据
     * 插入 shared sys_check_store_question、sys_check_store_scene 表
     * @param $date
     * @return bool
     */
    public function generateCheckData($date)
    {
        // 单独生成批次号
        $time = time();
        $queueName = Yii::$app->remq::getQueueName('queue_store_check_task_id_uid') . $time;
        $number = Yii::$app->remq::incr($queueName);
        Yii::$app->remq::setExpire($queueName, 10);
        $task_id = $time . '_' . $number;
        $check_store = new CheckStoreList();
        $check_store->tool_id = $this->tool_id;
        $check_store->task_id = $task_id;
        $check_store->store_id = $this->store_id;
        if ($check_store->save()) {
            // 需要删除当天其他批次的数据，因为有可能重复，可以考虑查询先比较
            $this->removeCheckData($date);
            $this->task_id = $task_id;
            Plan::generateStoreCheckData($this->store_id, $this->tool_id, $task_id, $date);
            return true;
        } else {
            $this->addErrors($check_store->getErrors());
            return false;
        }
    }

    /**
     * 删除其他批次的检查数据
     * @param $date
     */
    public function removeCheckData($date)
    {
        CheckStoreQuestion::deleteAll(['tool_id' => $this->tool_id, 'store_id' => $this->store_id, 'date' => $date]);
        CheckStoreScene::deleteAll(['tool_id' => $this->tool_id, 'store_id' => $this->store_id, 'date' => $date]);
    }

    public function validateStore()
    {
        $store = Store::findOneArray(['store_id' => $this->store_id]);
        if ($store == null)
            $this->addError('store_id', '售点不存在');
        else
            return false;
    }
}