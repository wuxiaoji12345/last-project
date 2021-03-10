<?php

namespace api\models\share;

use api\models\baseModel;
use api\models\Plan;
use api\models\PlanStoreRelation;
use api\models\ProtocolStore;
use api\models\ProtocolTemplate;
use api\models\Standard;
use api\models\User;
use api\service\zft\Protocol;
use common\libs\ding\Ding;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\Connection;
use yii\db\Exception;

/**
 * This is the model class for table "{{%organization_relation}}".
 *
 * @property int $id 主键id
 * @property string $company_code 公司代码
 * @property string $company_name 公司名称
 * @property string $bu_code BU代码
 * @property string $bu_name BU名称
 * @property string $region_code 销售大区代码
 * @property string $region_name 销售大区名称
 * @property string $location_code 营业所代码
 * @property string $location_name 营业所名称
 * @property string $supervisor_code 主任代码
 * @property string $supervisor_name 主任姓名
 * @property string $route_code 业代线路
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class OrganizationRelation extends baseModel
{
    const PROTOCOL_STORE_URL = '/api/getOutletContractList';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%organization_relation}}';
    }

    /**
     * @return object|Connection|null
     * @throws InvalidConfigException
     */
    public static function getDb()
    {
        return Yii::$app->get('db2');
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['company_code', 'created_at', 'updated_at'], 'required'],
            [['status', 'created_at', 'updated_at'], 'integer'],
            [['update_time'], 'safe'],
            [['company_code'], 'string', 'max' => 4],
            [['company_name', 'bu_name', 'region_name', 'location_name', 'supervisor_name'], 'string', 'max' => 50],
            [['bu_code', 'region_code', 'location_code', 'supervisor_code', 'route_code'], 'string', 'max' => 10],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'company_code' => '公司代码',
            'company_name' => '公司名称',
            'bu_code' => 'BU代码',
            'bu_name' => 'BU名称',
            'region_code' => '销售大区代码',
            'region_name' => '销售大区名称',
            'location_code' => '营业所代码',
            'location_name' => '营业所名称',
            'supervisor_code' => '主任代码',
            'supervisor_name' => '主任姓名',
            'route_code' => '业代线路',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    public static function findAllArray($where, $select = ['*'], $index = '', $bu_filter_flag = false, $group_by = null)
    {
        if (static::DEL_FLAG == true) {
            $where['status'] = static::DEL_STATUS_NORMAL;
        }

        if ($bu_filter_flag) {
            $user_info = Yii::$app->params['user_info'];
            $bu_condition = User::getBuCondition(static::class,
                $user_info['company_code'],
                $user_info['bu_code'],
                !Yii::$app->params['user_is_3004']
            );
            if (!empty($bu_condition))
                $where = ['and', $where, $bu_condition];
        }

        $query = self::find()->where($where)->select($select)->groupBy($group_by)->asArray();
        if ($index != '') {
            $query->indexBy($index);
        }
        return $query->all();
    }

    public static function companyBu($where = [])
    {
        $query = self::find()->select(['id', 'company_code', 'bu_code', 'bu_name', 'location_code'])
            ->asArray()->groupBy(['location_code'])->indexBy('location_code');
        $query->andFilterWhere($where);
        $data = $query->all();
        $result = [];
        foreach ($data as $datum) {
            $result[$datum['company_code'] . '_' . $datum['bu_code']] = $datum['bu_name'];
        }
        return $result;
    }

    /**
     * 同步报名售点数据
     * @param $company_code
     * @param $param
     * @throws Exception
     */
    public static function syncStore($company_code, $param)
    {
        $totalPage = 1;
        $page = 1;
        $plan_store_field = ['plan_id', 'store_id'];
        $protocol_store_filed = ['contract_id', 'outlet_contract_id', 'outlet_id', 'store_status', 'create_date', 'update_date', 'activation_list', 'created_at', 'updated_at'];
        $url = Yii::$app->params['zft_url'] . self::PROTOCOL_STORE_URL;
        $header = Protocol::getZftToken(time());
        $header[] = "Content-Type: application/x-www-form-urlencoded";

        // 用事务要考虑下是否会锁表
        $tran = Yii::$app->db->beginTransaction();
        while ($page <= $totalPage) {
            $param['companyCode'] = $company_code;
            $param['currentPageNo'] = $page;
            $param_field = http_build_query($param);

            $res = \Helper::curlQueryLog($url, $param_field, false, 5, $header);
            if ($res['resultCode'] == 200) {
                // 每个报名售点的数据，都有可能对应不同协议，所以需要单独入库
                $data = $res['contractList'];
                $contract_ids = array_column($data, 'contractID');
                $protocols = ProtocolTemplate::findAllArray(['contract_id' => $contract_ids], ['id', 'contract_id'], 'contract_id');
                $protocol_ids = array_column($protocols, 'id');
                $standards = Standard::findAllArray(['protocol_id' => $protocol_ids], ['id', 'protocol_id'], 'protocol_id');
                $standards_ids = array_column($standards, 'id');
                $plans = Plan::findAllArray(['standard_id' => $standards_ids], ['id', 'standard_id', 'set_store_type'], 'standard_id');
                $time = time();
                foreach ($data as $datum) {
                    // 一个协议
                    $insert_data = [];
                    $insert_protocol_store = [];
                    $del_data = [];
                    $datum['outletList'];
                    $plan = @$plans[$standards[$protocols[$datum['contractID']]['id']]['id']];
                    // 有可能有协议，但是在大中台没有配置检查项目和检查计划
                    foreach ($datum['outletList'] as $store) {
                        if ($store['status'] == 10) {
                            $insert_data[] = [$plan['id'], $store['outletNo']];
                        } else {
                            $del_data[] = $store['outletNo'];
                        }
                        $store['activationList'] = json_encode($store['activationList']);
                        $tmp = array_values($store);
                        array_unshift($tmp, $datum['contractID']);
                        $tmp[] = $time;
                        $tmp[] = $time;
                        $insert_protocol_store[] = $tmp;
                    }
                    // 先把数据存到签约客户表
                    $insert_res = Yii::$app->db->createCommand()->batchInsert(ProtocolStore::tableName(), $protocol_store_filed, $insert_protocol_store)->execute();
                    if ($insert_res <= 0) {
                        // 数据插入失败
                        $ding = Ding::getInstance();
                        $ding->sendTxt('【SFA获取报名售点列表】' . ' 签约客户数据插入失败 协议id:' . $datum['contractID']);
                    }
                    if (empty($plan)) {
                        continue;
                    }
                    ProtocolStore::removeDuplicate($protocols[$datum['contractID']]['contract_id']);

                    // 同步签约售点数据时，如果检查计划是excel导入的
                    // 不更新检查计划售点关联表
                    if ($plan['set_store_type'] != Plan::SET_STORE_ZFT) {
                        continue;
                    }

                    //插入新售点
                    $insert_res = Yii::$app->db->createCommand()->batchInsert(PlanStoreRelation::tableName(), $plan_store_field, $insert_data)->execute();//执行批量添加
                    if ($insert_res <= 0) {
                        // 数据插入失败
                        $ding = Ding::getInstance();
                        $ding->sendTxt('【SFA获取报名售点列表】' . ' 售点数据插入失败 检查计划id:' . $plans['id'] . ' 协议id:' . $datum['contractID']);
                    }
                    // 删除重复报名
                    Plan::removeDuplicate($plan['id']);
                    // 删除状态变更
                    if (!empty($del_data)) {
                        Plan::removeStore($plan['id'], $del_data);
                    }
                }
                $totalPage = $res['totalPageCount'];
            }
            $page++;
        }
        $tran->commit();
    }
}
