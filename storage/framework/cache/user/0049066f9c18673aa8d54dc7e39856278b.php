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
        <h3 class="box-flex">我的订单</h3>
        <a><i class="iconfont icon-13caidan j-nav-box"></i></a>
    </header>
    <div class="goods-info user-order of-hidden ect-tab j-ect-tab ts-3"style="padding-top:0">
        <nav class="tab-title b-color-f of-hidden" style="position:static">
            <ul class="dis-box">
                <li class="box-flex <?php if(empty($status)) { ?>active<?php } ?>"><a status="0" >全部订单</a></li>
                <li class="box-flex <?php if($status ==1) { ?>active<?php } ?>"><a status="1" >待付款</a></li>
                <li class="box-flex <?php if($status == 2) { ?>active<?php } ?>"><a status="2" >待收货</a></li>
            </ul>
        </nav>

        <div id="j-tab-con" class="tab-con">
            <div class="swiper-wrapper">
                <section class="swiper-slide store_info">
                    <script id="j-product" type="text/html">
                        <%each order_list as order%>
                        <section class="flow-checkout-pro m-top08">
                            <header class="b-color-f dis-box padding-all">
                                <span class=" box-flex">
                                    <%if order.user_name%>
                                    <%order.user_name%>
                                    <%else%>
                                    <%order.shop_name%>
                                    <%/if%>
                                </span>
                                <em class="j-goods-coupon t-first f-06"><%order.order_status%></em>
                            </header>
                            <div class="f-c-p-orderid padding-all m-top1px b-color-f">
                                <a class="product-div-link" href="<%order.order_url%>"></a>

                                <h4 class="t-remark2">
                                    <label class="t-remark">订单号：</label><%order.order_sn%>
                                    <!--拼团标识-->
                                    <%if order.team_id > 0%>
                                    <em class="em-promotion b-tag">拼团订单</em>
                                    <%/if%>
                                    <%if order.failure%>
                                    <span class="t-jiantou">订单失效</span>
                                    <%/if%>
                                    <!--拼团标识 end-->
                                     <span class="t-jiantou"></span>
                                </h4>
                                <p class="t-remark3 m-top04"><%order.order_time%></p>
                            </div>

                            <div class="padding-all user-orderlist-shop dis-box text-all-select">
                                <a class="product-div-link" href="<%order.order_url%>"></a>
                                <ul class="flow-checkout-smallpic box-flex">
                                    <%each order.order_goods as val %>
                                    <li><img class="product-list-img" src="<%val.goods_thumb%>" /></li>
                                    <%/each%>
                                </ul>
                                <span class="t-jiantou">
                                    <span class="f-c-a-count">共 <%order.order_goods_num%>款</span><i class="iconfont icon-jiantou tf-180"></i></span>
                            </div>
														<div class="padding-all f-05 user-order-money b-color-f">共<%order.order_goods_num%>款商品 合计：<em class="t-first"><%#order.total_fee%></em></div>
                            <footer class="padding-all b-color-f m-top1px of-hidden dis-box">
                                <h4 class="t-remark2 box-flex"></h4>
                                <p class="ect-button-more">
                                    <%if order.handler_return%>
                                    <a class="btn-default" href="<%order.handler_return%>">申请售后</a>
                                    <%/if%>
                                    <%if !order.handler%>
                                    <a class="btn-default" href="<%order.order_url%>">查看订单</a>
                                    <%/if%>
                                    <%if order.delete_yes == 1%>
                                    <!--<a class="btn-default">删除</a>-->
                                    <%/if%>

                                    <%if order.order_del%>
                                     <button class="btn-default del-order" data-item="<%order.order_id%>">删除</button>
                                    <%/if%>
                                    <%#order.handler%>

                                </p>
                            </footer>
                        </section>
                        <%/each%>
                        </script>
                    </section>
                </div>
            </div>
        </div>
    </div>
    <script type="text/javascript">
        /*切换*/
        var tabsSwiper = new Swiper('#j-tab-con', {
            speed: 100,
            noSwiping: true,
            autoHeight: true,
            onSlideChangeStart: function () {
                $(".j-tab-title .active").removeClass('active')
                $(".j-tab-title li").eq(tabsSwiper.activeIndex).addClass('active')
            }
        })
        $(".j-tab-title li").on('touchstart mousedown', function (e) {
            e.preventDefault()
            $(".j-tab-title .active").removeClass('active')
            $(this).addClass('active')
            tabsSwiper.slideTo($(this).index())
        })
        $(".j-tab-title li").click(function (e) {
            e.preventDefault()
        })
        //订单列表
        var infinite = $('.store_info').infinite({url: "<?php echo url('user/order/index');?>", template: 'j-product', params: 'status=<?php echo $status; ?>'});
        $(".tab-title a").click(function () {
            var status = $(this).attr('status');

            infinite.onload('status=' + status);
            $(this).parent().addClass("active").siblings().removeClass("active");
        })
       //删除订单
        $('.del-order').click(function(){
             var order_id = $(this).attr('data-item');
             var url='<?php echo url("user/order/delorder");?>';
               layer.open({
                    content: '是否删除此订单',
                    btn: ['确定', '取消'],
                    shadeClose: false,
                    yes: function() {
                      $.post(url, {order_id:order_id},
                         function(result){
                             if(result.y == 1){
                                window.location.href = "<?php echo url('user/order/index');?>";
                             }
                         }, 'json');
                     },
                    no: function() {

                         }
                });

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