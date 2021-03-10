<?php

namespace api\models;

use api\models\share\ChannelMain;
use api\models\share\ChannelSub;
use Yii;
use yii\data\Pagination;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * This is the model class for table "{{%survey}}".
 *
 * @property int $id 主键id
 * @property string $survey_code 走访code
 * @property string $store_id 售点id
 * @property int $sub_activity_id 子活动id
 * @property int $plan_id 指定检查计划id （针对特定计划走访）
 * @property int $sub_channel_id 次渠道类型id
 * @property string $sub_channel_name 次渠道类型
 * @property int $tool_id 执行工具id
 * @property string $survey_time 实际走访时间
 * @property string $survey_date 走访日期
 * @property int $create_type 生成来源，0批量，1单次生成
 * @property string $outside_task_id 外部批次号
 * @property string $examiner 检查人
 * @property int $examiner_id 检查人编号
 * @property int $survey_status 走访状态：0开始，1结束
 * @property int $send_engine 发送规则引擎状态：0未发送，1已发送，2发送超时，3引擎结果已返回，4没有命中计划
 * @property int $is_ir 是否ir走访 1是 ，0否
 * @property int $is_ine 0非INE，1是INE
 * @property string $company_code 厂房code
 * @property string $bu_code bu_code
 * @property string $location_name 营业所名称
 * @property string $supervisor_name 主任名称
 * @property string $route_code 线路编号
 * @property int $is_inventory 是否清单店 1是0否
 * @property string $ine_channel_id ine渠道id
 * @property string $store_name 非清单店售点名称
 * @property string $store_address 非清单店售点地址
 * @property int $year 指定ine规则的年份
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 * @property string $region_code 大区编码
 */
class Survey extends baseModel
{
    const STATUS_FIELD = 'survey_status';       // 走访状态字段
    const SURVEY_START = 0;     // 走访开始
    const SURVEY_END = 1;      // 走访结束

    const IS_IR_YES = 1;
    const IS_IR_NO = 0;

    const IS_INE_YES = 1;
    const IS_INE_NO = 0;

    const CHECK_TYPE_INE_ID = 1; //INE在check_type表的id

