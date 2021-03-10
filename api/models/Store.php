<?php

namespace api\models;

use api\models\share\Equipment;
use api\models\share\MarketSegment;
use api\service\plan\PlanService;
use Yii;
use yii\db\Expression;
use yii\db\Query;

/**
 * This is the model class for table "{{%store}}".
 *
 * @property int $id
 * @property int $store_id 售点编号
 * @property string $name 售点名称
 * @property string $address 售点地址
 * @property string $storekeeper 店主姓名
 * @property int $phone 店主手机
 * @property string $sub_channel_code 售点所属次渠道编号，下同
 * @property int $company_code 厂房编号
 * @property string $bu_code bu编号
 * @property string $region_code 大区编号
 * @property int $location_code 营业所编号
 * @property string $location_name 营业所名称
 * @property string $supervisor_name 主任名称
 * @property int $route_code 线路编号
 * @property string $route_name 线路名
 * @property int $status_code 售点状态 0未启用，1启用，2已关闭
 * @property int $status 删除标记0删除，1有效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class Store extends baseModel
{
    const HAS_EQUIPMENT_YES = 1;
    const HAS_EQUIPMENT_NO = 0;                // 导入成功

    const PAGINATION_FOR_SCREEN_STORE = 20000;                // 售点单次导入分页限制

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%store}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['store_id', 'phone', 'company_code', 'location_code', 'route_code', 'status_code', 'status', 'created_at', 'updated_at'], 'integer'],
            [['update_time'], 'safe'],
            [['name', 'location_name', 'supervisor_name', 'route_name'], 'string', 'max' => 50],
            [['address'], 'string', 'max' => 200],
            [['storekeeper', 'sub_channel_code', 'region_code'], 'string', 'max' => 10],
            [['bu_code'], 'string', 'max' => 5],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'store_id' => '售点编号',
            'name' => '售点名称',
            'address' => '售点地址',
            'storekeeper' => '店主姓名',
            'phone' => '店主手机',
            'sub_channel_code' => '售点所属次渠道编号，下同',
            'company_code' => '厂房编号',
            'bu_code' => 'bu编号',
            'region_code' => '大区编号',
            'location_code' => '营业所编号',
            'location_name' => '营业所名称',
            'supervisor_name' => '主任名称',
            'route_code' => '线路编号',
            'route_name' => '线路名',
            'status_code' => '售点状态 0未启用，1启用，2已关闭',
            'status' => '删除标记0删除，1有效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }

    /**
     * 售点分发处理
     * @param $bodyForm
     * @param bool $is_count
     * @return array|mixed
     * @throws \yii\db\Exception
     */
    public static function findScreenStore($bodyForm, $is_count = false)
    {
        $plan = Plan::findOne(['id' => $bodyForm['plan_id']]);
        if ($plan == null) {
            return [false, '无此计划，请检查'];
        }

        $bodyForm['screen_store_option'] = $bodyForm['screen_option'];
        $bodyForm['delete_store_option'] = $bodyForm['delete_option'];
        $bodyForm['id'] = $bodyForm['plan_id'];
        $bodyForm['company_code'] = $plan['company_code'];
        $bodyForm['bu_code'] = $plan['bu_code'];
        unset($bodyForm['screen_option']);
        unset($bodyForm['delete_option']);
        //首先要把store_relation表的该plan下的关联数据先要全部删除

        $query = PlanService::getPlanStoreListByParams($bodyForm, [PlanStoreTmp::CHECK_STATUS_PASS, PlanStoreTmp::CHECK_STATUS_FILTER_PASS, PlanStoreTmp::CHECK_STATUS_FILTER_FAIL]);
        if ($is_count) {
            return $query->count();
        }
//        for ($i = 0; $i < $count; $i += self::PAGINATION_FOR_SCREEN_STORE) {
//            $screen_list = self::JoinScreenStore($bodyForm, Store::tableName() . '.store_id', $i);
//            $screen_list = array_column($screen_list, 'store_id');
//            PlanStoreTmp::updateAll(['check_status' => PlanStoreTmp::CHECK_STATUS_FILTER_PASS], ['store_id' => $screen_list, 'plan_id' => $bodyForm['plan_id'], 'import_type' => PlanStoreTmp::IMPORT_TYPE_ADD]);
//            PlanStoreRelation::addNoRepeat($screen_list, $bodyForm['plan_id']);
//        }

        PlanStoreRelation::deleteAll(['plan_id' => $bodyForm['plan_id']]);

        // 将完全失败的更新为 5
        PlanStoreTmp::updateAll(['check_status' => PlanStoreTmp::CHECK_STATUS_REAL_FAIL],
            ['plan_id' => $bodyForm['plan_id'], 'import_type' => PlanStoreTmp::IMPORT_TYPE_ADD, 'check_status' => PlanStoreTmp::CHECK_STATUS_FAIL]);

        // 将导入成功的全部更新为失败
        PlanStoreTmp::updateAll(['check_status' => PlanStoreTmp::CHECK_STATUS_FILTER_FAIL],
            ['plan_id' => $bodyForm['plan_id'], 'import_type' => PlanStoreTmp::IMPORT_TYPE_ADD, 'check_status' => PlanStoreTmp::CHECK_STATUS_PASS]);
        // 再将匹配成功的售点更新为成功

        $tmpQuery = new Query();
        $tmpQuery->select(['store_id'])->from(new Expression(' (' . $query->createCommand()->getRawSql() . ') a'));
        $query->select(['main.store_id']);
        PlanStoreTmp::updateAll(['check_status' => PlanStoreTmp::CHECK_STATUS_FILTER_PASS],
            ['and', ['in', 'store_id', $tmpQuery],
                ['plan_id' => $bodyForm['plan_id']],
                ['import_type' => PlanStoreTmp::IMPORT_TYPE_ADD]]);
        $tmpQuery->select([new Expression($plan['id']), 'store_id']);
        Yii::$app->db->createCommand("insert into " . PlanStoreRelation::tableName() . " (plan_id, store_id) " . $tmpQuery->createCommand()->getRawSql())
            ->execute();
        Plan::removeDuplicate($bodyForm['plan_id']);

        $plan->screen_store_option = $bodyForm['screen_store_option'] ? json_encode($bodyForm['screen_store_option']) : '{}';
        $plan->delete_store_option = $bodyForm['delete_store_option'] ? json_encode($bodyForm['delete_store_option']) : '{}';
        if (!$plan->save(false)) {
            return [false, $plan->getErrors()];
        }

        return [true, '分发成功'];
    }

    /**
     * 按照条件拼凑售点分发的sql
     * @param $bodyForm
     * @param $field
     * @param string $join
     * @param bool $is_file_or
     * @param string $pagination
     * @return array|\yii\db\DataReader
     * @throws \yii\db\Exception
     */
    public static function JoinScreenStore($bodyForm, $field, $pagination = '')
    {
        //todo 此处应该写一个方法统一处理
        preg_match("/dbname=([^;]*)/", \Yii::$app->db2->dsn, $matches);
        $database = $matches[1];
        $sql1 = 'SELECT ' . $field . ' FROM ' . Store::tableName();
        //筛选条件
        $and = [];
        $or = [];
        $join = '';
        if (isset($bodyForm['screen_option']['up_file'])) {
            $join .= ' LEFT JOIN ' . PlanStoreTmp::tableName() . ' p ON p.store_id = ' . Store::tableName() . '.store_id AND p.import_type = ' . PlanStoreTmp::IMPORT_TYPE_ADD . ' AND p.plan_id = ' . $bodyForm['plan_id'];
            if ($bodyForm['screen_option']['up_file']['logic'] == 'and') {
                $and[] = " (p.check_status in(" . PlanStoreTmp::CHECK_STATUS_PASS . "," . PlanStoreTmp::CHECK_STATUS_FILTER_PASS . ")" . " AND p.plan_id = " . $bodyForm['plan_id'] . ")";
            } else {
                $or[] = " (p.check_status in(" . PlanStoreTmp::CHECK_STATUS_PASS . "," . PlanStoreTmp::CHECK_STATUS_FILTER_PASS . ")" . " AND p.plan_id = " . $bodyForm['plan_id'] . ")";
            }
        }
        if (isset($bodyForm['screen_option']['region_code'])) {
            if ($bodyForm['screen_option']['region_code']['logic'] == 'and') {
                $and[] = " region_code in ('" . implode("','", $bodyForm['screen_option']['region_code']['region_code']) . "')";
            } else {
                $or[] = " region_code in ('" . implode("','", $bodyForm['screen_option']['region_code']['region_code']) . "')";
            }
        }
        if (isset($bodyForm['screen_option']['location_code'])) {
            if ($bodyForm['screen_option']['location_code']['logic'] == 'and') {
                $and[] = " location_code in ('" . implode("','", $bodyForm['screen_option']['location_code']['location_code']) . "')";
            } else {
                $or[] = " location_code in ('" . implode("','", $bodyForm['screen_option']['location_code']['location_code']) . "')";
            }
        }
        if (isset($bodyForm['screen_option']['market_channel'])) {
            if ($bodyForm['screen_option']['market_channel']['logic'] == 'and') {
                $and[] = " market_channel in ('" . implode("','", $bodyForm['screen_option']['market_channel']['market_channel']) . "')";
            } else {
                $or[] = " market_channel in ('" . implode("','", $bodyForm['screen_option']['market_channel']['market_channel']) . "')";
            }
        }
        if (isset($bodyForm['screen_option']['sub_channel_code'])) {
            if ($bodyForm['screen_option']['sub_channel_code']['logic'] == 'and') {
                $and[] = " sub_channel_code in ('" . implode("','", $bodyForm['screen_option']['sub_channel_code']['sub_channel_code']) . "')";
            } else {
                $or[] = " sub_channel_code in ('" . implode("','", $bodyForm['screen_option']['sub_channel_code']['sub_channel_code']) . "')";
            }
        }
        if (isset($bodyForm['screen_option']['route_code'])) {
            if ($bodyForm['screen_option']['route_code']['logic'] == 'and') {
                $and[] = " route_code in ('" . implode("','", $bodyForm['screen_option']['route_code']['route_code']) . "')";
            } else {
                $or[] = " route_code in ('" . implode("','", $bodyForm['screen_option']['route_code']['route_code']) . "')";
            }
        }
        if (isset($bodyForm['screen_option']['is_ka'])) {
            $is_ka = $bodyForm['screen_option']['is_ka']['is_ka'] ? 'Y' : 'N';
            if ($bodyForm['screen_option']['is_ka']['logic'] == 'and') {
                $and[] = " ka_indicator ='" . $is_ka . "'";
            } else {
                $or[] = " ka_indicator ='" . $is_ka . "'";
            }
        }
        if (isset($bodyForm['screen_option']['client_level'])) {
            if ($bodyForm['screen_option']['client_level']['logic'] == 'and') {
                $and[] = " market_segment_code in ('" . implode("','", $bodyForm['screen_option']['client_level']['client_level']) . "')";
            } else {
                $or[] = " market_segment_code in ('" . implode("','", $bodyForm['screen_option']['client_level']['client_level']) . "')";
            }
        }
        if (isset($bodyForm['screen_option']['has_icebox'])) {
            $has_icebox = $bodyForm['screen_option']['has_icebox']['has_icebox'] ? self::HAS_EQUIPMENT_YES : self::HAS_EQUIPMENT_NO;
            if ($bodyForm['screen_option']['has_icebox']['logic'] == 'and') {
                $and[] = ' has_equipment =' . $has_icebox;
            } else {
                $or[] = ' has_equipment =' . $has_icebox;
            }
        }
        if (isset($bodyForm['screen_option']['market_segment'])) {
            $join .= ' LEFT JOIN ' . $database . '.' . MarketSegment::tableName() . ' m ON m.town_code = ' . Store::tableName() . '.town_code';
            $join_segment = true;
            if ($bodyForm['screen_option']['market_segment']['logic'] == 'and') {
                $and[] = " (market_segment in ('" . implode("','", $bodyForm['screen_option']['market_segment']['market_segment']) . "')" . " and m.company_code = '" . \Yii::$app->params['user_info']['company_code'] . "')";
            } else {
                $or[] = " (market_segment in ('" . implode("','", $bodyForm['screen_option']['market_segment']['market_segment']) . "')" . " and m.company_code = '" . \Yii::$app->params['user_info']['company_code'] . "')";
            }
        }
        if (isset($bodyForm['screen_option']['time'])) {
            if ($bodyForm['screen_option']['time']['logic'] == 'and') {
                if (isset($bodyForm['screen_option']['time']['create_time_start'])) {
                    $and[] = " sap_create_date > '" . $bodyForm['screen_option']['time']['create_time_start'] . "'";
                }
                if (isset($bodyForm['screen_option']['time']['create_time_end'])) {
                    $and[] = " sap_create_date < '" . $bodyForm['screen_option']['time']['create_time_end'] . "'";
                }
            } else {
                $time_where = '';
                if (isset($bodyForm['screen_option']['time']['create_time_start'])) {
                    $time_where = " sap_create_date > '" . $bodyForm['screen_option']['time']['create_time_start'] . "'";
                }
                if (isset($bodyForm['screen_option']['time']['create_time_end'])) {
                    if ($time_where) {
                        $time_where .= " and sap_create_date < '" . $bodyForm['screen_option']['time']['create_time_end'] . "'";
                    } else {
                        $time_where = " sap_create_date < '" . $bodyForm['screen_option']['time']['create_time_end'] . "'";
                    }
                }
                $or[] = $time_where;
            }
        }
        $and_sql = '';
        foreach ($and as $v) {
            if (!$and_sql) {
                $and_sql .= '(';
            } else {
                $and_sql .= ' AND ';
            }
            $and_sql .= $v;
        }
        $or_sql = '';
        foreach ($or as $v) {
            if ($or_sql) {
                $or_sql .= ' OR ';
            }
            $or_sql .= $v;
        }

        //剔除条件,剔除条件的条件要取反，并且and和or也要取反
        $and = [];
        $or = [];
        if (isset($bodyForm['delete_option']['delete_file'])) {
            $join .= ' LEFT JOIN ' . PlanStoreTmp::tableName() . ' p1 ON p1.store_id = ' . Store::tableName() . '.store_id AND p1.import_type = ' . PlanStoreTmp::IMPORT_TYPE_DELETE . ' AND p.plan_id = ' . $bodyForm['plan_id'];
            if ($bodyForm['delete_option']['delete_file']['logic'] == 'and') {
                $or[] = " ((p1.check_status != " . PlanStoreTmp::CHECK_STATUS_PASS . ") OR p1.store_id IS NULL)";
            } else {
                $and[] = " ((p1.check_status != " . PlanStoreTmp::CHECK_STATUS_PASS . ") OR p1.store_id IS NULL)";
            }
        }
        if (isset($bodyForm['delete_option']['region_code'])) {
            if ($bodyForm['delete_option']['region_code']['logic'] == 'and') {
                $or[] = " region_code not in ('" . implode("','", $bodyForm['delete_option']['region_code']['region_code']) . "')";
            } else {
                $and[] = " region_code not in ('" . implode("','", $bodyForm['delete_option']['region_code']['region_code']) . "')";
            }
        }
        if (isset($bodyForm['delete_option']['location_code'])) {
            if ($bodyForm['delete_option']['location_code']['logic'] == 'and') {
                $or[] = " location_code not in ('" . implode("','", $bodyForm['delete_option']['location_code']['location_code']) . "')";
            } else {
                $and[] = " location_code not in ('" . implode("','", $bodyForm['delete_option']['location_code']['location_code']) . "')";
            }
        }
        if (isset($bodyForm['delete_option']['market_channel'])) {
            if ($bodyForm['delete_option']['market_channel']['logic'] == 'and') {
                $or[] = " market_channel not in ('" . implode("','", $bodyForm['delete_option']['market_channel']['market_channel']) . "')";
            } else {
                $and[] = " market_channel not in ('" . implode("','", $bodyForm['delete_option']['market_channel']['market_channel']) . "')";
            }
        }
        if (isset($bodyForm['delete_option']['sub_channel_code'])) {
            if ($bodyForm['delete_option']['sub_channel_code']['logic'] == 'and') {
                $or[] = " sub_channel_code not in ('" . implode("','", $bodyForm['delete_option']['sub_channel_code']['sub_channel_code']) . "')";
            } else {
                $and[] = " sub_channel_code not in ('" . implode("','", $bodyForm['delete_option']['sub_channel_code']['sub_channel_code']) . "')";
            }
        }
        if (isset($bodyForm['delete_option']['route_code'])) {
            if ($bodyForm['delete_option']['route_code']['logic'] == 'and') {
                $or[] = " route_code not in ('" . implode("','", $bodyForm['delete_option']['route_code']['route_code']) . "')";
            } else {
                $and[] = " route_code not in ('" . implode("','", $bodyForm['delete_option']['route_code']['route_code']) . "')";
            }
        }
        if (isset($bodyForm['delete_option']['is_ka'])) {
            $is_ka = $bodyForm['delete_option']['is_ka']['is_ka'] ? 'N' : 'Y';
            if ($bodyForm['delete_option']['is_ka']['logic'] == 'and') {
                $or[] = " ka_indicator ='" . $is_ka . "'";
            } else {
                $and[] = " ka_indicator ='" . $is_ka . "'";
            }
        }
        if (isset($bodyForm['delete_option']['client_level'])) {
            if ($bodyForm['delete_option']['client_level']['logic'] == 'and') {
                $or[] = " market_segment_code not in ('" . implode("','", $bodyForm['delete_option']['client_level']['client_level']) . "')";
            } else {
                $and[] = " market_segment_code not in ('" . implode("','", $bodyForm['delete_option']['client_level']['client_level']) . "')";
            }
        }
        if (isset($bodyForm['delete_option']['has_icebox'])) {
            $has_icebox = $bodyForm['delete_option']['has_icebox']['has_icebox'] ? self::HAS_EQUIPMENT_NO : self::HAS_EQUIPMENT_YES;
            if ($bodyForm['delete_option']['has_icebox']['logic'] == 'and') {
//                $where[] = ['equipment_type' => $has_icebox];
                $or[] = ' has_equipment =' . $has_icebox;
            } else {
//                $where[] = ['or', ['equipment_type' => $has_icebox]];
                $and[] = ' has_equipment =' . $has_icebox;
            }
        }
        if (isset($bodyForm['delete_option']['market_segment'])) {
            if (!isset($join_segment)) {
                $join .= ' LEFT JOIN ' . $database . '.' . MarketSegment::tableName() . ' m ON m.town_code = ' . Store::tableName() . '.town_code';
            }
            if ($bodyForm['delete_option']['market_segment']['logic'] == 'and') {
                $or[] = " market_segment not in ('" . implode("','", $bodyForm['delete_option']['market_segment']['market_segment']) . "')";
            } else {
                $and[] = " market_segment not in ('" . implode("','", $bodyForm['delete_option']['market_segment']['market_segment']) . "')";
            }
        }
        if (isset($bodyForm['delete_option']['time'])) {
            if ($bodyForm['delete_option']['time']['logic'] == 'or') {
                if (isset($bodyForm['delete_option']['time']['create_time_start'])) {
                    $and[] = " sap_create_date < '" . $bodyForm['delete_option']['time']['create_time_start'] . "'";
                }
                if (isset($bodyForm['delete_option']['time']['create_time_end'])) {
                    $and[] = " sap_create_date > '" . $bodyForm['delete_option']['time']['create_time_end'] . "'";
                }
            } else {
                $time_where = '';
                if (isset($bodyForm['delete_option']['time']['create_time_start'])) {
                    $time_where = " sap_create_date <'" . $bodyForm['delete_option']['time']['create_time_start'] . "'";
                }
                if (isset($bodyForm['delete_option']['time']['create_time_end'])) {
                    if ($time_where) {
                        $time_where .= " and sap_create_date >'" . $bodyForm['delete_option']['time']['create_time_end'] . "'";
                    } else {
                        $time_where = " sap_create_date >'" . $bodyForm['delete_option']['time']['create_time_end'] . "'";
                    }
                }
                $or[] = $time_where;
            }
        }
        $del_and_sql = '';
        foreach ($and as $v) {
            if (!$del_and_sql) {
                $del_and_sql .= '(';
            } else {
                $del_and_sql .= ' AND ';
            }
            $del_and_sql .= $v;
        }
        $del_or_sql = '';
        foreach ($or as $v) {
            if ($del_or_sql) {
                $del_or_sql .= ' OR ';
            }
            $del_or_sql .= $v;
        }
        //拼接sql
        $and_sql = $and_sql ? $and_sql . ')' : '';
        $or_sql = $and_sql && $or_sql ? ' OR ' . $or_sql : $or_sql;
        $screen_sql = $and_sql . $or_sql;
        $del_and_sql = $del_and_sql ? $del_and_sql . ')' : '';
        $del_or_sql = $del_and_sql && $del_or_sql ? ' OR ' . $del_or_sql : $del_or_sql;
        $delete_sql = $del_and_sql . $del_or_sql;
        $screen_sql = $screen_sql ? '(' . $screen_sql . ') ' : $screen_sql;
        $and = $screen_sql && $delete_sql ? ' AND (' : '';
        $delete_sql = $screen_sql && $delete_sql ? $delete_sql . ')' : $delete_sql;
        if ($screen_sql) {
            $where_sql = '(' . $screen_sql . ')' . $and . $delete_sql;
        } else {
            $where_sql = $delete_sql ? '(' . $delete_sql . ')' : '';
        }

        $where_sql = $where_sql ? " WHERE " . Store::tableName() . ".company_code = '" . \Yii::$app->params['user_info']['company_code'] . "' AND bu_code = '" . \Yii::$app->params['user_info']['bu_code'] . "' AND " . $where_sql
            : '';

        $sql = $pagination === '' ? $sql1 . $join . $where_sql : $sql1 . $join . $where_sql . ' limit ' . $pagination . ',' . self::PAGINATION_FOR_SCREEN_STORE;

        return \Yii::$app->db->createCommand($sql)->queryAll();
    }

    public static function getLocationCode($where)
    {
        return $model = self::find()->select('location_code,route_code')->where($where)->limit(1)->one();
    }
}
