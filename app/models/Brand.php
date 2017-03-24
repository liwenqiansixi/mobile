<?php
namespace app\models;

class Brand extends Foundation
{

    /**
     * 该模型主键字段
     *
     * @var string
     */
    protected $primaryKey = 'brand_id';

    /**
     * 该模型是否被自动维护时间戳
     *
     * @var bool
     */
    public $timestamps = false;

}