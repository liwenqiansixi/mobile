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
<div class="con">
    <div class="flow-cart blur-div">
        <!-- <?php if(!$goods_list) { ?> -->
        <section class="flow-no-cart mb-7">
            <span class="gwc-bg">
                        <i class="iconfont icon-gouwuche"></i>
                    </span>
            <p class="t-remark text-center">购物车什么也没有</p>
            <a href="<?php echo url('/');?>" type="button" class="btn-default">去逛逛</a>

            <div class="f-n-c-prolist product-one-list j-f-n-c-prolist b-color-f">
                <h3 class="padding-all" style="font-size:1.6rem; padding-bottom:.4rem;">你可能想要</h3>
                <div class="swiper-wrapper">
                    <!-- <?php $n=1;if(is_array($relation)) foreach($relation as $key) { ?> -->
                    <li class="swiper-slide">
                        <div class="product-div">
                            <a class="product-div-link" href="<?php echo ($key['goods_url']); ?>"></a>
                            <img class="product-list-img" src="<?php echo ($key['goods_thumb']); ?>"/>
                            <div class="product-text m-top06">
                                <h4 style="line-height:1.6rem; height:3rem"><?php echo ($key['goods_name']); ?></h4>
                                <?php if($user_status) { ?><p><span class="p-price t-first "><?php echo ($key['current_price']); ?></span></p><?php } ?>
                            </div>
                    </li>
                    <!-- <?php $n++;}unset($n); ?> -->
                </div>
            </div>
    </div>
    </section>
    <!-- <?php } else { ?> -->
    <!-- 按店铺显示商品  start-->
    <!-- <?php $n=1;if(is_array($goods_list)) foreach($goods_list as $key) { ?> -->
    <section class="flow-have-cart select-three j-select-all">
        <section class="j-cart-get-i-more shop<?php echo ($key['ru_id']); ?>" num="<?php echo ($key['num']); ?>">
            <header class="b-color-f padding-all of-hidden dis-box">
                <div class="ect-select box-flex is-shop">
                    <label class="dis-box label-this-all active">
                        <i class="j-select-btn"></i>
                        <span class="box-flex"><a
                                href="<?php if($key['ru_id']>0) { echo url('store/index/shop_info',array('id'=>$key['ru_id'])); } ?>"><?php echo ($key['ru_name']); ?></a></span>
                    </label>
                </div>
                <?php if($key['fitting_goods_array'] ) { ?>
                <em><a style="margin-top: 0rem"
                       href="<?php echo url('cart/index/goods_fittings',array('goods_list'=>$key['shop_goods_list']));?>"
                       class="a-accessories fr">相关配件</a></em>
                <?php } ?>
            </header>

            <div class="m-top1px b-color-f padding-all product-list-small">
                <ul>

                    <li>
                        <?php if($key['favourable']) { ?>
                        <div class="g-promotion-con ts-5 j-show-favourable" <?php if($key['is_show_favourable'] == 0) { ?>style="display: none;"<?php } ?>>
                            <?php if(count($key['favourable'])>0) { ?>
                                <?php $n=1; if(is_array($key['favourable'])) foreach($key['favourable'] as $keylist => $list) { ?>
                                <?php if($keylist==0) { ?>
                                <p class="dis-box  j-icon-show"><em class="em-promotion ec-promotion1">
                                    <?php if($list['act_type']==0) { ?>
                                    赠品、促销
                                    <?php } elseif ($list['act_type']==1) { ?>
                                    满减
                                    <?php } elseif ($list['act_type']==2) { ?>
                                    折扣
                                    <?php } ?>
                                </em><span class="box-flex"><span class="g-p-c-promotion">
                                                    促销
                                                </span><span class="g-p-c-text">
                                                    <?php echo ($list['act_name']); ?>
                                                </span></span><span class="t-jiantou"><i
                                        class="iconfont icon-jiantou tf-180 ts-3"></i></span></p>
                                <?php } ?>
                                <?php $n++;}unset($n); ?>
                                <div class="g-promotion-con-sh m-top04">
                                    <?php $n=1; if(is_array($key['favourable'])) foreach($key['favourable'] as $keylist => $list) { ?>
                                    <?php if($list['act_type']==0) { ?>
                                    <a href="<?php echo url('activity',array('act_id'=>$list['act_id']));?>">
                                        <?php } ?>
                                        <p class="dis-box"><em class="em-promotion">
                                            <?php if($list['act_type']==0) { ?>
                                            赠品、促销
                                            <?php } elseif ($list['act_type']==1) { ?>
                                            满减
                                            <?php } elseif ($list['act_type']==2) { ?>
                                            折扣
                                            <?php } ?>
                                        </em><span class="box-flex">
                                                        <?php echo ($list['act_name']); ?>
                                                    </span><span class="t-jiantou"><i
                                                class="iconfont icon-jiantou tf-180 m-top04"></i></span></p>
                                        <?php if($list['act_type']==0) { ?>
                                    </a>
                                    <?php } ?>
                                    <?php $n++;}unset($n); ?>
                                </div>
                            <?php } ?>
                        </div>
                        <?php } ?>
                        <!-- <?php $n=1;if(is_array($key['goods_list'])) foreach($key['goods_list'] as $list) { ?> -->
                        <?php if($list['store_name']) { ?>
                        <div class="f-05" style="padding:1rem 0;border-bottom:1px solid #f6f6f9;color:#666">
                            门店名称：<?php echo ($list['store_name']); ?>
                        </div>
                        <?php } ?>
                        <div class="dis-box drop<?php echo ($list['rec_id']); ?> com-post-adr">
                            <input type="hidden" class="total" price="<?php echo ($list['amount']); ?>" number="<?php echo ($list['goods_number']); ?>">
                            <div class="ect-select">
                                <label class="active rec-active" goods-id="<?php echo ($list['goods_id']); ?>" rec-id="<?php echo ($list['rec_id']); ?>" <?php if($list['store_id']) { ?>store_id="<?php echo ($list['store_id']); ?>" <?php } ?>>
                                <i class="j-select-btn"></i>
                                </label>
                            </div>
                            <div class="box-flex">

                                <div class="product-div">
                                    <div class="p-d-img fl">
                                        <?php if($list['extension_code'] == 'package_buy') { ?>
                                        <a>
                                            <?php } else { ?>
                                            <a href="<?php echo ($list['url']); ?>">
                                                <?php } ?>
                                                <img class="product-list-img" src="<?php echo ($list['goods_thumb']); ?>"/>
                                            </a>
                                            <!-- <?php if($list['parent_id']>0) { ?> -->
                                            <span>配件</span>
                                            <!-- <?php } ?> -->
                                    </div>
                                    <div class="product-text">
                                        <h4 class="onelist-hidden"><?php if($list['extension_code'] == 'package_buy') { ?><a><?php } else { ?><a
                                                href="<?php echo ($list['url']); ?>"><?php } echo ($list['goods_name']); ?></a></h4>
                                        <?php if($user_status) { ?><span class="t-first j-item-<?php echo ($list['rec_id']); ?>-price"><?php echo ($list['goods_price']); ?></span><?php } ?>
                                        <p class="p-t-remark onelist-hidden"><?php echo ($list['goods_attr']); ?>

                                        </p>

                                        <div class="div-num dis-box m-top04">
                                            <a class="num-up" data-min-num="<?php if($list['min_quantity']) { echo ($list['min_quantity']); } else { ?>1<?php } ?>"></a>
                                            <input class="box-flex cart-number active" type="number" name="cart_number"
                                                   readonly
                                                   value="<?php if($list['parent_id']>0 || $list['is_gift']>0 ) { ?>1<?php } else { echo ($list['goods_number']); } ?>"
                                                   id="<?php echo ($list['goods_id']); ?>" is_gift="<?php echo ($key['act_id']); ?>"
                                                   cart-id="<?php echo ($list['rec_id']); ?>"/>
                                            <a class="num-next" xiangounum="<?php echo ($list['xiangounum']); ?>"
                                               data-max-num="<?php if($list['parent_id']>0 || $list['is_gift']>0 ) { ?>1<?php } else { ?>999<?php } ?>"></a>
                                        </div>

                                        <i class="iconfont icon-xiao10"
                                           onclick="DropCart(<?php echo ($list['rec_id']); ?>,<?php echo ($key['ru_id']); ?>)"></i>
                                    </div>
                                </div>

                            </div>
                        </div>
                        <!-- <?php $n++;}unset($n); ?> -->
                    </li>

                </ul>
            </div>
        </section>
        <!-- <?php $n++;}unset($n); ?> -->
        <!-- 按店铺显示商品  end-->
        <section>
            <section class="padding-all text-center t-remark2 ">
                <p class="j-goodsinfo-div">推荐商品</p>
            </section>
            <section class="product-list j-product-list product-list-medium new-flow-bottom">
                <ul>
                    <!-- <?php $n=1;if(is_array($relation)) foreach($relation as $key) { ?> -->
                    <li>
                        <div class="product-div">
                            <a href="<?php echo ($key['goods_url']); ?>"><img class="product-list-img" src="<?php echo ($key['goods_thumb']); ?>"/></a>
                            <div class="product-text">
                                <a href="<?php echo ($key['url']); ?>"><h4><?php echo ($key['goods_name']); ?></h4></a>
                                <p class="dis-box p-t-remark"><span class="box-flex">库存:<?php echo ($key['goods_number']); ?></span><span
 class="box-flex">销量:<?php echo ($key['sales_volume']); ?></span></p>
                                <p><span class="p-price t-first ">
                                <?php if($user_status) { ?>
                                             <?php if($key['is_promote'] && $key['gmt_end_time']) { ?>
                                             <?php echo ($key['promote_price']); ?>
                                             <?php } else { ?>
                                             <?php echo ($key['shop_price_formated']); ?>
                                             <?php } ?>
                                <?php } ?>
                                 <small><del><?php echo ($key['market_price']); ?></del></small></span></p>
                                <a onclick="addToCart(<?php echo ($key['goods_id']); ?>, 0)" class="icon-flow-cart j-goods-attr fr"><i class="iconfont icon-gouwuche"></i></a>
                            </div>
                        </div>
                    </li>
                    <!-- <?php $n++;}unset($n); ?> -->
                </ul>
            </section>
        </section>
    </section>

</div>
</div>
<!--领取优惠券star-->
<div class="show-goods-coupon j-filter-show-div ts-3 b-color-1">
    <section class="goods-show-title of-hidden  padding-all b-color-f">
        <h3 class="fl g-c-title-h3">领取店铺优惠券 (<span class="bonus-number">0</span>)</h3>
        <i class="iconfont icon-guanbi2 show-div-guanbi fr"></i>
    </section>
    <!-- 优惠卷 -->
    <section class="goods-show-con padding-all swiper-scroll swiper-container-vertical swiper-container-free-mode"
             id="goods-show-con">
        <div class="swiper-wrapper">
            <div class="swiper-slide swiper-slide-active cart-bonus">

            </div>
        </div>
        <div class="swiper-scrollbar"></div>

    </section>

    <!-- end/优惠卷  -->
</div>
<div class="mask-filter-div"></div>
<!--领取优惠券end-->
<input type="hidden" name="warehouse_id" value="<?php echo $warehouse_id; ?>">
<input type="hidden" name="area_id" value="<?php echo $area_id; ?>">
<!--悬浮btn star-->
<footer class="flow-cart-btn">
    <section class="filter-btn f-cart-filter-btn dis-box n-flow-btn">
        <div class="box-flex select-three j-cart-get-more j-get-more-all pl">
            <div class="ect-select">
                <label class="dis-box label-all active">
                    <i class="select-btn"></i>
                    <span class="box-flex">全选</span></span>
                </label>
            </div>
            <div class="g-cart-filter-price of-hidden">
                <p class="dis-box"><em class="dis-block">合计：</em>
                    <span class="t-first box-flex onelist-hidden cart-price-show"></span>
                    <span class="t-first box-flex onelist-hidden cart-price-hidden" style="display:none"></span>
                </p>
                <p class="t-remark">不含运费</p>
            </div>
        </div>
        <div class="g-cart-filter-sb">
            <span class="span-bianji fl"><i class="iconfont icon-bianji1"></i><em>编辑</em></span>
            <form id="formid" action="<?php echo url('flow/index/index');?>" class="fl" method="post">
                <input type="hidden" name="cart_value" value="<?php echo $cart_value; ?>">
                <input type="hidden" name="store_id"/>
                <a type="button" class="btn-submit fl" onclick="c_value()">结算 <span class='cart-number-show f-05'>(<?php echo ($cart_show['cart_goods_number']); ?>)</span></a>
            </form>
        </div>
        <div class="g-cart-filter-bj">
            <span class="heart j-heart fl"><i class="ts-2"></i><em class="ts-2">收藏</em></span>
            <a type="button" class="btn-default j-btn-default fl">返回</a>
            <a type="button" class="btn-submit fl delete">删除</a>
        </div>
    </section>
    <footer class="footer-nav dis-box p-s">
        <a href="<?php echo url('/');?>" class="box-flex nav-list">
            <i class="nav-box i-home"></i><span>首页</span>
        </a>
        <a href="<?php echo url('category/index/index');?>" class="box-flex nav-list">
            <i class="nav-box i-cate"></i><span>分类</span>
        </a>
        <a href="<?php echo url('search/index/index');?>" class="box-flex nav-list j-search-input">
            <i class="nav-box i-shop"></i><span>搜索</span>
        </a>
        <a href="<?php echo url('cart/index/index');?>" class="box-flex position-rel nav-list  active">
            <i class="nav-box i-flow"></i><span>购物车</span>
        </a>
        <?php if($filter) { ?>
        <a href="<?php echo url('drp/user/index');?>" class="box-flex nav-list">
            <i class="nav-box i-user"></i><span><?php echo $custom; ?>中心</span>
        </a>
        <?php } elseif ($community) { ?>
        <a href="<?php echo url('community/index/index');?>" class="box-flex nav-list">
            <i class="nav-box i-user"></i><span>社区</span>
        </a>
        <?php } else { ?>
        <a href="<?php echo url('user/index/index');?>" class="box-flex nav-list">
            <i class="nav-box i-user"></i><span>我</span>
        </a>
        <?php } ?>
    </footer>
</footer>
<!-- <?php } ?> -->

