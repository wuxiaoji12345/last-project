<?php

namespace api\controllers;

use api\models\Question;
use api\models\QuestionBusinessType;
use api\models\QuestionOption;
use api\models\share\OrganizationRelation;
use api\models\share\Scene;
use api\models\User;
use Codeception\Util\HttpCode;
use Yii;
use yii\db\Expression;

class QuestionController extends BaseApi
{
    const ACCESS_ANY = ['drop-list', 'scene-type-list', 'business-type'];

    /**
     * 问卷查询列表
     * @return array
     */
    public function actionQuestionList()
    {
        $searchForm = Yii::$app->request->post();
        if (!$this->isPost() || !$this->check($searchForm, ['page', 'page_size'])) {
            return $this->error();
        }

        $select = [
            new Expression(Question::tableName() . '.company_code'),
            new Expression(Question::tableName() . '.bu_code'),
            new Expression(Question::tableName() . '.created_at'),
            new Expression(Question::tableName() . '.id'),
            'type',
            new Expression(Question::tableName() . '.scene_type_id'),
            'title',
            'question_type',
            'merge_type',
            'question_status',
            'is_ir',
            'required'
        ];
        $where = $searchForm;
        $pager = [
            'page' => $searchForm['page'],
            'page_size' => $searchForm['page_size']
        ];
        // 时间处理
        $where['created_start'] = strtotime($where['start_time']);
        $where['created_end'] = strtotime($where['end_time']);

        if (isset($where['end_time']) && $where['end_time'] != '') {
            $where['created_end'] = strtotime($where['end_time'] . ' 23:59:59');
        }
        $data = Question::getList($select, $where, $pager, true, ['created_at' => SORT_DESC, 'id' => SORT_DESC]);

        $sceneTypeList = Scene::getAll(['id', 'scene_code', 'scene_code_name']);

        $user = Yii::$app->params['user_info'];
        $bu = OrganizationRelation::companyBu();
        foreach ($data['list'] as &$datum) {
            $key = $datum['company_code'] . '_' . $datum['bu_code'];
            $datum['bu_name'] = isset($bu[$key]) ? $bu[$key] : User::COMPANY_CODE_ALL_LABEL;
            $datum['same_bu'] = $user['company_code'] == $datum['company_code'] && $user['bu_code'] == $datum['bu_code'];
            $datum['create_time'] = date('Y-m-d H:i', $datum['created_at']);
            $datum['question_type_label'] = Question::QUESTION_LABEL[$datum['question_type']];
            $datum['merge_type_label'] = Question::MERGE_TYPE_ARR[$datum['merge_type']];
            $datum['scene_type_label'] = isset($sceneTypeList[$datum['scene_type_id']]) ? $sceneTypeList[$datum['scene_type_id']]['scene_code_name'] : '';

        }
        return $data;
    }

    /**
     * 场景类型下拉列表
     * 全量不分页
     * @return array
     */
    public function actionSceneTypeList()
    {
        $data = Scene::findAllArray([], ['id', 'scene_code', 'scene_code_name']);
        return $data;
    }

    /**
     * 问卷状态启用禁用
     * @return array
     */
    public function actionSetStatus()
    {
        $searchForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($searchForm, ['question_id', 'question_status'])) {
            return $this->error();
        }
        $question_id = $searchForm['question_id'];
        $question_status = $searchForm['question_status'];
        $question = Question::findOne(['id' => $question_id], true);
        if ($question == null) {
            return $this->error('问卷id不存在');
        } else {
            $user = Yii::$app->params['user_info'];
            if ($user['company_code'] != $question->company_code || $user['bu_code'] != $question->bu_code) {
                return $this->error('不允许用户编辑非同厂房同BU的数据', HttpCode::FORBIDDEN);
            }
            $question->question_status = $question_status;
            if ($question->save(false)) {
                return $this->success();
            } else {
                $err = $question->getErrStr();
                return $this->error($err, -1);
            }
        }
    }

    /**
     * 新增问卷
     * @return array
     */
    public function actionCreate()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['title', 'question_type', 'type'])) {
            return $this->error();
        }
        $question = new Question();
        $question->load($bodyForm, '');
