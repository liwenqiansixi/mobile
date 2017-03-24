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
    		    <header class="dis-box header-menu n-header-menu b-color color-whie">
        <a class="" href="javascript:history.go(-1);"><i class="iconfont icon-back"></i></a>
        <h3 class="box-flex">订单完成</h3>
        <a><i class="iconfont icon-13caidan j-nav-box"></i></a>
    </header>
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

        <div class="flow-done">
            <div class="flow-done-con">
                <?php if($order['pay_code'] == 'balance') { ?>
                <i class="iconfont icon-hookring2"></i>
                <p class="flow-done-title">余额支付成功</p>
                <?php } else { ?>
                <i class="iconfont icon-qian"></i>
                <p class="flow-done-title">付款金额</p>
                <?php if($child_order > 1) { ?>
                <p class="flow-done-price"><?php echo ($total['amount_formated']); ?></p>
                <?php } else { ?>
                <p class="flow-done-price"><?php echo ($order['order_amount']); ?></p>
                <?php } ?>
                <?php } ?>
            </div>
            <?php if($child_order > 1) { ?>
            <div class="flow-done-all">
                <?php $n=1;if(is_array($child_order_info)) foreach($child_order_info as $child) { ?>
                <div class="padding-all b-color-f flow-done-id">
                    <section class="dis-box">
                        <label class="t-remark g-t-temark">订单号：</label>
                        <span class="box-flex t-goods1 text-right"><?php echo ($child['order_sn']); ?></span>
                    </section>
                </div>
                <?php $n++;}unset($n); ?>
            </div>
            <?php } else { ?>
            <div class="flow-done-all">
            <div class="padding-all b-color-f flow-done-id">
                <section class="dis-box">
                    <label class="t-remark g-t-temark">订单号：</label>
                    <span class="box-flex t-goods1 text-right"><?php echo ($order['order_sn']); ?></span>
                </section>
                <?php if($store) { ?>
                <section class="dis-box">
                    <label class="t-remark g-t-temark">门店信息：</label>
                    <span class="box-flex t-goods1 text-right"><?php echo ($store['stores_name']); ?>[<?php echo ($store['province_name']); ?> <?php echo ($store['city_name']); ?> <?php echo ($store['district_name']); ?>] <?php echo ($store['stores_address']); ?></span>
                </section>
                <?php } ?>
            </div>
            </div>
            <?php } ?>
            <div class="padding-all ect-button-more dis-box">
                <!-- <?php if($pay_online && $order['pay_code'] != 'balance') { ?> -->
                <!-- 如果是线上支付则显示支付按钮 -->
                <?php echo $pay_online; ?>
                <!-- <?php } ?> -->
            </div>
            <div class="flow-done-other">
                <?php if($child_order > 1) { ?>
                <a href="<?php echo url('user/index/index');?>">会员中心</a>
                <?php } else { ?>
                <a href="<?php echo url('user/order/detail', array('order_id'=>$order['order_id']));?>">查看订单</a>
                <?php } ?>
            </div>
        </div>
    </div>
</body>
</html>