<!--悬浮btn star-->

<footer class="footer-nav dis-box">
    <a href="<?php echo url('/');?>" class="box-flex nav-list">
        <i class="nav-box i-home"></i><span>首页</span>
    </a>
    <a href="<?php echo url('category/index/index');?>" class="box-flex nav-list">
        <i class="nav-box i-cate"></i><span>分类</span>
    </a>
    <a href="<?php echo url('search/index/index');?>" class="box-flex nav-list">
        <i class="nav-box i-shop"></i><span>搜索</span>
    </a>
    <a href="<?php echo url('cart/index/index');?>" class="box-flex position-rel nav-list  active">
        <i class="nav-box i-flow"></i><span>购物车</span>
    </a>
    <?php if($filter) { ?>
    <a href="<?php echo url('drp/user/index');?>" class="box-flex nav-list">
        <i class="nav-box i-user"></i><span><?php echo $custom; ?>中心</span>
    </a>
    <?php } elseif ($community) { ?>
    <a href="<?php echo url('community/index/index');?>" class="box-flex nav-list">
        <i class="nav-box i-user"></i><span>社区</span>
    </a>
    <?php } else { ?>
    <a href="<?php echo url('user/index/index');?>" class="box-flex nav-list">
        <i class="nav-box i-user"></i><span>我</span>
    </a>
    <?php } ?>