//        $scene_type_ids = $bodyForm['scenes_type_id'];

        // 售点问卷直接保存
        if ($question->type == Question::TYPE_STORE) {
            $question->scene_type_id = '';
            if ($question->save()) {
                return $this->success(['list' => [$question->id]]);
            } else {
                $err = $question->getErrStr();
                return $this->error($err, -1);
            }
        }

        if ($question->type == Question::TYPE_SCENE && empty($bodyForm['scenes_code']) && empty($bodyForm['scenes_type_id'])) {
            return $this->error('场景类型不能为空', -1);
        }
        $scenes = Scene::getSmallScene(['scenes_type_id' => $bodyForm['scenes_type_id'], 'scenes_code' => $bodyForm['scenes_code']]);

        $question->scene_type_id = $scenes[array_keys($scenes)[0]]['id'];
        unset($bodyForm['scene_type_id']);
        // 先验证1个能否通过校验
        if ($question->validate()) {
            $successIds = [];
            try {
                $tran = Yii::$app->db->beginTransaction();
                foreach ($scenes as $scene) {
                    $question = new Question();
                    $question->load($bodyForm, '');
                    $question->scene_type_id = $scene['id'];
                    $question->save();
                    $successIds[] = ['id' => $question->id];
                }
                $tran->commit();
                return $this->success(['list' => $successIds]);
            } catch (\Exception $e) {
                return $this->error($e->getMessage(), -1);
            }
        } else {

            $err = $question->getErrStr();
            return $this->error($err, -1);

        }
    }

    public function actionEdit()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['id', 'title', 'question_type', 'type'])) {
            return $this->error();
        }
        $question = Question::findOne(['id' => $bodyForm['id']], true);
        if ($question == null) {
            return $this->error('问卷id不存在');
        }
        $user = Yii::$app->params['user_info'];
        if ($user['company_code'] != $question->company_code || $user['bu_code'] != $question->bu_code) {
            return $this->error('不允许用户编辑非同厂房同BU的数据', HttpCode::FORBIDDEN);
        }

        $question->load($bodyForm, '');
        // 需要查scene_code对应的id
        $scene = Scene::findOneArray(['scene_code' => $bodyForm['scenes_code']]);
        if ($scene != null) {
            $question->scene_type_id = $scene['id'];
        }
        if ($question->type == Question::TYPE_SCENE && $question->scene_type_id == '') {
            return $this->error('场景类型不能为空', -1);
        }
        if ($question->save()) {
            return $this->success();
        } else {
            $str = $question->getErrStr();
            return $this->error($str);
        }
    }

    /**
     * 问卷查询详情
     * @return array
     */
    public function actionView()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['question_id'])) {
            return $this->error();
        }

        $select = [
            new Expression(Question::tableName() . '.id'),
            'title',
            'type',
            new Expression(Question::tableName() . '.scene_type_id'),
            'business_type_id',
            'question_type',
            'merge_type',
            'question_status',
            'is_ir',
            'required'
        ];
        $question = Question::getOneQuestion($select, $bodyForm['question_id']);
        if ($question == null) {
            return $this->error('问卷不存在');
        }
        $question['options'] = QuestionOption::findAllArray(['question_id'=> $question['id']], ['id', 'value']);

        if($question['business_type_id'] == 0){
            $question['business_type_id'] = '';
            $question['business_type_label'] = '';
        } else {
            $business_type = QuestionBusinessType::findOneArray(['id'=> $question['business_type_id']]);
            $question['business_type_label'] = $business_type['title'];
        }
        $sceneTypeList = Scene::getAll(['id', 'scene_code', 'scene_code_name']);
        $question['scene_code'] = $question['scene_type_id'] != 0 ? $sceneTypeList[$question['scene_type_id']]['scene_code'] : '';
        $question['scene_type_label'] = $question['scene_type_id'] != 0 ? $sceneTypeList[$question['scene_type_id']]['scene_code_name'] : '';
        $question['merge_type_label'] = Question::MERGE_TYPE_ARR[$question['merge_type']];
        unset($question['sceneType']);
        return $this->success($question);
    }

    /**
     * 问卷列表查询
     * @return array
     */
    public function actionViewList()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost()) {
            return $this->error();
        }

        $select = [
            new Expression(Question::tableName() . '.id'),
            'title',
            'type',
            new Expression(Question::tableName() . '.scene_type_id'),
            'question_type',
            'merge_type',
            'question_status',
            'is_ir'
        ];

        $questions = Question::find()->select($select)
            ->andWhere(['=', new Expression(Question::tableName() . '.status'), Question::DEL_STATUS_NORMAL])
            ->andFilterWhere(['scene_type_id' => $bodyForm['scene_type_id']])
            ->andFilterWhere(['type' => $bodyForm['type']])
            ->andFilterWhere(['is_ir' => $bodyForm['is_ir']])
            ->andFilterWhere(
                ['or',
                    ['=', new Expression(Question::tableName() . '.id'), $bodyForm['question_id']],
                    ['like', new Expression(Question::tableName() . '.title'), $bodyForm['question_id']]
                ]
            )
            ->orderBy(['created_at' => SORT_DESC])
            ->asArray()->all();

        $sceneTypeList = Scene::getAll(['id', 'scene_code', 'scene_code_name']);

        if (!empty($questions)) {
            foreach ($questions as &$question) {
                $question['scene_type_label'] = isset($sceneTypeList[$question['scene_type_id']]) ? $sceneTypeList[$question['scene_type_id']]['scene_code_name'] : '';
                $question['merge_type_label'] = Question::MERGE_TYPE_ARR[$question['merge_type']];
                unset($question['sceneType']);
            }
        }
        return $this->success(['list' => $questions]);
    }

    public function actionDelete()
    {
        $bodyForm = Yii::$app->request->post();

        if (!$this->isPost() || !$this->check($bodyForm, ['question_id'])) {
            return $this->error();
        }

        $question = Question::findOne(['id' => $bodyForm['question_id']], true);
        if ($question == null) {
            return $this->error('问卷不存在');
        }
        $user = Yii::$app->params['user_info'];
        if ($user['company_code'] != $question->company_code || $user['bu_code'] != $question->bu_code) {
            return $this->error('不允许用户编辑非同厂房同BU的数据', HttpCode::FORBIDDEN);
        }

        $question->status = Question::DEL_STATUS_DELETE;
        $question->save();

        return $this->success();
    }

    public function actionDropList()
    {
//        $questions = Question::findAllArray(['question_status' => [Question::QUESTION_STATUS_ACTIVE, Question::QUESTION_STATUS_DISABLE]], ['id', 'title'], '', true);
//        $questions = Question::findAllArray([['<>', 'question_status', Question::QUESTION_STATUS_DEFAULT]], ['id', 'title'], '', true);
        $where = ['question_status' => [Question::QUESTION_STATUS_ACTIVE, Question::QUESTION_STATUS_DISABLE]];
        $where = Question::normalFilter($where, true);
        $query = Question::find()
            ->select(['id', 'title'])
            ->where($where)
            ->andWhere([])
            ->groupBy('title')
            ->asArray();
        $questions = $query->all();

        return $this->success(['list' => $questions]);
    }

    public function actionBusinessType(){
        return QuestionBusinessType::findAllArray([], ['id', 'title'], '', false, 'sort desc, id asc');
    }
}
