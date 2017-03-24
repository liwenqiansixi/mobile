<?php if (!defined('THINK_PATH')) exit();?><!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0"/>
    <meta name="apple-mobile-web-app-capable" content="yes"/>
    <meta name="apple-mobile-web-app-status-bar-style" content="black"/>
    <meta name="format-detection" content="telephone=no"/>
    <meta charset="utf-8">
    <meta name="description" content="<?php echo $description; ?>"/>
    <meta name="keywords" content="<?php echo $keywords; ?>"/>
    <title><?php echo $page_title; ?></title>
    <?php echo global_assets('css');?>
    <script type="text/javascript">var ROOT_URL = '/mobile/';</script>
    <?php echo global_assets('js');?>
    <?php if($is_wechat) { ?>
    <script type="text/javascript" src="https://res.wx.qq.com/open/js/jweixin-1.1.0.js"></script>
    <script type="text/javascript">
        // 分享内容
        var shareContent = {
            title: '<?php echo $page_title; ?>',
            desc: '<?php echo $description; ?>',
            link: '',
            imgUrl: '<?php if($page_img) { echo $page_img; } else { echo elixir("img/wxsdk.png", true); } ?>'
        };
        $(function(){
            var url = window.location.href;
            var jsConfig = {
                debug: false,
                jsApiList: [
                    'onMenuShareTimeline',
                    'onMenuShareAppMessage',
                    'onMenuShareQQ',
                    'onMenuShareWeibo',
                    'onMenuShareQZone'
                ]
            };
            $.post('<?php echo url("wechat/jssdk/index");?>', {url: url}, function (res) {
                if(res.status == 200){
                    jsConfig.appId = res.data.appId;
                    jsConfig.timestamp = res.data.timestamp;
                    jsConfig.nonceStr = res.data.nonceStr;
                    jsConfig.signature = res.data.signature;
                    // 配置注入
                    wx.config(jsConfig);
                    // 事件注入
                    wx.ready(function () {
                        wx.onMenuShareTimeline(shareContent);
                        wx.onMenuShareAppMessage(shareContent);
                        wx.onMenuShareQQ(shareContent);
                        wx.onMenuShareWeibo(shareContent);
                        wx.onMenuShareQZone(shareContent);
                    });
                }
            }, 'json');
        })
    </script>
    <?php } ?>
