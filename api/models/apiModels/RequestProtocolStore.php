<?php


namespace api\models\apiModels;


class RequestProtocolStore extends apiBaseModel
{
    public $contract_id;
    public $standard_id;
    public $tool_id;
    public $page;
    public $page_size;

    public function rules()
    {
        return [
            [['tool_id', 'page', 'page_size'], 'required'],
            [['tool_id', 'contract_id', 'standard_id', 'page'], 'integer'],
            [['page_size'], 'integer', 'max' => 5000, 'min' => 1],
            ['tool_id', 'validateRequired']
        ];
    }

    public function attributeLabels()
    {
        return [
            'tool_id' => '执行工具',
            'contract_id' => 'ZFT协议ID',
            'standard_id' => '检查项目ID',
            'page' => '页码',
            'page_size' => '分页数',
        ];
    }

    /**
     * 协议id和检查项目id必须有1个有值
     */
    public function validateRequired(){
        if($this->contract_id == 0 && $this->standard_id == 0){
            $this->addError('standard_id', '协议id和检查项目id必须有1个有值');
        }
    }
}