</footer>

<script>
    var currency_format = '<?php echo $currency_format; ?>';

    function attrprice(id) {
        var attr = '';
        $("label.ts-1" + id).each(function () {
            if ($(this).hasClass("active")) {
                attr += $(this).attr("attr-id") + ',';
            }
        })
        attr = attr.substr(0, attr.length - 1);
        var number = $("input[name=number" + id + "]").val();
        var warehouse_id = $("input[name=warehouse_id]").val();
        var area_id = $("input[name=area_id]").val();
        $.get(ROOT_URL + 'index.php?m=goods&a=price', {
            id: id,
            warehouse_id: warehouse_id,
            area_id: area_id,
            number: number,
            attr: attr
        }, function (data) {
            $(".goods_attr_num" + id).text(data.attr_number);
            $("#ECS_GOODS_AMOUNT" + id).text(data.result);
            if (data.attr_number < 1) {
                $(".add-to-cart" + id).hide();
                $(".quehuo" + id).show();
            } else {
                $(".add-to-cart" + id).show();
                $(".quehuo" + id).hide();
            }
        }, 'json')

    }

    function show(html) {
        $(".mask-filter-div").addClass("show");
        $(".j-show-goods-attr" + html).addClass("show");
    }

    function c_value() {
        var id = '';
        var store_id = new Array();
        $("input[name=store_id]").val('');
        $("input[name=cart_value]").val('');
        $("label").each(function () {
            if ($(this).hasClass("rec-active")) {
                if ($(this).hasClass("active")) {
                    id += $(this).attr("rec-id") + ',';
                    //门店ID
                    if ($(this).attr('store_id') != undefined) {
                        store_id.push($(this).attr('store_id'));
                    }
                }
            }
        });
        if (id == '') {
            d_messages('至少选中一个商品', 2);
            return false;
        }
        id = id.substr(0, id.length - 1);
        $("input[name=cart_value]").val(id);
        //门店ID
        if (store_id.length == 1 == $('.rec-active.active').length && $('.rec-active.active').attr('store_id') == store_id[0]) {
            $("input[name=store_id]").val(store_id[0]);
        }
        //门店ID   END
        document.getElementById("formid").submit();
    }
    //加载
    var price = <?php echo ($total['goods_amount']); ?>;
    var k = 0;