    const SEND_ENGINE_NO = 0;
    const SEND_ENGINE_YES = 1;
    const SEND_ENGINE_TIME_OUT = 2;
    const SEND_ENGINE_HAS_RESULT = 3;
    const NOT_HIT_RULE = 4;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%survey}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['store_id', 'tool_id'], 'required'],
            [['sub_activity_id', 'plan_id', 'sub_channel_id', 'tool_id', 'create_type', 'examiner_id', 'survey_status',
                'send_engine', 'is_ir', 'is_ine', 'is_inventory', 'status', 'created_at', 'updated_at', 'year'], 'integer'],
            [['survey_time', 'survey_date', 'update_time'], 'safe'],
            [['survey_code'], 'string', 'max' => 100],
            [['store_id', 'company_code', 'bu_code'], 'string', 'max' => 16],
            [['sub_channel_name'], 'string', 'max' => 20],
            [['outside_task_id'], 'string', 'max' => 64],
            [['examiner', 'location_name', 'supervisor_name', 'store_name', 'ine_channel_id'], 'string', 'max' => 50],
            [['route_code'], 'string', 'max' => 4],
            [['region_code'], 'string', 'max' => 15],
            [['store_address'], 'string', 'max' => 200],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'survey_code' => '走访code',
            'store_id' => '售点id',
            'sub_activity_id' => '子活动id',
            'plan_id' => '指定检查计划id （针对特定计划走访）',
            'sub_channel_id' => '次渠道类型id',
            'sub_channel_name' => '次渠道类型',
            'tool_id' => '执行工具id',
            'survey_time' => '实际走访时间',
            'survey_date' => '走访日期',
            'create_type' => '生成来源，0批量，1单次生成',
            'outside_task_id' => '外部批次号',
            'examiner' => '检查人',
            'examiner_id' => '检查人编号',
            'survey_status' => '走访状态：0开始，1结束',
            'send_engine' => '发送规则引擎状态：0未发送，1已发送，2发送超时，3引擎结果已返回，4没有命中计划',
            'is_ir' => '是否ir走访 1是 ，0否',
            'is_ine' => '0非INE，1是INE',
            'company_code' => '厂房code',
            'bu_code' => 'bu_code',
            'location_name' => '营业所名称',
            'supervisor_name' => '主任名称',
            'route_code' => '线路编号',
            'is_inventory' => '是否清单店 1是0否',
            'ine_channel_id' => 'ine渠道id',
            'store_name' => '非清单店售点名称',
            'store_address' => '非清单店售点地址',
            'year' => '指定ine规则的年份',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
            'region_code' => '大区编码',
        ];
    }

    public function getSurveyQuestion()
    {
        return $this->hasOne(SurveyQuestion::class, ['survey_id' => 'id']);
    }

    public function getTool()
    {
        return $this->hasOne(Tools::class, ['id' => 'tool_id']);
    }

    public function getStore()
    {
        return $this->hasOne(Store::class, ['store_id' => 'store_id']);
    }

    public function getImage()
    {
        return $this->hasMany(Image::class, ['survey_code' => 'survey_code']);
    }

    public function getIneConfigSnapshot()
    {
        return $this->hasMany(IneConfigSnapshot::class, ['ine_config_timestamp_id' => 'ine_config_timestamp_id']);
    }
    /**
     * 生成走访记录并返回走访号
     * @param $store    Store   // 售点对象
     * @return Survey
     */
    public static function generateSurvey($store)
    {
        // 先生成 $survey_code
        $survey_code = self::generateSurveyCode();
        $survey = new Survey();
        $survey->survey_code = $survey_code;
        $survey->store_id = $store->store_id;
        return $survey;
    }

    /**
     * 先生成走访号
     * @return string
     */
    public static function generateSurveyCode()
    {
        $time = microtime(true);
        $queueName = Yii::$app->params['project_id'] . '_' . Yii::$app->params['queue_survey_id'] . $time;
        $survey_code = Yii::$app->remq->incr($queueName);
        Yii::$app->remq->expire($queueName, 5);
        return $time . str_pad($survey_code, 4, '0', STR_PAD_LEFT);
    }

    /**
     * 修改走访状态为完成
     * @param $where
     * @param $data
     * @return array
     */
    public static function doSurveyFinish($where, $data)
    {
        $model = self::findOne($where);
        if ($model) {
            //为了幂等做判断，第三个参数为done的话为之前有传过
            if ($model->survey_status == self::SURVEY_END) {
                return [true, $model->attributes['id'], 'done', $model->attributes, $model];
            }
            $model->survey_status = self::SURVEY_END;
            $model->load($data, '');
            if ($model->save()) {
                return [true, $model->attributes['id'], '', $model->attributes, $model];
            } else {
                return [false, $model->getErrors(), '', '', $model];
            }
        } else {
            return [false, '走访号不存在', '', '', $model];
        }
    }

    public static function getIdByCode($code)
    {
        $model = self::find()->where(['survey_code' => $code])->asArray()->one();
        if ($model) {
            return [true, $model];
        } else {
            return [false, 'id不存在'];
        }
    }

    public static function getCode($where)
    {
        return $model = self::find()->where($where)->limit(1)->one();
    }

    /**
     * 存储执行工具端传来的走访号
     * @param $param
     * @return array
     */
    public static function saveSurvey($param)
    {
        $model = self::findOne(['survey_code' => $param['survey_code']]);
        if (!$model) {
            $model = new self();
        } else {
            if ($model->survey_status == 1) {
                return [false, '走访:' . $model->survey_code . '已结束，无法再次提交'];
            }
        }
        $model->load($param, '');
        if ($model->save()) {
            return [true, $model->attributes['id']];
        } else {
//            return [false, '走访号存储失败，请检查'];
            return [false, $model->getErrors()];
        }
    }

    /**
     * 获取检查结果列表
     * @param $where
     * @param $pageSize
     * @param $page
     * @return array|ActiveRecord[]
     */
    public static function getReportList($where, $pageSize, $page)
    {
        $user_info = Yii::$app->params['user_info'];
        $bu_condition = User::getBuCondition(self::class,
            $user_info['company_code'],
            $user_info['bu_code'],
            !Yii::$app->params['user_is_3004']
        );
        if (!empty($bu_condition))
            $where = ['and', $where, $bu_condition];
        if (empty($page) || $page < 0) {
            $list = self::find()
                ->leftJoin('sys_tools t', 't.id = sys_survey.tool_id')
//                ->leftJoin($database.'.sys_store', 'sys_store.store_id = s.store_id')
                ->select(['survey_time', 'survey_code', 't.name tool_name', 'sub_channel_name', 'store_id',
                    'location_name', 'supervisor_name', 'route_code',
                    'company_code', 'bu_code', 'is_inventory'])
                ->andWhere($where)
                ->orderBy('survey_time DESC')
                ->asArray()
                ->all();
            return [
                'list' => $list,
                'count' => count($list)
            ];
        } else {
            $page -= 1;
            $pages = new Pagination(['pageSize' => $pageSize, 'page' => $page]);
            $query = self::find();
            $list = $query->leftJoin('sys_tools t', 't.id = sys_survey.tool_id')
//                ->leftJoin($database.'.sys_store', 'sys_store.store_id = s.store_id')
                ->select(['survey_time', 'survey_code', 't.name tool_name', 'sub_channel_name', 'store_id',
                    'location_name', 'supervisor_name', 'route_code',
                    'company_code', 'bu_code', 'is_inventory'])
                ->andWhere($where);
            $count = $list->count();
            $list = $list->offset($pages->offset)->limit($pages->limit)
                ->orderBy('survey_time DESC')
                ->asArray()
                ->all();
            return [
                'list' => $list,
                'count' => (int)$count
            ];
        }
    }

    /**
     * 设置用户信息条件
     * @param $where
     */
    public static function setBu(&$where)
    {
        if (isset(Yii::$app->request->bodyParams['company_bu']) && !empty(Yii::$app->request->bodyParams['company_bu'])) {
            $company_bus = Yii::$app->request->bodyParams['company_bu'];
            foreach ($company_bus as $v) {
                $company_bu = explode('_', $v);
                $company_code[] = $company_bu[0];
                if (isset($company_bu[1])) {
                    $bu_code[] = $company_bu[1];
                }
            }
            $where[] = ['in', 's.company_code', $company_code];
            if (!empty($bu_code)) {
                $where[] = ['in', 's.bu_code', $bu_code];
            }
        }
    }

    /**
     * 设置用户信息条件
     * @param $where
     */
    public static function setUserInfo(&$where)
    {
        $user_info = Yii::$app->params['user_info'];
        $bu_condition = User::getBuCondition(self::class,
            $user_info['company_code'],
            $user_info['bu_code'],
            !Yii::$app->params['user_is_3004'],
            's'
        );
        if (!empty($bu_condition))
            $where = ['and', $where, $bu_condition];

    }

    /**
     * 获取走访列表
     * @param $where
     * @param $page
     * @param $pageSize
     * @param array $select
     * @param string $order
     * @return array
     */
    public static function getInterview($where, $page, $pageSize, $select = [], $order = 'survey_time DESC')
    {
        if (empty($select)) {
            $select = ['s.id', 's.survey_code', 's.examiner', 's.survey_time', 's.store_id', 's.store_name', 's.store_address', 's.sub_channel_id', 'c.year', 'r.ine_total_points'];
        }
        $select[] = 's.survey_status status';
        $select[] = 'i.ine_channel_id ine_channel_id';
        $select[] = 'c.channel_name';
        $query = self::find()->alias('s')
            ->leftJoin('sys_survey_ine_channel i', 'i.survey_code=s.survey_code')
            ->leftJoin('sys_ine_channel c', 'c.id=i.ine_channel_id')
            ->leftJoin('sys_engine_result r', 'r.survey_code=s.survey_code')
            ->select($select)->groupBy('r.id')
            ->where($where);
        if (empty($page) || $page < 0) {
            $count = $query->count();
        } else {
            $page -= 1;
            $count = $query->count();
            $query = $query->offset($page * $pageSize)->limit($pageSize);
        }

        $list = $query->orderBy($order)
            ->asArray()
            ->all();
//        $sql = $query->createCommand()->getRawSql();
        $ine_channels = IneChannel::find()->indexBy('id')->select(['id','channel_name'])->asArray()->all();
        $storeIds = array_column($list, 'store_id');
        $stores = \api\models\share\Store::find()->where(['in', 'store_id', $storeIds])->select(['store_id', 'address'])->indexBy('store_id')->asArray()->all();
        // 替换次渠道
        foreach ($list as &$val) {
            $val['store_address'] = isset($val['store_id']) && isset($stores[$val['store_id']]['address']) ? $stores[$val['store_id']]['address'] : $val['store_address'];
        }

        return [
            'list' => $list,
            'count' => (int)$count
        ];
    }

    /**
     * 获得单个走访号所有详情
     * @param $where
     * @return array|ActiveRecord[]
     */
    public static function getSurveyInfo($where)
    {
        $user_info = Yii::$app->params['user_info'];
        $bu_condition = User::getBuCondition(Store::class,
            $user_info['company_code'],
            $user_info['bu_code'],
            !Yii::$app->params['user_is_3004']
        );
        if (!empty($bu_condition))
            $where = ['and', $where, $bu_condition];
        return $list = self::find()->alias('s')
            ->leftJoin('sys_store', 'sys_store.store_id = s.store_id')
            ->leftJoin('sys_image i', 'i.survey_code = s.survey_code')
            ->select(['s.survey_time', 's.survey_date', 'i.scene_id_name', 'i.scene_code', 's.store_id', 's.store_name', 'i.id photo_id'
            ])
            ->andWhere($where)
            ->orderBy('i.id DESC')
            ->asArray()
            ->all();
    }

    /**
     * 查询走访号列表
     * @param $where
     * @param $pageSize
     * @param $page
     * @return array|ActiveRecord[]
     */
    public static function getSurveyList($where, $pageSize, $page)
    {
        $user_info = Yii::$app->params['user_info'];
        $bu_condition = User::getBuCondition(self::class,
            $user_info['company_code'],
            $user_info['bu_code'],
            !Yii::$app->params['user_is_3004']
        );
        if (!empty($bu_condition))
            $where = ['and', $where, $bu_condition];
        if (empty($page) || $page < 0) {
            return $list = self::find()
                ->select(['survey_code'])
                ->andWhere($where)
                ->orderBy('created_at DESC')
                ->asArray()
                ->all();
        } else {
            $page -= 1;
            $pages = new Pagination(['pageSize' => $pageSize, 'page' => $page]);
            $query = self::find();
            return $list = $query->offset($pages->offset)->limit($pages->limit)
                ->select(['survey_code'])
                ->andWhere($where)
                ->orderBy('created_at DESC')
                ->asArray()
                ->all();
        }
    }

    /**
     * 查询走访号信息，此处为冗余，可以用公用方法findOneArray
     * @param $survey_code
     * @return array|null|ActiveRecord
     */
    public static function getSurveyStore($survey_code)
    {
        return $list = self::find()
            ->select(['store_id', 'survey_time', 'tool_id', 'id', 'plan_id', 'sub_activity_id', 'is_ir', 'examiner_id', 'ine_channel_id'])
            ->where(['survey_code' => $survey_code])
//            ->orderBy('id desc')
            ->asArray()
            ->one();
    }

    public static function findAllSurveyInfo($where)
    {
        return self::find()->alias('s')
            ->leftJoin('sys_image i', 'i.survey_code = s.survey_code')
            ->leftJoin('sys_image_url iu', 'iu.image_id = i.id')
            ->select(['s.survey_time', 's.survey_code', 'i.standard_id', 'i.scene_id_name', 'i.scene_id', 'i.scene_code', 'iu.image_url'])
            ->where($where)
//            ->groupBy('i.standard_id')
            ->orderBy('s.survey_time desc')
//            ->orderBy('id desc')
            ->asArray()
            ->all();
    }

    /**
     * 返回单个售点已检查次数
     * @param $store_id //检查售点
     * @param $standard_id //检查项目id
     * @param $tool_id //执行工具id
     * @param $cycle_list //ZFT协议返回字段：执行周期时间段列表
     * @param $time //用于判断走访归于哪个周期的时间
     * @param null $plan_cycle //plan的短周期时间
     * @return array
     */
    public static function findCountSurvey($store_id, $standard_id, $tool_id, $cycle_list, $time, $plan_cycle = null)
    {
        $where = [];
        $last_start = '';
        //是否空档期
        $is_neutral = false;
        //短周期时间数组
        $short_cycle = [];
        if ($cycle_list) {
            foreach ($cycle_list as $v) {
                $start_data = date('Y-m-d', strtotime($v['cycleFromDate']));
                $end_data = date('Y-m-d', strtotime($v['cycleToDate']));
                $short_cycle[] = [
                    'start_data' => $start_data,
                    'end_data' => $end_data,
                ];
                $start = $start_data . ' 00:00:00';
                $end = $end_data . ' 23:59:59';
                if ($time > $end) {
                    $last_start = $start;
                }
                if ($time > $start && $time < $end) {
//                    $cycle_id = $v['cycleID'];
                    //若不在空档期，则查询当前周期的所有检查结果
                    $is_neutral = true;
                    $where[] = 'and';
                    $where[] = ['>', 's.survey_time', $start];
                    $where[] = ['<', 's.survey_time', $end];
                }
            }
        } elseif (!$cycle_list && $plan_cycle) {
            foreach ($plan_cycle as $v) {
                $start = $v['start_time'] . ' 00:00:00';
                $end = $v['end_time'] . ' 23:59:59';
                if ($time > $end) {
                    $last_start = $start;
                }
                if ($time > $start && $time < $end) {
                    //若不在空档期，则查询当前周期的所有检查结果
                    $is_neutral = true;
                    $where[] = 'and';
                    $where[] = ['>', 's.survey_time', $start];
                    $where[] = ['<', 's.survey_time', $end];
                }
            }
            $short_cycle = $plan_cycle;
        } else {
            $where = ['and', ['<', 's.survey_time', $time]];
        }
        //若在空档期，则查询前一检查周期的所有检查结果
        if ($last_start) {
            $where = $where ? $where : ['and', ['>', 's.survey_time', $last_start], ['<', 's.survey_time', $time]];
        } else {
            //此种情况应该不会出现
            $where = $where ? $where : ['and', ['<', 's.survey_time', $time]];
        }
        $alias = 's';
        $join = [
            ['type' => 'JOIN',
                'table' => Image::tableName() . ' i',
                'on' => 'i.survey_code = s.survey_code'],
            ['type' => 'LEFT JOIN',
                'table' => SubActivity::tableName() . ' sa',
                'on' => 'i.sub_activity_id = sa.id'],
            ['type' => 'LEFT JOIN',
                'table' => EngineResult::tableName() . ' e',
                'on' => 'e.survey_code = s.survey_code'],
            ['type' => 'LEFT JOIN',
                'table' => Plan::tableName() . ' p',
                'on' => 'e.plan_id = p.id'],
        ];
        $select = ['s.survey_code', 's.survey_time', 'sa.activation_name', 'i.sub_activity_id', 'p.status'];
        $where[] = ['s.store_id' => $store_id];
        $where[] = ['i.standard_id' => $standard_id];
        $where[] = ['s.tool_id' => $tool_id];
        $survey_result = self::findJoin($alias, $join, $select, $where);
        $survey_list = $survey_result;
        //去掉已删除的plan的走访，不计走访数
        foreach ($survey_list as $k => $v){
            if($v['status'] == Plan::DEL_STATUS_DELETE){
                unset($survey_list[$k]);
            }
        }
        $survey_list = array_column($survey_list, 'survey_code');
        $survey_list = array_unique($survey_list);
//        $is_neutral = $last_start ? true : false;
        return [count($survey_list), $survey_result, $is_neutral, $short_cycle];
    }

    /**
     * {@inheritdoc}
     * @return bQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new bQuery(get_called_class());
    }

    /**
     * 考虑重推的，所以执行覆盖策略
     * @param $param
     * @return array
     */
    public static function saveSurveyCover($param)
    {
        $model = self::findOne(['survey_code' => $param['survey_code']]);
        if (!$model) {
            $model = new self();
        }
        $model->load($param, '');
        if ($model->save()) {
            return [true, $model->attributes['id']];
        } else {
            return [false, '走访号存储失败，请检查'];
        }
    }

    public static function getReportImage($where, $page, $page_size)
    {
        $user_info = Yii::$app->params['user_info'];
        $bu_condition = User::getBuCondition(self::class,
            $user_info['company_code'],
            $user_info['bu_code'],
            !Yii::$app->params['user_is_3004'],
            's'
        );
        if (!empty($bu_condition))
            $where = ['and', $where, $bu_condition];


        $alias = 's';
        $join = [];
        $join[] = [
            'type' => 'JOIN',
            'table' => EngineResult::tableName() . ' e',
            'on' => 's.survey_code = e.survey_code'
        ];
//        $join[] = [
//            'type' => 'JOIN',
//            'table' => Store::tableName() . ' st',
//            'on' => 's.store_id = st.store_id'
//        ];
        $join[] = [
            'type' => 'LEFT JOIN',
            'table' => Standard::tableName() . ' sta',
            'on' => 'sta.id = e.standard_id'
        ];
        $select = ['s.survey_time', 's.store_name', 's.survey_code', 'e.is_rectify', 's.route_code', 's.store_id', 's.is_inventory'];
        $with = [['image' => function ($query) {
            $query->select('id, scene_id_name, created_at, sub_activity_id, survey_code, status, img_prex_key, number');
        }], ['image.subActivity' => function ($query) {
            $query->select('id, activation_id, activation_name');
        }], ['image.imageUrl' => function ($query) {
            $query->alias('u')->leftJoin('sys_question q', 'q.id=u.question_id')
                ->select('u.id, u.image_id, u.image_url,u.image_key,u.rebroadcast_status,u.is_rebroadcast,u.similarity_status,u.is_similarity,u.status, u.img_type, q.title question')
                ->where(['u.status' => ImageUrl::DEL_STATUS_NORMAL]);
        }]];
        return self::findJoin($alias, $join, $select, $where, true, true, 's.survey_time DESC', '', '', $with, ['page' => $page, 'page_size' => $page_size]);
    }

    /**
     * 不带走访的图片查看
     * @param $where
     * @param $page
     * @param $page_size
     * @return array|null|string|ActiveRecord|ActiveRecord[]
     */
    public static function getReportImageNotSurvey($where, $page, $page_size)
    {
        $user_info = Yii::$app->params['user_info'];
        $bu_condition = User::getBuCondition(self::class,
            $user_info['company_code'],
            $user_info['bu_code'],
            !Yii::$app->params['user_is_3004'],
            's'
        );
        if (!empty($bu_condition))
            $where = ['and', $where, $bu_condition];


        $alias = 's';
        $join = [];
        $join[] = [
            'type' => 'JOIN',
            'table' => EngineResult::tableName() . ' e',
            'on' => 's.survey_code = e.survey_code'
        ];
        $join[] = [
            'type' => 'JOIN',
            'table' => Image::tableName() . ' i',
            'on' => 's.survey_code = i.survey_code'
        ];
//        $join[] = [
//            'type' => 'JOIN',
//            'table' => Store::tableName() . ' st',
//            'on' => 's.store_id = st.store_id'
//        ];
        $join[] = [
            'type' => 'LEFT JOIN',
            'table' => SubActivity::tableName() . ' sa',
            'on' => 'sa.id = i.sub_activity_id'
        ];
        $join[] = [
            'type' => 'LEFT JOIN',
            'table' => Standard::tableName() . ' sta',
            'on' => 'sta.id = e.standard_id'
        ];
        $join[] = [
            'type' => 'JOIN',
            'table' => ImageUrl::tableName() . ' iu',
            'on' => 'i.id = iu.image_id'
        ];
        $join[] = [
            'type' => 'LEFT JOIN',
            'table' => Question::tableName() . ' q',
            'on' => 'q.id = iu.question_id'
        ];
        $select = [
            's.survey_time',
            's.store_name',
            's.survey_code',
            'e.is_rectify',
            's.route_code',
            's.store_id',
            'sa.activation_id',
            'sa.activation_name',
            'i.scene_id_name',
            'i.created_at',
            'i.img_prex_key',
            'iu.img_type',
            'iu.image_id',
            'iu.image_url',
            'iu.image_key',
            'iu.rebroadcast_status',
            'iu.is_rebroadcast',
            'iu.similarity_status',
            'iu.is_similarity',
            's.is_inventory',
            'q.title question',
        ];
        return self::findJoin($alias, $join, $select, $where, true, true, 'i.created_at DESC', '', '', '', ['page' => $page, 'page_size' => $page_size]);
    }

    /**
     * 不带走访的图片查看
     * @param $where
     * @param $page
     * @param $page_size
     * @return array|null|string|ActiveRecord|ActiveRecord[]
     */
    public static function getReportImageNotSurveyDown($where, $page, $page_size)
    {
        $user_info = Yii::$app->params['user_info'];
        $bu_condition = User::getBuCondition(self::class,
            $user_info['company_code'],
            $user_info['bu_code'],
            !Yii::$app->params['user_is_3004'],
            's'
        );
        if (!empty($bu_condition))
            $where = ['and', $where, $bu_condition];

        $alias = 's';
        $join = [];
        $join[] = [
            'type' => 'JOIN',
            'table' => EngineResult::tableName() . ' e',
            'on' => 's.survey_code = e.survey_code'
        ];
        $join[] = [
            'type' => 'JOIN',
            'table' => Image::tableName() . ' i',
            'on' => 's.survey_code = i.survey_code'
        ];
//        $join[] = [
//            'type' => 'LEFT JOIN',
//            'table' => SubActivity::tableName() . ' sa',
//            'on' => 'sa.id = i.sub_activity_id'
//        ];
        $join[] = [
            'type' => 'LEFT JOIN',
            'table' => Standard::tableName() . ' sta',
            'on' => 'sta.id = e.standard_id'
        ];
        $join[] = [
            'type' => 'JOIN',
            'table' => ImageUrl::tableName() . ' iu',
            'on' => 'i.id = iu.image_id'
        ];
        $join[] = [
            'type' => 'JOIN',
            'table' => Tools::tableName() . ' t',
            'on' => 't.id = s.tool_id'
        ];
//        $pre = Yii::$app->params['cos_url'];
        $select = [
            's.survey_time',
            's.store_name',
            's.survey_code',
            't.name as tool_name',
            'sta.title as sta_title',
            'i.standard_id',
            'i.scene_id',
            "if(e.is_rectify,'是','否') as is_rectify",
            's.route_code',
            's.store_id',
            "if(iu.is_rebroadcast=0,'正常','翻拍') as is_rebroadcast",
            "if(iu.is_similarity=0,'正常','相似') as is_similarity",
            "iu.image_url",
        ];
        return self::findJoin($alias, $join, $select, $where, true, true, 'i.created_at DESC', '', '', '', ['page' => $page, 'page_size' => $page_size]);
    }
}