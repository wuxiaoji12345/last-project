<?php

namespace api\models;

/**
 * This is the model class for table "{{%function_permission}}".
 *
 * @property int $id 主键id
 * @property string $module 模块名
 * @property string $web_function_id 前端url
 * @property string $controller 控制器
 * @property string $action 函数
 * @property string $action_url 链接
 * @property string $function_id 执行工具function_id
 * @property string $menu_function_id 所属菜单
 * @property string $name 事件
 * @property string $note 备注
 * @property int $sys_used 系统是否强制使用，0否，1是，为1不能删除，修改需要检查代码
 * @property int $status 删除标识：1有效，0无效
 * @property int $created_at 添加时间
 * @property int $updated_at 业务更新时间
 * @property string $update_time db更新时间
 */
class FunctionPermission extends baseModel
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%function_permission}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['action_url', 'created_at', 'updated_at'], 'required'],
            [['sys_used', 'status', 'created_at', 'updated_at'], 'integer'],
            [['update_time'], 'safe'],
            [['module', 'controller', 'action'], 'string', 'max' => 100],
            [['web_function_id', 'action_url', 'name', 'note'], 'string', 'max' => 255],
            [['function_id', 'menu_function_id'], 'string', 'max' => 50],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'module' => '模块名',
            'web_function_id' => '前端url',
            'controller' => '控制器',
            'action' => '函数',
            'action_url' => '链接',
            'function_id' => '执行工具function_id',
            'menu_function_id' => '所属菜单',
            'name' => '事件',
            'note' => '备注',
            'sys_used' => '系统使用',
            'status' => '删除标识：1有效，0无效',
            'created_at' => '添加时间',
            'updated_at' => '业务更新时间',
            'update_time' => 'db更新时间',
        ];
    }
}