</head>
<body>
<p style="text-align:right; display:none;"><?php echo config('shop.stats_code');?></p>
<div id="loading"><img src="<?php echo elixir('img/loading.gif');?>" /></div>
<style type="text/css">
.s-g-attr-con{max-height:25rem;}
</style>
<div class="con goods" id="checkPage">
    <header class="dis-box header-menu n-header-menu b-color color-whie">
        <a class="" href="javascript:history.go(-1);"><i class="iconfont icon-back"></i></a>
        <h3 class="box-flex">
        <nav class="n-goods-shop-list-nav box-flex swiper-container-horizontal ">
            <ul class="swiper-wrapper  dis-box text-c">
                <li class="div1 box-flex swiper-slide active position-rel swiper-slide-active" category="1">
                    <a class="product-div-link" href="<?php echo url('goods/index/index', array('id'=>$goods['goods_id']));?>"></a>商品</li>
                <li class="div3 box-flex swiper-slide position-rel swiper-slide-next" category="3">
                    <a class="product-div-link" href="<?php echo url('goods/index/info', array('id'=>$goods['goods_id']));?>"></a>详情</li>
                <li class="div4 box-flex swiper-slide position-rel" category="4">
                    <a class="product-div-link" href="<?php echo url('goods/index/comment', array('id'=>$goods['goods_id']));?>"></a>评论</li>
            </ul>
        </nav>
        </h3>
        <a><i class="iconfont icon-13caidan j-nav-box"></i></a>
    </header>

    <form name="ECS_FORMBUY" id="ECS_FORMBUY" method="post" action="javascript:addToCart(<?php echo ($goods['goods_id']); ?>)">
        <div>
            <div class="goods-photo j-show-goods-img goods-banner j-goods-box" >
                <span class="goods-num" id="goods-num"><span id="g-active-num"></span>/<span id="g-all-num"></span></span>
                <div class="swiper-wrapper">
                    <?php if($pictures) { ?>
                    <?php $n=1;if(is_array($pictures)) foreach($pictures as $li) { ?>
                    <li class="swiper-slide tb-lr-center">
                        <img src="<?php echo ($li['img_url']); ?>" alt="<?php echo ($picture['img_desc']); ?>" />
                    </li>
                    <?php $n++;}unset($n); ?>
                    <?php } else { ?>
                    <li class="swiper-slide tb-lr-center">
                        <img src="<?php echo ($goods['goods_img']); ?>" alt="<?php echo ($goods['goods_name']); ?>" />
                    </li>
                    <?php } ?>
                </div>
                <!-- Add Pagination -->
                <div class="swiper-pagination"></div>
            </div>

            <section class="goods-title b-color-f padding-all ">
                <div class="dis-box">
                    <h3 class="box-flex">
                        <?php if(empty($goods['user_id'])) { ?>
                        <em class="em-promotion">自营</em>
                        <?php } ?>
                        <?php echo ($goods['goods_style_name']); ?></h3>
                    <span class="heart j-heart <?php if($goods_collect) { ?>active<?php } ?>"  onclick="collect(<?php echo ($goods['goods_id']); ?>)" id="ECS_COLLECT"><i class="ts-2"></i><em class="ts-2">收藏</em></span>
                </div>
            </section>
            <section class="goods-price padding-all b-color-f">
                <p class="p-price">
                <!-- 如果是 user_status 1才显示，0不显示 -->
                    <?php if($user_status == 1) { ?>
                    <span class="t-first" id="ECS_SHOPPRICE"><?php if($goods['is_promote'] && $goods['gmt_end_time']) { echo ($goods['promote_price']); } else { echo ($goods['shop_price_formated']); } ?></span>
                    <em class="em-promotion"><font id="ECS_DISCOUNT"><?php echo ($goods['zhekou']); ?></font>折</em>
                    <?php } else { ?>

                    <?php } ?>

                </p>
                <p class="p-market">
                 <?php if(config('shop.show_marketprice')==1) { ?>
                <?php echo ($lang['market_price']); ?> <del id="ECS_MARKETPRICE"><?php echo ($goods['market_price']); ?></del>
                 <?php } ?>
                </p>
                <p class=" dis-box g-p-tthree m-top06">
                    <span class="box-flex text-left">销量 <?php if($goods['setting_sales']) { echo ($goods['setting_sales']); echo ($goods['measure_unit']); } else { echo ($goods['sales_volume']); echo ($goods['measure_unit']); } ?></span>
                    <?php if($cfg['show_goodsnumber'] && $cfg['use_storage']) { ?>
                    <span class="box-flex text-center">当前库存 <font class="goods_attr_num"><?php echo ($goods['goods_number']); ?></font><?php echo ($goods['measure_unit']); ?></span>
                    <?php } ?>
                    <?php if($goods['user_id']) { ?>
                    <span class="box-flex text-right"><?php echo ($basic_info['province']); echo ($basic_info['city']); ?></span>
                    <?php } ?>
                </p>
            </section>
            <?php if($promotion || $goods['consumption']) { ?>
            <section class="m-top04 padding-all b-color-f goods-promotion">
                <div class="dis-box">
                    <label class="t-remark g-t-temark">促销</label>
                    <div class="box-flex g-promotion-con">
                        <?php $n=1;if(is_array($promotion)) foreach($promotion as $k=>$v) { ?>
                        <?php if($v['type'] == "favourable") { ?>
                        <p><em class="em-promotion">
                            <?php if($v['act_type'] == 0) { ?>
                            满赠
                            <?php } elseif ($v['act_type'] == 1) { ?>
                            立减
                            <?php } elseif ($v['act_type'] == 2) { ?>
                            折扣
                            <?php } ?>
                        </em><span class=""><?php echo ($v['act_name']); ?></span></p>
                        <?php } elseif ($v['type'] == "group_buy") { ?>
                        <p><a href="<?php echo url('groupbuy/index/index');?>" >[团购活动] <span class=""><?php echo ($v['act_name']); ?></span></a></p>
                        <?php } ?>
                        <?php $n++;}unset($n); ?>
                        <?php if($goods['consumption']) { ?>
                        <p><em class="em-promotion">满减</em>
                            <?php $n=1;if(is_array($goods['consumption'])) foreach($goods['consumption'] as $k=>$v) { ?>
                            <span class="">
                            满<?php echo ($v['cfull']); ?>减<?php echo ($v['creduce']); ?>
                            </span>
                            <?php $n++;}unset($n); ?>
                        </p>
                        <?php } ?>
                    </div>
                </div>
            </section>
            <?php } ?>
            <?php if($bonus_list) { ?>
            <section class="m-top04 padding-all b-color-f goods-coupon j-show-div">
                <div class="dis-box">
                    <label class="t-remark g-t-temark">领取优惠券 (<?php echo count($bonus_list);?>)</label>
                    <div class="box-flex g-coupon-con">
                        <ul class="dis-box">
                            <?php $n=1;if(is_array($bonus_list)) foreach($bonus_list as $key=>$v) { ?>
                            <li class="box-flex">
                                <span class="remark-all  temark-<?php if($bonus['cou_money']>=0) { if($bonus['cou_money']>=50) { if($bonus['cou_money']>=100) { ?>1<?php } else { ?>2<?php } } else { ?>3<?php } } else { ?>3<?php } ?>">
                                   <?php echo ($v['cou_name']); ?>
                                </span>
                            </li>
                            <?php $n++;}unset($n); ?>
                        </ul>
                    </div>
                </div>
                <!--领取优惠券star-->
                <div class="show-goods-coupon j-filter-show-div ts-3 b-color-1">
                    <section class="goods-show-title of-hidden padding-all b-color-f">
                        <h3 class="fl g-c-title-h3">领取店铺优惠券 (<?php echo count($bonus_list);?>)</h3>
                        <i class="iconfont icon-guanbi2 show-div-guanbi fr"></i>
                    </section>
                    <section class="goods-show-con padding-all swiper-scroll" id="goods-show-con">
                        <div class="swiper-wrapper">
                            <div class="swiper-slide">
                        <ul>
                            <?php $n=1;if(is_array($bonus_list)) foreach($bonus_list as $key=>$bonus) { ?>
                            <li class="dis-box big-remark-all">
                                <div class="box-flex remark-all temark-<?php if($bonus['cou_money']>=0) { if($bonus['cou_money']>=50) { if($bonus['cou_money']>=100) { ?>1<?php } else { ?>2<?php } } else { ?>3<?php } } else { ?>3<?php } ?>">
                                    <p>
                                        <span class="b-r-a-price fl"><sup>¥</sup><?php echo ($bonus['cou_money']); ?></span>
                                        <span class="b-r-a-con fl text-left "><em>优惠券</em><em>满<?php echo ($bonus['cou_man']); ?>元可使用</em></span>
                                    </p>
                                    <p class="text-left b-r-a-time">使用期限：<?php echo ($bonus['cou_start_time']); ?> ~ <?php echo ($bonus['cou_end_time']); ?></p>
                                </div>
                                <a class="b-r-a-btn b-color-f temark-<?php if($bonus['cou_money']>=0) { if($bonus['cou_money']>=50) { if($bonus['cou_money']>=100) { ?>1<?php } else { ?>2<?php } } else { ?>3<?php } } else { ?>3<?php } ?>-text tb-lr-center" href="javascript:;" onclick="receivebonus('<?php echo ($bonus['cou_id']); ?>')">立即<br />领取</a>
                            </li>
                            <?php $n++;}unset($n); ?>
                        </ul>
                        </div>
                        </div><div class="swiper-scrollbar"></div>

                    </section>
                </div>
                <!--领取优惠券end-->
            </section>
            <?php } ?>
            <section class="padding-all b-color-f m-top1px n-goods-box">
                <!--address-start-->
                <div id="editAddressForm">
                    <input type="hidden" id="town_id" name="town_region_id" value="<?php echo ($town_row['region_id']); ?>">
                    <input type="hidden" id="village_id" name="village_region_id" value="">
                    <input type="hidden" id="address_id" name="address_id" value="">
                    <input type="hidden" value="<?php if($district_row['region_id']) { echo ($district_row['region_id']); } else { ?>0<?php } ?>" id="district_id" name="district_region_id">
                    <input type="hidden" value="<?php echo $goods_id; ?>" id="good_id" name="good_id">
                    <div class="address-box" id="selectAddressBtn" region-data="">
                            <label class="fl t-remark g-t-temark">配送至：</label>
                            <span class="fl text-all-span f-05" id="addressLabelId"><?php echo ($province_row['region_name']); echo ($city_row['region_name']); echo ($district_row['region_name']); echo ($town_row['region_name']); echo ($village_row['region_name']); ?></span>
                            <span class="t-jiantou fr"><i class="iconfont icon-jiantou tf-180"></i></span>
                    </div>
                </div>
                 <!--address-end-->
            </section>

            <?php if($goods['store_count'] > 0) { ?>
            <section class="m-top1px padding-all b-color-f goods-attr position-rel">
                <a class="product-div-link" href="<?php echo url('offline_store/index/storeList', array('id'=>$goods['goods_id']));?>"></a>
                <div class="dis-box">
                    <label class="t-remark g-t-temark">门店服务</label>
                    <span class="box-flex t-goods1 text-all-span">
                        <?php if($store) { ?>
                        <?php echo ($store['stores_name']); ?>
                        <input type="hidden" id="store_id" name="store_id" checked value="<?php echo ($store['id']); ?>"/>
                        <?php } else { ?>
                        门店自提
                        <?php } ?>
                    </span>
                    <span class="t-jiantou fr<?php if($store!='') { ?> j-del-store<?php } ?>" <?php if($store) { ?>style="padding-right:0;"<?php } ?>> <i class="iconfont <?php if($store) { ?>icon-guanbi2<?php } else { ?>icon-jiantou  tf-180<?php } ?>"></i></span>
                </div>
            </section>
            <?php } ?>
            <section class="m-top1px padding-all b-color-f goods-attr">
                <div class="dis-box">
                    <label class="t-remark g-t-temark">运费信息</label>
                    <?php if($shippingFee['is_shipping'] == 1) { ?>
                    <span class="box-flex t-goods1 text-all-span"><?php echo ($shippingFee['shipping_fee_formated']); ?></span>
                    <?php } else { ?>
                    <span class="box-flex t-goods1 text-all-span">该地区不支持配送</span>
                    <?php } ?>
                </div>
            </section>
            <?php if($show_warehouse) { ?>
            <section class="m-top1px padding-all b-color-f goods-attr j-filter-depot">
                <div class="dis-box">
                    <label class="t-remark g-t-temark">默认仓库</label>
                    <span class="box-flex t-goods1 text-all-span "><?php echo $warehouse_name; ?></span>
                    <span class="t-jiantou fr"><i class="iconfont icon-jiantou tf-180"></i></span>
                </div>
            </section>
            <?php } ?>
            <section class="m-top1px padding-all b-color-f goods-attr j-goods-attr j-show-div">
                <div class="dis-box">
                    <label class="t-remark g-t-temark">已选</label>
                    <div class="box-flex t-goods1 "><?php echo $default_spe; ?></div>
                    <span class="t-jiantou"><i class="iconfont icon-jiantou tf-180"></i></span>
                </div>
                <!--商品属性弹出层star-->
                <div class="mask-filter-div"></div>
                <div class="show-goods-attr j-filter-show-div ts-3 b-color-1">
                    <section class="s-g-attr-title b-color-1  product-list-small">
                        <div class="product-div">
                            <img class="product-list-img" src="<?php echo ($goods['goods_img']); ?>">
                            <div class="product-text">
                                <div class="dis-box position-rel">
                                    <h4 class="box-flex"><?php echo ($goods['goods_name']); ?></h4>
                                    <i class="iconfont icon-guanbi2 show-div-guanbi"></i>
                                </div>
                                <p>
                                <?php if($user_status) { ?>
                                <span class="p-price t-first" id="ECS_GOODS_AMOUNT">
                                    <?php if($goods['is_promote'] && $goods['gmt_end_time']) { ?>
                                    <?php echo ($goods['promote_price']); ?>
                                    <?php } else { ?>
                                    <?php echo ($goods['shop_price_formated']); ?>
                                    <?php } ?>
                                </span>
                                <?php } ?>
                                </p>
                                <p class="dis-box p-t-remark"><span class="box-flex">库存:<font class="goods_attr_num"></font><?php echo ($goods['measure_unit']); ?></span></p>
                            </div>
                        </div>
                    </section>
                    <section class="s-g-attr-con swiper-scroll b-color-f padding-all m-top1px">
                        <div class="swiper-wrapper">
                            <div class="swiper-slide">
                                <?php $n=1;if(is_array($specification)) foreach($specification as $spec_key=>$spec) { ?>
                                <?php if($spec['values']) { ?>
                                <h4 class="t-remark"><?php echo ($spec['name']); ?></h4>
                                <!-- 判断属性是复选还是单选 -->
                                <?php if($spec['attr_type'] == 1) { ?>
                                <ul class="select-one j-get-one m-top10">
                                    <?php if($spec['is_checked'] > 0) { ?>
                                    <!-- pc有属性图片 -->
                                    <?php $n=1;if(is_array($spec['values'])) foreach($spec['values'] as $key=>$val) { ?>
                                    <a class="ect-select dis-flex fl" href="javascript:;" <?php if($val['img_site']) { ?>onclick="location.href='<?php echo ($val['img_site']); ?>'"<?php } ?>>
                                        <label class="ts-1 <?php if($val['checked'] == 1) { ?>active<?php } ?>" for="spec_value_<?php echo ($val['id']); ?>"><?php echo ($val['label']); ?></label>
                                        <input style="display:none" id="spec_value_<?php echo ($val['id']); ?>" type="radio" name="spec_<?php echo $spec_key; ?>" value="<?php echo ($val['id']); ?>" <?php if($val['checked'] == 1) { ?>checked<?php } ?> onclick="changePrice()" />
                                    </a>
                                    <?php $n++;}unset($n); ?>
                                    <?php } else { ?>
                                    <!-- pc没属性图片 -->
                                    <?php $n=1;if(is_array($spec['values'])) foreach($spec['values'] as $key=>$val) { ?>
                                    <a class="ect-select dis-flex fl" href="javascript:;" <?php if($val['img_site']) { ?>onclick="location.href='<?php echo ($val['img_site']); ?>'"<?php } ?>>
                                        <label class="ts-1 <?php if($key == 0) { ?>active<?php } ?>" for="spec_value_<?php echo ($val['id']); ?>"><?php echo ($val['label']); ?></label>
                                        <input style="display:none" id="spec_value_<?php echo ($val['id']); ?>" type="radio" name="spec_<?php echo $spec_key; ?>" value="<?php echo ($val['id']); ?>" <?php if($key == 0) { ?>checked<?php } ?> onclick="changePrice()" />
                                    </a>
                                    <?php $n++;}unset($n); ?>
                                    <?php } ?>
                                </ul>
                                <input type="hidden" name="spec_list" value="<?php echo $spec_key; ?>" />

                                <?php } else { ?>
                                <ul class="select-one j-get-one m-top10">
                                    <?php $n=1;if(is_array($spec['values'])) foreach($spec['values'] as $key=>$val) { ?>
                                    <li class="ect-select dis-flex fl">
                                        <label class="ts-1 <?php if($key == 0) { ?>active<?php } ?>" for="spec_value_<?php echo ($val['id']); ?>"><?php echo ($val['label']); ?></label>
                                        <input type="checkbox" name="spec_<?php echo $spec_key; ?>[]" value="<?php echo ($val['id']); ?>" id="spec_value_<?php echo ($val['id']); ?>" onclick="changePrice()" <?php if($select_key == 0) { ?>checked<?php } ?> style="display:none" />
                                    </li>
                                    <?php $n++;}unset($n); ?>
                                </ul>
                                <?php } ?>
                                <?php } ?>
                                <?php $n++;}unset($n); ?>

                                <h4 class="t-remark">数量</h4>
                                <!-- 普通商品可修改数量 -->
                                <?php if($goods['goods_id'] > 0 && $goods['is_gift'] == 0 && $goods['parent_id'] == 0) { ?>
                                <div class="div-num dis-box m-top08 fl">
                                    <a class="num-less" onClick="changePrice('1')"></a>
                                    <input class="box-flex" type="text" value="<?php if($goods['min_quantity']) { echo ($goods['min_quantity']); } else { ?>1<?php } ?>" onblur="changePrice('2')" name="number" id="goods_number" />
                                    <a class="num-plus" onClick="changePrice('3')"></a>
                                </div>
                                <?php } else { ?>
                                <div class="div-num dis-box m-top08 div-num-disabled">
                                    <a class="num-less"></a>
                                    <input class="box-flex" type="text" value="<?php echo ($goods['goods_number']); ?>" name="number" />
                                    <a class="num-plus"></a>
                                </div>
                                <?php } ?>
                                <!-- 最小起订量 -->
                                <span class="text-left m-top08 t-first fl" style="height: 3rem;line-height: 2rem; padding: .5rem;font-size: 1.2rem;">最小起订量 <font class="min_quantity"><?php echo ($goods['min_quantity']); ?></font>
                            </div>
                        </div>
                        <div class="swiper-scrollbar"></div>
                    </section>
                    <section class="ect-button-more dis-box padding-all">
                        <input type="hidden" value="<?php echo ($province_row['region_id']); ?>" id="province_id" name="province_region_id">
                        <input type="hidden" value="<?php echo ($city_row['region_id']); ?>" id="city_id" name="city_region_id">
                        <input type="hidden" value="<?php if($district_row['region_id']) { echo ($district_row['region_id']); } else { ?>0<?php } ?>" id="district_id" name="district_region_id">
                        <input type="hidden" value="<?php echo $region_id; ?>" id="region_id" name="region_id">
                        <input type="hidden" value="<?php echo $goods_id; ?>" id="goods_id" name="good_id">
                        <input type="hidden" value="<?php echo $user_id; ?>" id="user_id" name="user_id">
                        <input type="hidden" value="<?php echo $area_id; ?>" id="area_id" name="area_id">
                        <a class="btn-disab box-flex quehuo" href="javascript:;" <?php if($goods['review_status'] > 2) { ?>style="display:none"<?php } ?>><?php if($goods['review_status'] == 1) { ?>暂时下架<?php } else { ?>暂时缺货<?php } ?></a>
                        <a class="btn-cart box-flex add-to-cart" href="javascript:;" onclick="addToCart_quick(<?php echo ($goods['goods_id']); ?>)" <?php if($goods['review_status'] <= 2) { ?>style="display:none"<?php } ?>>加入购物车</a>
                        <a class="btn-submit box-flex add-to-cart" href="javascript:;" onclick="addToCart(<?php echo ($goods['goods_id']); ?>)" <?php if($goods['review_status'] <= 2) { ?>style="display:none"<?php } ?>>立即购买</a>
                    </section>
                </div>
                <!--商品属性弹出层end-->
            </section>
            <?php if($is_reality['is_reality']==1 || $is_reality['is_return']==1 || $is_reality['is_fast']==1) { ?>
            <section class="m-top1px padding-all b-color-f goods-service j-show-div">
                <div class="dis-box">
                    <label class="t-remark g-t-temark">服务</label>
                    <div class="box-flex">
                        <div class="dis-box">
                            <p class="box-flex t-goods1">
                                <?php if($goods['user_id'] > 0) { ?>
                                由<?php echo ($goods['rz_shopName']); ?>发货并提供售后服务。
                                <?php } else { ?>
                                由<?php echo ($basic_info['shop_name']); ?>发货并提供售后服务。
                                <?php } ?></p>
                            <i class="iconfont icon-102 goods-min-icon"></i>
                            <!--服务信息star-->
                            <?php if($is_reality ) { ?>
                            <div class="show-goods-service j-filter-show-div ts-3 b-color-1">
                                <section class="goods-show-title of-hidden padding-all b-color-f">
                                    <h3 class="fl g-c-title-h3">服务说明</h3>
                                    <i class="iconfont icon-guanbi2 show-div-guanbi fr"></i>
                                </section>
                                <section class="goods-show-con goods-big-service swiper-scroll">
                                    <div class="swiper-wrapper">
                                        <div class="swiper-slide">
                                            <ul>

                                                <?php if($is_reality['is_reality']==1 ) { ?>
                                                <li class="m-top1px b-color-f padding-all of-hidden">
                                                    <p class="dis-box t-remark3">
                                                        <em class="em-promotion"><i class="iconfont icon-daifukuan"></i></em>
                                                        <span class="box-flex">正品保证</span>
                                                    </p>
                                                </li>
                                                <?php } ?>
                                                    <?php if($is_reality['is_return']==1 ) { ?>
                                                <li class="m-top1px b-color-f padding-all of-hidden">
                                                    <p class="dis-box t-remark3">
                                                        <em class="em-promotion"><i class="iconfont icon-7tianwuliyoutuihuo"></i></em>
                                                        <span class="box-flex">包退服务</span>
                                                    </p>
                                                </li>
                                                          <?php } ?>
                                                            <?php if($is_reality['is_fast']==1 ) { ?>
                                                <li class="m-top1px b-color-f padding-all of-hidden">
                                                    <p class="dis-box t-remark3">
                                                        <em class="em-promotion"><i class="iconfont icon-tixingnaozhong"></i></em>
                                                        <span class="box-flex">闪速配送</span>
                                                    </p>
                                                </li>
                                                   <?php } ?>


                                            </ul>

                                        </div>
                                    </div>

                                </section>
                                     <?php } ?>
                            </div>

                            <!--服务信息end-->
                        </div>
                        <div class="dis-box m-top08 g-r-rule">
                            <?php if($is_reality['is_reality']==1 ) { ?>
                            <p class="box-flex t-remark3">
                                <em class="fl em-promotion"><i class="iconfont icon-daifukuan"></i></em><span class="fl">正品保证</span></p>
                            <?php } ?>
                            <?php if($is_reality['is_return']==1 ) { ?>
                            <p class="box-flex t-remark3">
                                <em class="fl em-promotion"><i class="iconfont icon-7tianwuliyoutuihuo"></i></em><span class="fl">包退服务</span></p>
                            <?php } ?>
                            <?php if($is_reality['is_fast']==1 ) { ?>
                            <p class="box-flex t-remark3">
                                <em class="fl em-promotion"><i class="iconfont icon-tixingnaozhong"></i></em><span class="fl">闪速配送</span></p>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </section>
            <?php } ?>
            <section class="m-top1px padding-all b-color-f goods-promotion">
                <a href="<?php echo url('goods/index/info', array('id'=>$goods['goods_id']));?>">
                <div class="dis-box">
                    <label class="t-remark g-t-temark"><em class="t-first">查看商品详情</em></label>
                    <div class="box-flex"></div>
                    <span class="t-jiantou"><i class="iconfont icon-jiantou tf-180"></i></span>
                </div>
                </a>
            </section>
            <section class="m-top04 goods-evaluation">
                <a href="<?php echo url('goods/index/comment', array('id'=>$goods['goods_id']));?>">
                    <div class="dis-box padding-all b-color-f  g-evaluation-title">
                        <label class="t-remark g-t-temark">用户评价</label>
                        <div class="box-flex t-goods1">好评率 <em class="t-first"><?php echo ($comment_all['goodReview']); ?>%</em></div>
                        <div class="t-goods1"><em class="t-first"><?php echo ($comment_all['allmen']); ?></em><span class="t-jiantou">人评论<i class="iconfont icon-jiantou tf-180"></i></span></div>
                    </div>
                </a>
                <?php if($good_comment) { ?>
                <div class="padding-all m-top1px b-color-f g-evaluation-con">
                    <div class="evaluation-list">
                      <div class="dis-box comment-list-box">
                          <div class="box-flex p-r">
                              <?php if(!empty($good_comment[0]['user_picture'])) { ?>
                                <span class="comment-header"><img src="<?php echo $good_comment[0]['user_picture']; ?>"></span>
                              <?php } else { ?>
                                <span class="comment-header"><img src="<?php echo elixir('img/no_image.jpg');?>"></span>
                              <?php } ?>
                            <span class="f-05 col-7 comment-admin"><?php echo $good_comment[0]['username']; ?></span>
                          </div>
                          <div class="box-flex"><div class="fr t-remark"><?php echo $good_comment[0]['add_time']; ?></div></div>
                      </div>
                     <span class="grade-star g-star-<?php echo $good_comment[0]['rank']; ?> fl"></span>
                      <p class="clear f-05"><?php echo $good_comment[0]['content']; ?></p>
                        <?php if($good_comment[0]['goods']) { ?>
                        <p class="clear m-top08 t-remark"><?php echo $good_comment[0]['goods'][0]['goods_attr']; ?></p>
                        <?php } ?>
                        <div class="ect-button-more m-top10 dis-box">
                            <a href="<?php echo url('goods/index/infoimg', array('id'=>$goods['goods_id']));?>" class="box-flex btn-default">有图评价</a>
                            <a href="<?php echo url('goods/index/comment', array('id'=>$goods['goods_id']));?>" class="box-flex btn-default">全部评价</a>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </section>
            <section class="m-top04 padding-all goods-shop b-color-f <?php if(empty($goods['user_id'])) { ?>no-shopping-title<?php } ?>">
                <?php if($goods['user_id']) { ?>
                <div class="goods-shop-info">
                    <a href="<?php echo ($goods['store_url']); ?>" class="link-abs"></a>
                    <section class="dis-box">
                        <div class="g-s-i-img"><img src="<?php echo ($goods['shopinfo']['logo_thumb']); ?>" /></div>
                        <div class="g-s-i-title box-flex">
                            <h3 class="ellipsis-one"><?php echo ($goods['rz_shopName']); ?></h3>
                            <p class="t-remark m-top04">已经有 <?php echo $collect_number; ?> 人关注</p>
                        </div>
                    </section>
                    <section class="dis-box goods-shop-score m-top12">
                        <p class="box-flex">
                            <label class="fl">商品</label><span class="t-first margin-lr fl"><?php echo $merch_cmt['cmt']['commentRank']['zconments']['score']; ?>分</span><em class="em-promotion fl"><?php echo $merch_cmt['cmt']['commentRank']['zconments']['goodReview']; ?></em></p>
                        <p class="box-flex">
                            <label class="fl">服务</label><span class="t-low margin-lr fl"><?php echo $merch_cmt['cmt']['commentServer']['zconments']['score']; ?>分</span><em class="em-promotion em-p-low fl"><?php echo $merch_cmt['cmt']['commentServer']['zconments']['goodReview']; ?></em></p>
                        <p class="box-flex">
                            <label class="fl">时效</label><span class="t-center margin-lr fl"><?php echo $merch_cmt['cmt']['commentDelivery']['zconments']['score']; ?>分</span><em class="em-promotion em-p-center fl"><?php echo $merch_cmt['cmt']['commentDelivery']['zconments']['goodReview']; ?></em></p>
                    </section>
                </div>
                <?php } ?>
                <?php if($link_goods) { ?>
                <div class="goods-shop-pic of-hidden">
                    <h4 class="title-hrbg m-top06"><span>关联商品</span><hr> </h4>
                    <div class="g-s-p-con product-one-list of-hidden scrollbar-none j-g-s-p-con">
                        <div class="swiper-wrapper ">
                            <?php $n=1;if(is_array($link_goods)) foreach($link_goods as $k=>$v) { ?>
                            <li class="swiper-slide">
                                <div class="product-div">
                                    <a href="<?php echo ($v['url']); ?>"><img class="product-list-img" src="<?php echo ($v['goods_img']); ?>" /></a>
                                    <div class="product-text m-top06">
                                        <a href="<?php echo ($v['url']); ?>"><h4><?php echo ($v['goods_name']); ?></h4></a>
                                        <p>
                                        <?php if($user_status) { ?>
                                        <span class="p-price t-first ">
                                            <?php if($v['promote_price']) { ?>
                                            <?php echo ($v['promote_price']); ?>
                                            <?php } else { ?>
                                            <?php echo ($v['shop_price']); ?>
                                            <?php } ?>
                                        </span>
                                        <?php } ?>
                                        </p>
                                    </div>
                                </div>
                            </li>
                            <?php $n++;}unset($n); ?>
                        </div>
                    </div>
                </div>
                <?php } else { ?>
                <div class="goods-shop-pic of-hidden">
                    <h4 class="title-hrbg m-top06"><span>爆款新品</span><hr> </h4>
                    <div class="g-s-p-con product-one-list of-hidden scrollbar-none j-g-s-p-con">
                        <div class="swiper-wrapper ">
                            <?php $n=1;if(is_array($new_goods)) foreach($new_goods as $k=>$v) { ?>
                            <li class="swiper-slide">
                                <div class="product-div">
                                    <a href="<?php echo ($v['url']); ?>"><img class="product-list-img" src="<?php echo ($v['goods_img']); ?>" /></a>
                                    <div class="product-text m-top06">
                                        <a href="<?php echo ($v['url']); ?>"><h4><?php echo ($v['name']); ?></h4></a>
                                        <p>
                                        <?php if($user_status) { ?>
                                        <span class="p-price t-first ">
                                            <?php if($v['promote_price']) { ?>
                                            <?php echo ($v['promote_price']); ?>
                                            <?php } else { ?>
                                            <?php echo ($v['shop_price']); ?>
                                            <?php } ?>
                                        </span>
                                        <?php } ?>
                                        </p>
                                    </div>
                                </div>
                            </li>
                            <?php $n++;}unset($n); ?>
                        </div>
                    </div>
                </div>
                <?php } ?>
                <div class="ect-button-more m-top10 dis-box goods-shop-btn">
                    <?php if(isset($basic_info['kf_appkey']) && !empty($basic_info['kf_appkey'])) { ?>
                        <a class="box-flex btn-default" href="<?php echo url('chat/index/index', array('goods_id'=> $goods['goods_id']));?>"><i class="iconfont icon-kefu t-first"></i>联系客服</a>
                    <?php } elseif (isset($basic_info['meiqia']) && !empty($basic_info['meiqia'])) { ?>
                        <a class="box-flex btn-default" href="https://static.meiqia.com/dist/standalone.html?eid=<?php echo ($basic_info['meiqia']); ?>"><i class="iconfont icon-kefu t-first"></i>联系客服</a>
                    <?php } else { ?>
                        <?php if($basic_info['kf_type']) { ?>
                        <a class="box-flex btn-default" href="http://www.taobao.com/webww/ww.php?ver=3&touid=<?php echo ($basic_info['kf_ww']); ?>&siteid=cntaobao&status=1&charset=utf-8"><i class="iconfont icon-kefu t-first"></i>联系客服</a>
                        <?php } else { ?>
                        <a class="box-flex btn-default" href="http://wpa.qq.com/msgrd?v=3&uin=<?php echo ($basic_info['kf_qq']); ?>&site=qq&menu=yes"><i class="iconfont icon-kefu t-first"></i>联系客服</a>
                        <?php } ?>
                        <?php if($goods['user_id']) { ?>
                        <a class="box-flex btn-default" href="<?php echo ($goods['store_url']); ?>"><i class="iconfont icon-dianpu t-two"></i>进入店铺</a>
                        <?php } ?>
                    <?php } ?>
                </div>
            </section>
        </div>

        <!--飞入购物车小图s-->
        <div id="flyItem" class="fly_item"><img src="<?php echo ($goods['goods_img']); ?>" width="35" height="35"></div>
        <!--飞入购物车小图e-->

        <!--悬浮btn star-->
        <section class="filter-btn dis-box">
            <!--飞入购物车位置s-->
            <span class="quick_links" id="shopCart"></span>
            <!--飞入购物车位置e-->
            <?php if(isset($basic_info['kf_appkey']) && !empty($basic_info['kf_appkey'])) { ?>
                <a class="filter-btn-kefu filter-btn-a" href="<?php echo url('chat/index/index', array('goods_id'=> $goods['goods_id']));?>"><i class="iconfont icon-kefu"></i><em>客服</em></a>
            <?php } elseif (isset($basic_info['meiqia']) && !empty($basic_info['meiqia'])) { ?>
                <a class="filter-btn-kefu filter-btn-a" href="https://static.meiqia.com/dist/standalone.html?eid=<?php echo ($basic_info['meiqia']); ?>"><i class="iconfont icon-kefu"></i><em>客服</em></a>
            <?php } else { ?>
                <?php if($basic_info['kf_type']) { ?>
                <a class="filter-btn-kefu filter-btn-a" href="http://www.taobao.com/webww/ww.php?ver=3&touid=<?php echo ($basic_info['kf_ww']); ?>&siteid=cntaobao&status=1&charset=utf-8"><i class="iconfont icon-kefu"></i><em>客服</em></a>
                <?php } else { ?>
                <a class="filter-btn-kefu filter-btn-a" href="http://wpa.qq.com/msgrd?v=3&uin=<?php echo ($basic_info['kf_qq']); ?>&site=qq&menu=yes"><i class="iconfont icon-kefu"></i><em>客服</em></a>
                <?php } ?>
            <?php } ?>

            <a href="<?php echo url('cart/index/index');?>" class="filter-btn-flow filter-btn-a"><i class="iconfont icon-gouwuche"></i><sup class="b-color cart-num"><?php echo $cart_num; ?></sup><em>购物车</em></a>
            <?php if($goods['user_id']) { ?>
            <a class="filter-btn-shop filter-btn-a" href="<?php echo ($goods['store_url']); ?>"><i class="iconfont icon-dianpu"></i><em>店铺</em></a>
            <?php } ?>

            <a class="btn-disab box-flex quehuo" href="javascript:;" <?php if($goods['review_status'] > 2) { ?>style="display:none"<?php } ?>><?php if($goods['review_status'] == 1) { ?>暂时下架<?php } else { ?>暂时缺货<?php } ?></a>
            <?php if($isdrp==1 && $goods['user_id']==0 && $drp>0) { ?>
              <a class="btn-cart box-flex add-to-cart" href="javascript:;" onclick="checkDrp()" <?php if($goods['review_status'] <= 2) { ?>style="display:none"<?php } ?>>我要<?php echo $custom; ?></a>
            <?php } else { ?>
              <a class="btn-cart box-flex add-to-cart <?php if($user_status) { ?> btnCart <?php } ?>" href="javascript:;" onclick="addToCart_quick(<?php echo ($goods['goods_id']); ?>)" <?php if($goods['review_status'] <= 2) { ?>style="display:none"<?php } ?>>加入购物车</a>
            <?php } ?>
             <a class="btn-submit box-flex click-show-attr add-to-cart" href="javascript:;" <?php if($goods['review_status'] <= 2) { ?>style="display:none"<?php } ?>>立即购买</a>
        </section>
        <?php if($show_warehouse) { ?>
        <!--仓库s-->
        <div class="filter-depot-div ts-5 c-filter-div c-depot-div">
            <section class="close-filter-div j-close-filter-div">
                <div class="close-f-btn">
                    <i class="iconfont icon-fanhui"></i>
                    <span>关闭</span>
                </div>
            </section>
            <section class="con-filter-div">
                <div class="select-two j-get-depot-one">
                    <ul class="padding-all">
                        <?php $n=1;if(is_array($warehouse_list)) foreach($warehouse_list as $warehouse) { ?>
                        <li class="ect-select" onclick="warehouse(<?php echo ($warehouse['region_id']); ?>, <?php echo $goods_id; ?>)">
                            <label class="ts-1 <?php if($region_id == $warehouse['region_id']) { ?>active<?php } ?>"><?php echo ($warehouse['region_name']); ?><i class="fr iconfont icon-gou ts-1"></i></label>
                        </li>
                        <?php $n++;}unset($n); ?>
                    </ul>
                </div>
            </section>
        </div>
        <!--仓库e-->
        <?php } ?>

    </form>
</div>
    <!--地区选择 s-->
    <div class="choose-address-page" id="chooseAddressPage">
	<div class="head-fix">
		<header>
			<div class="jd-index-header">
				<div class="jd-index-header-icon-back">
					<span id="goBack"></span>
				</div>
				<div class="jd-index-header-title"></div>
			</div>

		</header>
		<ul class="head-address-ul" id="headAddressUl">
			<li mytitle="0"></li>
			<li mytitle="1"></li>
			<li mytitle="2"></li>
			<li mytitle="3"></li>
			<li mytitle="4"></li>
		</ul>
	</div>
	<div class="address-container" id="addressContainer">
		<div class="address-content" id="addressContentDiv">
			<ul class="address-ul"></ul>
			<ul class="address-ul"></ul>
			<ul class="address-ul"></ul>
			<ul class="address-ul"></ul>
			<ul class="address-ul"></ul>
		</div>
	</div>
</div>
    <!--地区选择 e-->
    <div class="shopping-prompt ts-2">
        <img src="<?php echo elixir('img/fengxiang.png');?>" />
    </div>
    <div class="goods-bg-box">
        <div class="goods-list-close position-abo"><i class="iconfont icon-guanbi2 text-r"></i></div>
    </div>

<script type="text/javascript">
    $('.j-del-store').click(function(){
        $(this).siblings('span').text('门店自提');
        $('#store_id').val('');
        $(this).removeAttr('style');
        $(this).children().removeClass('icon-guanbi2').addClass('icon-jiantou tf-180');
        $.ajax({
            url :　"<?php echo url('ClearStoreId');?>"
        });
    });
    /*商品详情相册切换*/
    var swiper = new Swiper('.goods-photo', {
        paginationClickable: true,
        onInit: function(swiper) {
            document.getElementById("g-active-num").innerHTML = swiper.activeIndex + 1;
            document.getElementById("g-all-num").innerHTML = swiper.slides.length;
        },
        onSlideChangeStart: function(swiper) {
            document.getElementById("g-active-num").innerHTML = swiper.activeIndex + 1;
        }
    });
    /*推荐商品滚动*/
    var swiper = new Swiper('.j-g-s-p-con', {
        scrollbarHide: true,
        slidesPerView: 'auto',
        centeredSlides: false,
        grabCursor: true
    });
    $(function(){
        changePrice('onload');
        //商品详情属性弹出层
        $(".click-show-attr").click(function(){
            $(".show-goods-attr").addClass("show");
            $(".mask-filter-div").addClass("show");
        });
    })
    /**
     * 点选可选属性或改变数量时修改商品价格的函数
     */
    function changePrice(type)
    {
        var max_number = <?php if($xiangou == 1) { if($goods['is_xiangou']==1 && $goods['xiangou_num'] > 0) { echo ($goods['xiangou_num']); } else { ?>-1<?php } } else { ?>-1<?php } ?>;
        var min_number = $(".min_quantity").html() ? parseInt($(".min_quantity").html()) : 1; // 判断购买数量不能小于最小起订量

        var qty = $("#goods_number").val();
        if(type == 1){
            if(qty > min_number){
                qty--;
            }
        }
        if(type == 3){
            if(max_number == -1){
                max_number = $(".goods_attr_num").html() ? parseInt($(".goods_attr_num").html()) : 1;
            }
            if(qty < max_number){
                qty++;
            }
        }
        if(qty <=0 ){ qty=1; }
        if(!/^[0-9]*$/.test(qty)){ qty = 1 }
        var attr = getSelectedAttributes(document.forms['ECS_FORMBUY']);

        //ecmoban模板堂 --zhuo start 限购
        <?php if($xiangou == 1) { ?>
            <?php if($goods['is_xiangou'] == 1 && $goods['xiangou_num'] > 0) { ?>
                var xiangou_num = <?php echo ($goods['xiangou_num']); ?>;
            var xiangou = <?php echo $xiangou; ?>;
            if(qty > xiangou_num && xiangou_num > 0 && xiangou == 1){
                d_messages('不能超过限购数量');
                return false;
            }
            <?php } ?>
        <?php } ?>
        var warehouse = <?php if($region_id) { echo $region_id; } else { ?>0<?php } ?>;
        var area = <?php if($area_id) { echo $area_id; } else { ?>0<?php } ?>;

        var attr_id = '';
        <?php if(config('add_shop_price') == 0) { ?>
        attr_id = getSelectedAttributesGroup(document.forms['ECS_FORMBUY']);
        <?php } ?>

        $.get('<?php echo url("price");?>', {'id':<?php echo $goods_id; ?>, 'attr':attr, 'goods_attr':attr_id, 'number':qty, 'warehouse_id':warehouse, 'area_id':area}, function(data){
            changePriceResponse(data);
        }, 'json');
    }
    /**
     * 获得选定的商品属性组
     */
    function getSelectedAttributesGroup(formBuy)
    {
        var spec_arr = new Array();
        var j = 0;

        $("form[name='ECS_FORMBUY'] :input[name='spec_list']").each(function(index, element) {
            spec_arr[j] = ($(this).val());
            j++ ;
        });

        return spec_arr.join(',');
    }

    /**
     * 接收返回的信息
     */
    function changePriceResponse(res){
        if (res.err_msg.length > 0){
            d_messages(res.err_msg);
        } else {
            //属性图片
            if(res.attr_img) {
                $(".s-g-attr-title .product-list-img").attr('src',res.attr_img);
            }
            // 用户可购买的数量
            var number = res.limit_number;
            // 限购数量大于0且小于起订量 显示起订量
            if(res.limit_number > 0 && res.min_quantity > 0){
                if(res.limit_number < res.min_quantity){
                    $("#goods_number").val(res.min_quantity);
                    number = parseInt($("#goods_number").val());
                }
            }
            $("#goods_number").val(number);
            // 显示最小起订量
            if ($(".min_quantity").length > 0){
                $(".min_quantity").html(res.min_quantity);
            }
            //更改数量的同时显示
            var get_text = '';
            s_get_label = $(".show-goods-attr .s-g-attr-con").find("label.active"); //获取被选中label
            if(s_get_label.length > 0){
                s_get_label.each(function() {
                    get_text += $(this).text() + "、";
                });
            }
            var goods_number = $("#goods_number").val();
            goods_number = parseInt(goods_number) ? parseInt(goods_number) : 1;
            get_text = get_text + goods_number + "个";
            $(".j-goods-attr").find(".t-goods1").text(get_text);
            if ($(".goods_attr_num").length > 0){
                $(".goods_attr_num").html(res.attr_number);
            }
            if(res.err_no == 2){
                d_messages("该地区暂不支持配送");
            }
            else{
                if(res.attr_number <= 0){
                    $(".add-to-cart").hide();
                    $(".quehuo").show();
                }
                else{
                    <?php if($goods['review_status'] > 2) { ?>
                    $(".add-to-cart").show();
                    $(".quehuo").hide();
                    <?php } ?>
                }
            }
            //总价
            if ($("#ECS_GOODS_AMOUNT").length > 0){
                $("#ECS_GOODS_AMOUNT").html(res.result);
            }
            var total_res;
            if($("#ECS_SHOPPRICE").length > 0){
                <?php if(config('shop.add_shop_price') == 1) { ?>
                    total_res = res.result;
                <?php } else { ?>
                    if(res.show_goods == 1){
                        total_res = res.spec_price;
                    }else{
                        total_res = res.result;
                    }
                <?php } ?>
                $("#ECS_SHOPPRICE").html(total_res);
                $("#ECS_GOODS_AMOUNT").html(total_res);

            }
            if($("#ECS_DISCOUNT").length > 0){
                $("#ECS_DISCOUNT").html(res.discount);
            }
            if($("#ECS_MARKETPRICE").length > 0){
                $("#ECS_MARKETPRICE").html(res.result_market);
            }
        }
    }

    // 检查是否申请分销商
    function checkDrp(){
        var goods_id = '<?php echo $goods_id; ?>';
        var user_id = '<?php echo $user_id; ?>';
        var url = "<?php echo url('goods/index/check_drp');?>";
        if(goods_id){
            $.get(url, {'id':goods_id, 'u':user_id},
                function(result){
                    if(result.code){
                        if(!$(".shopping-prompt").hasClass("active")){
                            $(".shopping-prompt").addClass("active");
                        }
                    }
                    else{
                        layer.open({
                            content: '是否申请成为<?php echo $custom; ?>商',
                            btn: ['确定', '取消'],
                            shadeClose: false,
                            yes: function() {
                                window.location.href = "<?php echo url('drp/index/shopnotice');?>";
                            },
                            no: function() {
                            }
                        });
                    }
                }, 'json');
        }
        return false;
    }

</script>

<script type="text/javascript">
$(function($) {
    // 元素以及其他一些变量
    var eleFlyElement = document.querySelector("#flyItem"),
        eleShopCart = document.querySelector("#shopCart");
    var numberItem = 0;
    // 抛物线运动
    var myParabola = funParabola(eleFlyElement, eleShopCart, {
        speed: 10, //抛物线速度
        curvature: 0.009, //控制抛物线弧度
        complete: function() {
            eleFlyElement.style.visibility = "hidden";
            //eleShopCart.querySelector("span").innerHTML = ++numberItem;
        }
    });
    // 绑定点击事件
    if(eleFlyElement && eleShopCart) {

        [].slice.call(document.getElementsByClassName("btnCart")).forEach(function(button) {
            button.addEventListener("click", function(event) {
                // 滚动大小
                var scrollLeft = document.documentElement.scrollLeft || document.body.scrollLeft || 0,
                    scrollTop = document.documentElement.scrollTop || document.body.scrollTop || 0;
                eleFlyElement.style.left = event.clientX + scrollLeft + "px";
                eleFlyElement.style.top = event.clientY + scrollTop + "px";
                eleFlyElement.style.visibility = "visible";

                // 需要重定位
                myParabola.position().move();
            });
        });
    }
});
</script>

<div class="filter-top" id="scrollUp">
    <i class="iconfont icon-jiantou"></i>
</div>
    <div class="goods-nav ts-3">
        <ul class="goods-nav-box">
        	<!--<a href="<?php echo url('index/messagelist');?>">
                <li><i class="iconfont icon-xiaoxi1"></i>消息</li>
         </a>-->
            <a href="<?php echo url('/');?>">
                <li><i class="iconfont icon-home"></i>首页</li>
            </a>
            <li class="box-flex j-search-input position-rel"><i class="iconfont icon-sousuo"></i>搜索</li>
            <a href="<?php echo url('category/index/index');?>">
                <li><i class="iconfont icon-caidan"></i>分类</li>
            </a>
            <a href="<?php echo url('user/index/index');?>">
                <li style="border:none"><i class="iconfont icon-geren"></i>个人中心</li>
            </a>
        </ul>
    </div>
    <div class="goods-scoll-bg"></div>
    <div class="search-div j-search-div ts-3">
    <section class="search">
        <form action="<?php echo url('/');?>?m=category&a=search" method="post" id="search-form">
        <div class="text-all dis-box j-text-all text-all-back">
            <a class="a-icon-back j-close-search" href="javascript:;"><i class="iconfont icon-back"></i></a>
            <div class="box-flex input-text">
                <label class="search-check j-search-check" data="2">
                    <span>商品</span><i class="iconfont icon-xiajiantou"></i>
                </label>
                <input class="j-input-text" type="text" name="keyword" placeholder="<?php if($keywords) { echo $keywords; } else { ?>商品/店铺搜索<?php } ?>" />
                <input type="hidden" name="type_select" value="2" />
                <input type="hidden" name="isself" value="<?php echo $isself; ?>" />
                <input type="hidden" name="brand" value="<?php echo $brand_id; ?>" />
                <input type="hidden" name="price_min" value="<?php echo $price_min; ?>" />
                <input type="hidden" name="price_max" value="<?php echo $price_max; ?>" />
                <input type="hidden" name="filter_attr" value="<?php echo $filter_attr; ?>"/>
                <i class="iconfont icon-guanbi1 is-null j-is-null"></i>
            </div>
            <a type="button" class="btn-submit" onclick="$('#search-form').submit()">搜索</a>
        </div>
        </form>
    </section>
    <section class="search-con">
        <div class="history-search">
            <!--<div class="swiper-wrapper">
                <div class="swiper-slide">-->
                    <p>
                        <label class="fl">热门搜索</label>
                    </p>
                    <ul class="hot-search a-text-more">
                        <?php $n=1;if(is_array($hot_keywords)) foreach($hot_keywords as $v) { ?>
                        <li class="w-3"><a href="<?php echo url('category/index/search', array('keyword'=>$v));?>"><span class="onelist-hidden"><?php echo $v; ?></span></a></li>
                        <?php $n++;}unset($n); ?>
                    </ul>
                    <p class="hos-search">
                        <label class="fl">最近搜索</label>
                        <span class="fr clear_history onelist-hidden" ><i class="iconfont icon-xiao10"></i></span>
                    </p>
                    <?php if($history_keywords) { ?>
                    <ul class="hot-search a-text-more a-text-one search-new-list" id="search-con">
                        <?php $n=1;if(is_array($history_keywords)) foreach($history_keywords as $v) { ?>
                        <?php if($v) { ?>
                        <li><a href="<?php echo url('category/index/search', array('keyword'=>$v));?>"><span class="onelist-hidden"><?php echo $v; ?></span></a></li>
                        <?php } ?>
                        <?php $n++;}unset($n); ?>
                    </ul>
                    <?php } else { ?>
                    <div class="no-div-message">
                        <p>暂无搜索记录</p>
                    </div>
                    <?php } ?>
                <!--</div>
            </div>-->
        <div class="swiper-scrollbar"></div>
        </div>
    </section>
    <footer class="close-search j-close-search">
        点击关闭
    </footer>
</div>
<script type="text/javascript">

    $(function(){
        //清除搜索记录
        var history = <?php if($history_keywords) { echo $history_keywords; } else { ?>""<?php } ?>;
        $(".clear_history").click(function(){
            if(history && $("ul#search-con").length > 0){
                $.get("<?php echo url('category/index/clear_history');?>", '', function(data){
                    if(data.status){
                        $("#search-con").remove();
                        var no = '<div class="no-div-message"><p>暂无搜索记录</p></div>';
                        $(".hos-search").after(no);
                    }
                }, 'json');
            }
        });
    })
</script>

</body>
</html>