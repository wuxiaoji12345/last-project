<?php


namespace console\service\downloads;


use yii\helpers\FileHelper;
use Yii;

class Base
{

    /**
     * @param $bodyForm
     * @param $search_task_id
     * @param $file_name
     * @return array
     * @throws \yii\base\Exception
     */
    protected static function generateFileName($bodyForm, $search_task_id, $file_name = '')
    {

        if ($file_name == '') {
            $file_name = $bodyForm['start_time'] . '_' . $bodyForm['end_time'] . '_' . '_' . $search_task_id . '.xlsx';
        } else {
            $ext = pathinfo($file_name, PATHINFO_EXTENSION);
            if (empty($ext)) {
                $file_name .= '.xlsx';
            }
        }

        $relativePath = '/tmp/' . date('Ymd') . '/';
        $path = Yii::getAlias('@api') . '/web' . $relativePath;

        if (!file_exists($path)) {
            FileHelper::createDirectory($path);
        }
        return ['file_name' => $file_name, 'full_name' => $path . $file_name, 'relative_path' => $relativePath];
    }
}