<?php
namespace api\models;

interface ReportInterface
{
    public static function getList();

    public static function getDetail();

    public static function getNext();

    public static function getPrev();
}