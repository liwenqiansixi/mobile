<?php
namespace app\models;

class Goods extends Foundation
{

    /**
     * 该模型主键字段
     *
     * @var string
     */
    protected $primaryKey = 'goods_id';

    /**
     * 该模型是否被自动维护时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     *
     * @param $value
     * @return mixed
     */
    public function getGoodsNameAttribute($value)
    {
        return $value;
    }

}