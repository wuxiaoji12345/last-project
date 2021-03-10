<?php
return [
    'project_id' => 'MARKET_EXE_SYS_PRE',
    'h5key' => 'lingmou',
    'baidumap' => [
        'ak' => 'o7X8Ay0zI76aAIxLENxzWFDbZuQsqcPN',
    ],
    'tencentcos' => [
        'secretid' => 'AKIDd3E9np4LlvpUxAzuObYGiurZ9UYNGHLE',
        'secretkey' => 'mMGwQRDj02oKbcMq8A1cw0oMgmbATXMv',
        'region' => 'ap-shanghai',
        'bucket' => 'snapshot-swire-1255412942',
        'bucket-video' => 'snapshot-video-1255412942'
    ],
    'sms' => [
        'appid' => '1400143208',
        'appkey' => '3c2ab735d8f22895e8d4eeaf7cb98e9a',
        'tempid' => '217814',
        'sign' => '零眸秒识'
    ],
    'ding' => [
        'redis_name' => 'DING_REDIS',
        'access_token' => '83d200e48ca3e1c532c0bf2ccb4cf0dca92445ad39a2c86ca2210a1f04d474c4',
        'secret' => 'SEC24011b2be01795d3edf7d964c049ae2aceb0bae35e6d6a85ad63416a0993a998',
    ],
    'sftp' => [
        'host' => 'ftp.lingmouai.com',
        'username' => 'smart-pre',
        'password' => 'MwWaaO5B',
        'port' => '2229',
    ],
    'store_sftp' => [
        'customer' => [
            'host' => 'ftp.lingmouai.com',
            'username' => 'pre-customer',
            'password' => 'kdu76sT0',
            'port' => '2229'
        ],
        'equipment'=> [
            'host'=> 'ftp.lingmouai.com',
            'username'=> 'pre-equipment',
            'password'=> 'Zk#Jlrta',
            'port'=> '2229'
        ]
    ],
    'tools' => [
        'update_question' => 'http://sea-api-pre.lingmouai.com/updateQuestion'
    ],
    'queue_tool_token' => 'TOOL_TOKEN',
    'queue_ask_survey_data' => 'ASK_SURVEY_DATA',
    'queue_survey_id' => 'SURVEY_ID_',  // 生成走访号用，key需要加时间戳做后缀，并且设置5秒过期时间
    'queue_plan_store_file' => 'PLAN_STORE_FILE',       // 检查计划上传售点
    'queue_plan_store_list_task' => 'PLAN_STORE_LIST_TASK',     // 检查计划任务列表
    'queue_plan_store_tmp' => 'PLAN_STORE_FILE_TMP',       // 检查计划上传售点，入临时表
    'queue_plan_batch_tmp' => 'PLAN_BATCH_FILE_TMP',       // 按批次创建检查计划，协议门店关系导入，入临时表
    'redis_queue' => [
        'report_scene_download_list' => 'REPORT_SCENE_DOWNLOAD_LIST',           // 引擎结果下载
        'report_scene_download_process_prefix' => 'REPORT_SCENE_DOWNLOAD_PROCESS_PREFIX_',
        'report_statistical_download_list' => 'REPORT_STATISTICAL_DOWNLOAD_LIST',           // 统计项目引擎结果下载
        'report_statistical_download_process_prefix' => 'REPORT_STATISTICAL_DOWNLOAD_PROCESS_PREFIX_',
        'report_question_download_list' => 'REPORT_QUESTION_DOWNLOAD_LIST',           // 引擎结果问卷下载
        'report_question_download_process_prefix' => 'REPORT_QUESTION_DOWNLOAD_PROCESS_PREFIX_',
        'plan_store_download_list' => 'PLAN_STORE_DOWNLOAD_LIST',           // 检查计划已配置的售点下载
        'plan_store_download_process_prefix' => 'PLAN_STORE_DOWNLOAD_PROCESS_PREFIX',
        'plan_protocol_store_download_list' => 'PLAN_PROTOCOL_STORE_DOWNLOAD_LIST',           // 检查计划协议签约售点下载
        'plan_protocol_store_download_process_prefix' => 'PLAN_PROTOCOL_STORE_DOWNLOAD_PROCESS_PREFIX',
        'replan_create_list' => 'REPLAN_CREATE_LIST',                // 创建重跑计划的数据
        'plan_batch_upload' => 'PLAN_BATCH_UPLOAD',                 // 按批次创建检查计划
        'plan_batch_upload_process_prefix' => 'PLAN_BATCH_UPLOAD_PROCESS_PREFIX',                // 按批次创建检查计划 生成进度查询
        'manual_check_result_list_download' => 'MANUAL_CHECK_RESULT_LIST_DOWNLOAD',           // 获取人工复核结果列表下载
        'manual_check_result_list_download_process_prefix' => 'MANUAL_CHECK_RESULT_LIST_DOWNLOAD_PROCESS_PREFIX_',
        'replan_status_prefix' => 'REPLAN_STATUS_PREFIX_',                      // 统计重跑队列
        'plan_excel_store_download_list' => 'PLAN_EXCEL_STORE_DOWNLOAD_LIST',           // 检查计划excel已导入售点下载
        'plan_excel_store_download_process_prefix' => 'PLAN_EXCEL_STORE_DOWNLOAD_PROCESS_PREFIX',
        'plan_excel_store_fail_download_list' => 'PLAN_EXCEL_STORE_FAIL_DOWNLOAD_LIST',           // 检查计划excel已导入售点失败数据下载
        'plan_excel_store_fail_download_process_prefix' => 'PLAN_EXCEL_STORE_FAIL_DOWNLOAD_PROCESS_PREFIX',
        'plan_batch_excel_import_fail_download_list' => 'PLAN_BATCH_EXCEL_IMPORT_FAIL_DOWNLOAD_LIST',           // 按批次创建检查计划 协议门店关系excel导入失败数据下载
        'plan_batch_excel_import_fail_download_process_prefix' => 'PLAN_BATCH_EXCEL_IMPORT_FAIL_DOWNLOAD_PROCESS_PREFIX',
        'plan_batch_save' => 'PLAN_BATCH_SAVE',                 // 按批次创建检查计划
        'plan_batch_save_process_prefix' => 'PLAN_BATCH_SAVE_PROCESS_PREFIX',                // 按批次创建检查计划 生成进度查询
        'download_list' => 'DOWNLOAD_LIST',           // 引擎结果下载
        'report_image_download_process_prefix' => 'REPORT_IMAGE_DOWNLOAD_PROCESS_PREFIX_',
        'question_qc_upload' => 'QUESTION_QC_UPLOAD',    // 问卷qc 批量复核上传
        'question_qc_fail_download' => 'QUESTION_QC_FAIL_DOWNLOAD',    // 问卷qc 批量复核失败数据下载
        'question_qc_fail_download_progress' => 'QUESTION_QC_FAIL_DOWNLOAD_PROGRESS',    // 问卷qc 导入失败数据下载失败数据下载
    ],
    'distinguish_callback' => 'http://swire-check-system-api-pre.lingmouai.com/api/receive/recognition-result',
    'similarity_callback' => 'http://swire-check-system-api-pre.lingmouai.com/api/receive/similarity-image',
    'open_similarity' => true,
    'url' => [
        'ir_sku_host' => 'http://engine-sku-api-pre.lingmouai.com/',
        'media_host' => 'http://snapshot-swire-medi-pre.lingmouai.com/',
        'rule_host' => 'http://rule-engine-api-pre.lingmouai.com/',
//        'rule_web' => 'http://rule-engine-pre.lingmouai.com/',
        'rule_web' => 'https://rule-engine-pre.lingmouai.com/',
//        'smart_medi_host' => 'http://dev2.ficent.com:9888/',
    ],
    'swire_user' => [
        'url' => 'http://mes-url-pre.lingmouai.com',
//        'url' => 'https://mockapi.eolinker.com/aIJA2tabd36a2b76a0fdd6810da330554cd9caa5b2bb28d',
        'account' => 'medi@swirebev.com',
        'api-key' => 'YnrIUtEaSXCJQ39SqE4VHh50-gzGzoHsz',
        'user_function_list_queue' => 'USER_FUNC_LIST_'        // 用户权限列表 USER_FUNC_LIST_30060001 后面加user表太古user_id
    ],
    'zft_url' => 'https://zftapiqnr.app.swiretest.com',                 // zft接口地址
    'swire_function_map' => 'SWIRE_FUNCTION_MAP',
    'swire_user_info' => 'USER_TOKEN_',
    'media_key' => 'swireIne2020',
    'cp_url' => 'http://cp-api-pre.lingmouai.com/receive/auditResult',
    'new_cp_url' => 'http://cp-api-pre.lingmouai.com/receiver/auditResult',
    'queue_store_check_task_id_uid' => 'QUEUE_STORE_CHECK_TASK_ID_UID_',
    'zft_api_key' => '48392f24a3d76b5a9ffd613',
    'engine_calculation' => [
        'engine_0' => 'ENGINE_INPUT_PRE_0',
        'engine_1' => 'ENGINE_INPUT_PRE_1'
    ],
    'cos_url' => 'http://snapshot-swire-1255412942.cos.ap-shanghai.myqcloud.com/',
    //复制规则的url配置，由于存在跨环境复制，因此每个环境都要配置
    'copy_standard_url' => [
        'dev' => [
            'get_engine_used_data' => 'http://local-engine-api.cn/api/copy/get-used-data',
            'push_data_to_target_check' => 'http://local-check-api.cn/api/copy/receive-data',
            'push_data_to_target_engine' => 'http://local-engine-api.cn/api/copy/copy-rule'
        ],
        /*'dev' => [
            'get_engine_used_data' => 'http://rule-engine-api-dev.lingmou.ai:8031/api/copy/get-used-data',
            'push_data_to_target_check' => 'https://swire-check-system-api-dev.lingmou.ai:4431/api/copy/receive-data',
            'push_data_to_target_engine' => 'http://rule-engine-api-dev.lingmou.ai:8031/api/copy/copy-rule',
        ],*/
        'test' => [
            'get_engine_used_data' => 'https://rule-engine-api-test.lingmou.ai:4432/api/copy/get-used-data',
            'push_data_to_target_check' => 'https://swire-check-system-api-test.lingmou.ai:4432/api/copy/receive-data',
            'push_data_to_target_engine' => 'https://rule-engine-api-test.lingmou.ai:4432/api/copy/copy-rule'
        ],
        'pre' => [
            'get_engine_used_data' => 'https://rule-engine-api-pre.lingmouai.com/api/copy/get-used-data',
            'push_data_to_target_check' => 'https://swire-check-system-api-pre.lingmouai.com/api/copy/receive-data',
            'push_data_to_target_engine' => 'https://rule-engine-api-pre.lingmouai.com/api/copy/copy-rule'
        ],
        'prod' => [
            'get_engine_used_data' => 'https://rule-engine-api.lingmouai.com/api/copy/get-used-data',
            'push_data_to_target_check' => 'https://swire-check-system-api.lingmouai.com/api/copy/receive-data',
            'push_data_to_target_engine' => 'https://rule-engine-api.lingmouai.com/api/copy/copy-rule'
        ]
    ],
    'sea_url'=>'http://sea-plus-pre.lingmouai.com/sea',
];