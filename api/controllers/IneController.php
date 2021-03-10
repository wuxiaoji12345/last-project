<?php

namespace api\controllers;

use api\models\CheckType;
use api\models\IneChannel;
use api\models\RuleOutputInfo;
use api\models\Standard;
use api\service\ine\IneChannelService;
use api\service\ine\IneConfigService;
use Codeception\Util\HttpCode;
use Yii;
use yii\helpers\ArrayHelper;

class IneController extends BaseApi
{
    const ACCESS_ANY = [
        'standard-list',
        'rule-output-list',
    ];

    //INE配置模块仅对央服具有编辑权限的人员开放
    public function beforeAction($action)
    {
        $result = parent::beforeAction($action);
        if (!ArrayHelper::getValue(Yii::$app->params, 'user_is_3004', '')) {
            Yii::$app->response->data = $this->error('没有权限', HttpCode::UNAUTHORIZED);
            return false;
        }
        return $result;
    }

    /**
     * 配置入口--渠道列表
     */
    public function actionIndex()
    {
        return $this->success(IneChannelService::getAllGroupByYear());
    }

    /**
     * 配置详情
     */
    public function actionDetail()
    {
        //参数校验，ine渠道id必传
        $form = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($form, ['ine_channel_id'])) {
            return $this->error();
        }
        //获取ine渠道详情
        $ine_channel_detail = IneChannelService::getOneById($form['ine_channel_id']);
        //获取所有ine渠道配置
        $ine_config_list = IneConfigService::getListTreeByChannelId($form['ine_channel_id']);
        //获取检查项目详情
        $standard = Standard::findOneArray(['id' => $ine_channel_detail['standard_id']]);
        //拼接返回参数
        $detail = [
            'ine_channel_id' => $ine_channel_detail['id'],
            'standard_id' => $ine_channel_detail['standard_id'],
            'standard_name' => ArrayHelper::getValue($standard, 'title', ''),
            'is_ine' => $ine_channel_detail['is_ine'],
            'ine_status' => $ine_channel_detail['ine_status'],
            'config_list' => $ine_config_list
        ];
        return $this->success($detail);
    }

    /**
     * ine渠道检查项目列表
     */
    public function actionStandardList()
    {
        $form = Yii::$app->request->post();
        $where = [];
        $table = Standard::tableName();
        $where[] = 'and';
        $where[] = ['=', $table . '.check_type_id', CheckType::INE['check_type_id']];
        $where[] = ['=', $table . '.standard_status', Standard::STATUS_AVAILABLE];
        $where[] = ['=', $table . '.is_deleted', Standard::NOT_DELETED];
        if (!empty($form['title'])) {
            $where[] = ['like', $table . '.title', $form['title']];
        }
        //获取所有，不分页
        $standard_list = Standard::getStandardAll($where, 0, 0);
        //获取所有已发布的ine渠道
        $ine_channel_list = IneChannel::findAllArray(['ine_status' => IneChannel::INE_STATUS_PUBLISHED]);
        //去除所有已绑定的检查项目
        foreach ($standard_list['list'] as $key => $standard) {
            foreach ($ine_channel_list as $ine_channel) {
                if ($standard['standard_id'] == $ine_channel['standard_id']) {
                    unset($standard_list['list'][$key]);
                    $standard_list['count']--;
                }
            }
        }
        $standard_list['list'] = array_values($standard_list['list']);
        return $this->success($standard_list);
    }

    /**
     * 规则引擎输出项列表
     */
    public function actionRuleOutputList()
    {
        //参数校验，standard_id必传
        $form = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($form, ['standard_id'])) {
            return $this->error();
        }
        $select = ['id', 'node_index', 'node_name', 'scene_type', 'scene_code', 'is_all_scene', 'output_type'];
        $rule_output_list = RuleOutputInfo::find()->select($select)
            ->where(['standard_id' => $form['standard_id'], 'status' => RuleOutputInfo::DEL_STATUS_NORMAL])
            ->groupBy('node_index')->asArray()->all();
        return $this->success(IneConfigService::joinSceneNameWithOutput($rule_output_list));
    }

    /**
     * 配置暂存
     */
    public function actionSave()
    {
        //参数校验，ine_channel_id、standard_id、config_list必传
        $form = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($form, ['ine_channel_id', 'standard_id', 'config_list'])) {
            return $this->error();
        }
        try {
            $result = IneConfigService::save($form['ine_channel_id'], $form['standard_id'], $form['config_list']);
            return $this->success($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 配置保存并生效
     */
    public function actionPublish()
    {
        //参数校验，ine_channel_id、standard_id、config_list必传
        $form = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($form, ['ine_channel_id', 'standard_id', 'config_list'])) {
            return $this->error();
        }
        try {
            $result = IneConfigService::publish($form['ine_channel_id'], $form['standard_id'], $form['config_list']);
            return $this->success($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}