//    $(".total").each(function () {
//        console.log($(this).attr("price"));
//        price += $(this).attr("price") * 1;
//
//    })

    $(".cart-price-show").text(currency_format + price.toFixed(2));
    //删除
    function DropCart(id, sid) {
        var shopnum = $(".shop" + sid).attr("num");
        $.ajax({
            type: "post",
            url: ROOT_URL + 'index.php?m=cart&a=delete_cart',
            data: {
                id: id
            },
            dataType: "json",
            success: function (data) {
                if (data.error == 0) {
                    if (shopnum - 1 < 1) {
                        $(".shop" + sid).html("");
                        window.location.href = ROOT_URL + "index.php?m=cart";
                    } else {
                        shopnum = shopnum - 1;
                        $(".shop" + sid).attr("num", shopnum);
                    }
                    $(".drop" + id).html("");
                    var price = 0;
                    var k = 0;
                    $(".total").each(function () {
                        price += $(this).attr("price") * 1;
                    })
                    $(".cart-number").each(function () {
                        k += $(this).val() * 1;
                    })
                    $(".cart-number-show").text(k);
                    $(".cart-price-show").text(currency_format + price.toFixed(2));
                    d_messages('已删除');
                }
            }
        });
    }
    var heartstatus = 1;
    $(".heart").click(function () {
        var id = '';
        $(".com-post-adr label.active").each(function () {
            id += $(this).attr("goods-id") + ',';
        })
        $.get(ROOT_URL + 'index.php?m=cart&a=heart', {
            id: id,
            status: heartstatus
        }, function (data) {
            heartstatus++;
            if (data.error == 1) {
                $(".heart").addClass("active");
            } else if (data.error == 2) {
                $(".heart").removeClass("active");
            }
            if (data.error > 0) {
                d_messages(data.msg);
            } else {
                layer.open({
                    content: '请登录后关注',
                    btn: ['立即登录', '取消'],
                    shadeClose: false,
                    yes: function () {
                        window.location.href = ROOT_URL + 'index.php?m=user&c=login';
                    },
                    no: function () {
                    }
                });
            }
        }, 'json')
    })
    $(".delete").click(function () {
        var id = '';
        $("label").each(function () {
            if ($(this).hasClass("rec-active")) {
                if ($(this).hasClass("active")) {
                    id += $(this).attr("rec-id") + ',';
                }
            }
        })
        if (id == '') {
            d_messages('至少选中一个商品', 2);
            return false;
        }
        $.get(ROOT_URL + 'index.php?m=cart&a=drop_goods', {
            id: id
        }, function (data) {

        }, 'json')
        window.location.href = ROOT_URL + "index.php?m=cart";
    })
    //弹出优惠券
    /*-*/
    $(".j-goods-coupon").click(function () {
        document.addEventListener("touchmove", handler, false);
        var ru_id = $(this).attr("ru-id");
        $.ajax({
            type: "POST",
            url: ROOT_URL + "index.php?m=cart&a=cart_bonus",
            data: {
                ru_id: ru_id
            },
            dataType: "json",
            async: false,
            success: function (data) {
                if (data.data != 0) {
                    $(".cart-bonus").html(data.data);
                    $(".bonus-number").text(data.number);
                    $(".mask-filter-div").addClass("show");
                    $(".show-goods-coupon").addClass("show");
                }
            }
        });
        swiper_scroll();
    });
    /*-*/
    $(".div-num a").click(function () {
        if (!$(this).parent(".div-num").hasClass("div-num-disabled")) {
            var t = $(this);
            var is_gift = parseInt($(this).siblings("input").attr("is_gift"));

            if ($(this).hasClass("num-up")) {
                num = parseInt($(this).siblings("input").val());
                min_num = parseInt($(this).attr("data-min-num"));
                rec = parseInt($(this).siblings("input").attr("cart-id"));
                if (num > min_num) {
                    num -= 1;
                    $(this).siblings("input").val(num);
                    if ($(this).siblings("input").hasClass("active")) {
                        none = 0;
                    } else {
                        none = 1;
                    }
                    var arr = '';
                    $(".rec-active").each(function () {
                        if ($(this).hasClass("active")) {
                            arr += $(this).attr("rec-id") + ',';
                        }
                    })
                    $.ajax({
                        type: "POST",
                        url: ROOT_URL + "index.php?m=cart&a=cart_goods_number",
                        dataType: "json",
                        data: {
                            id: rec,
                            number: num,
                            act_id: is_gift,
                            arr: arr,
                            none: none
                        },
                        success: function (data) {
                            if (data.none > 0) {
                                return;
                            }
                            if (data.error) {
                                d_messages(data.msg);
                                return;
                            }
                            if(data.is_show_favourable==1) {
                                $('.j-show-favourable').css('display', 'block');
                            }else if(data.is_show_favourable==0) {
                                $('.j-show-favourable').css('display', 'none');
                                if(data.remove_rec_id != ''){
                                    for(var i in data.remove_rec_id){
                                        $('.drop' + data.remove_rec_id[i]).remove();
                                    }
                                }
                            }
                            var number = 0;
                            $(".cart-number").each(function () {
                                if ($(this).hasClass("active")) {
                                    number += $(this).val() * 1;
                                }
                            })
                            if (number > data.max_number) {
                                number = data.max_number;
                            }
                            $(".cart-number-show").html('(' + number.toString() + ')');
                            $(".j-item-" + rec + "-price").html(data.shop_price);
                            $(".cart-price-show").html(data.content);
                            $(".cart-price-hidden").html(data.content);

                        }
                    });

                } else {
                    d_messages("不能小于最小数量");
                }
                return false;
            }
            if ($(this).hasClass("num-next")) {
                num = parseInt($(this).siblings("input").val());
                max_num = parseInt($(this).attr("data-max-num"));
                max_num = parseInt($(this).attr("data-max-num"));
                xiangounum = parseInt($(this).attr("xiangounum"));
                if (xiangounum) {
                    if (num >= xiangounum) {
                        d_messages('不能超过限购');
                        return;
                    }
                }
                rec = parseInt($(this).siblings("input").attr("cart-id"));
                //限购
                if (num < max_num) {
                    num += 1;
                    $(this).siblings("input").val(num);
                    $(this).siblings("input").val(num);
                    if ($(this).siblings("input").hasClass("active")) {
                        none = 0;
                    } else {
                        none = 1;
                    }
                    var arr = '';
                    $(".rec-active").each(function () {
                        if ($(this).hasClass("active")) {
                            arr += $(this).attr("rec-id") + ',';
                        }
                    })
                    $.ajax({
                        type: "POST",
                        url: ROOT_URL + "index.php?m=cart&a=cart_goods_number",
                        dataType: "json",
                        data: {
                            id: rec,
                            number: num,
                            act_id: is_gift,
                            arr: arr,
                            none: none
                        },
                        success: function (data) {

                            if (data.none > 0) {
                                return;
                            }
                            if (data.error) {
                                d_messages(data.msg);
                                t.siblings("input").val(data.num);
                                return;
                            }
                            if(data.is_show_favourable==1) {
                                $('.j-show-favourable').css('display', 'block');
                            }else if(data.is_show_favourable==0) {
                                $('.j-show-favourable').css('display', 'none');
                            }

                            t.attr("data-max-num", data.max_number);
                            var number = 0;
                            $(".cart-number").each(function () {
                                if ($(this).hasClass("active")) {
                                    number += $(this).val() * 1;
                                }
                            })
                            if (number > data.max_number) {
                                number = data.max_number;
                            }
                            $(".cart-number-show").html('(' + number.toString() + ')');
                            $(".j-item-" + rec + "-price").html(data.shop_price);
                            $(".cart-price-show").html(data.content);
                            $(".cart-price-hidden").html(data.content);
                        }
                    });

                } else {
                    d_messages("不能大超过最大数量");
                }
                return false;
            }
        } else {
            d_messages("该商品不能增减");
        }
    });
    $(".div-num a").click(function () {
        if (!$(this).parent(".div-num").hasClass("div-num-disabled")) {
            if ($(this).hasClass("num-less")) {
                num = parseInt($(this).siblings("input").val());
                min_num = parseInt($(this).attr("data-min-num"));
                if (num > min_num) {
                    num -= 1;
                    $(this).siblings("input").val(num);
                } else {
                    d_messages("不能小于最小数量");
                }
                return false;
            }
            if ($(this).hasClass("num-plus")) {
                num = parseInt($(this).siblings("input").val());
                max_num = parseInt($(this).attr("data-max-num"));
                if (num < max_num) {
                    num += 1;
                    $(this).siblings("input").val(num);
                } else {
                    d_messages("不能大超过最大数量");
                }
                return false;
            }
        } else {
            d_messages("该商品不能增减");
        }
    });
    $(".div-num input").bind("change", function () {
        num = parseInt($(this).val());
        max_num = parseInt($(this).siblings(".num-plus").attr("data-max-num"));
        min_num = parseInt($(this).siblings(".num-less").attr("data-min-num"));
        if (num > max_num) {
            $(this).val(max_num);
            d_messages("不能大超过最大数量");
            return false;
        }
        if (num < min_num) {
            $(this).val(min_num);
            d_messages("不能小于最小数量");
            return false;
        }
    });
    /*多选*/
    $(".j-cart-get-more .ect-select").click(function () {
        if (!$(this).find("label").hasClass("active")) {
            $(this).find("label").addClass("active");
            $("input[name=cart_number]").addClass("active");
            if ($(this).find("label").hasClass("label-all")) {
                $(".j-select-all").find(".ect-select label").addClass("active");
                /*hu*/
                var rec_id = '';
                $(".rec-active").each(function () {
                    var goods_id = $(this).attr("goods-id");
                    if ($(this).hasClass("active")) {

                        if ($(this).attr("rec-id") != undefined && $(this).attr("rec-id") > 0) {
                            rec_id += $(this).attr("rec-id") + ',';
                            $("#" + goods_id + "").addClass("active");
                        }
                    }
                });
                $.ajax({
                    type: "POST",
                    url: ROOT_URL + "index.php?m=cart&a=cart_label_count",
                    data: {
                        id: rec_id
                    },
                    dataType: "json",
                    success: function (data) {
                        $(".cart-number-show").text('(' + data.cart_number + ')');
                        $(".cart-price-show").html(data.content);

                    }
                });
                /*hu*/

            }
        } else {
            $(this).find("label").removeClass("active");
            $("input[name=cart_number]").removeClass("active");
            if ($(this).find("label").hasClass("label-all")) {
                /*hu*/
                $(".cart-price-show").html("￥0.00");
                $(".cart-number-show").text(0);
                /*hu*/
                $(".j-select-all").find(".ect-select label").removeClass("active");
            }
        }
    });
    /*多选只点击单选按钮 - 全选，全不选*/
    $(".j-cart-get-i-more .j-select-btn").click(function () {
        if ($(this).parents(".ect-select").hasClass("j-flowcoupon-select-disab")) {
            d_messages("同商家只能选择一个", 2);
        } else {
            is_select_all = true;
            if ($(this).parent("label").hasClass("label-this-all")) {
                if (!$(this).parent("label").hasClass("active")) {
                    $(this).parents(".j-cart-get-i-more").find(".ect-select label").addClass("active");
                } else {
                    $(this).parents(".j-cart-get-i-more").find(".ect-select label").removeClass("active");
                }
            }

            if (!$(this).parent("label").hasClass("label-this-all") && !$(this).parent("label").hasClass("label-all")) {
                $(this).parent("label").toggleClass("active");
                is_select_this_all = true;
                select_this_all = $(this).parents(".j-cart-get-i-more").find(".ect-select label").not(".label-this-all");

                select_this_all.each(function () {
                    if (!$(this).hasClass("active")) {
                        is_select_this_all = false;
                        return false;
                    }
                })
                if (is_select_this_all) {
                    $(this).parents(".j-cart-get-i-more").find(".label-this-all").addClass("active");
                } else {
                    $(this).parents(".j-cart-get-i-more").find(".label-this-all").removeClass("active");
                }
            }

            var select_all = $(".j-select-all").find(".ect-select label");
            select_all.each(function () {
                if (!$(this).hasClass("active")) {
                    is_select_all = false;
                    return false;
                }
            });
            if (is_select_all) {
                $(".label-all").addClass("active");
            } else {
                $(".label-all").removeClass("active");
            }
        }
        /*hu*/
        var rec_id = '';
        $(".rec-active").each(function () {
            var goods_id = $(this).attr("goods-id");
            if ($(this).hasClass("active")) {

                if ($(this).attr("rec-id") != undefined && $(this).attr("rec-id") > 0) {
                    rec_id += $(this).attr("rec-id") + ',';
                    $("#" + goods_id + "").addClass("active");
                }
            } else {
                $("#" + goods_id + "").removeClass("active");
            }
        });
        $.ajax({
            type: "POST",
            url: ROOT_URL + "index.php?m=cart&a=cart_label_count",
            data: {
                id: rec_id
            },
            dataType: "json",
            success: function (data) {
                $(".cart-number-show").text('(' + data.cart_number + ')');
                $(".cart-price-show").html(data.content);
                $(".cart-price-hidden").text(data.content);
            }
        });
        /*hu*/
    });
    /*店铺信息商品滚动*/
    var swiper = new Swiper('.j-f-n-c-prolist', {
        scrollbarHide: true,
        slidesPerView: 'auto',
        centeredSlides: false,
        grabCursor: true
    });
</script>
</body>

</html>