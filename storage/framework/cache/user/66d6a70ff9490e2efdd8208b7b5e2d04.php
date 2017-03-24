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
		<h3 class="box-flex">退换货列表</h3>
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

	<div class="goods-info user-order of-hidden ect-tab j-ect-tab ts-3" style="padding-top: 0rem;">
		<nav class=" j-tab-title tab-title b-color-f of-hidden" style="position: static;">
			<ul class="dis-box">
				<li class="box-flex active">售后申请</li>
				<li class="box-flex">进度查询</li>
			</ul>
		</nav>
		<div id="j-tab-con" class="tab-con">
			<div class="swiper-wrapper">
				<section class="swiper-slide order_info">
					<script id="j-order" type="text/html">
					<%if order_list != null%>
					<%each order_list as order%>
					<section class="flow-checkout-pro m-top08">
						<div class="padding-all m-top1px b-color-f n-reture-num">
							<a href="javascript:;">
							<h4 class="t-remark2"><label class="t-remark">订单号：</label><%order.order_sn%></h4>
							<p class="t-remark3 m-top04"><%order.add_time%></p>
							</a>
						</div>
						<!--order-list s-->
						<%each order.goods_list as goods%>
						<section class="n-return-list-box b-color-f">
							<ul class="dis-box">
								<li  class="reture-left-img">
									<a href="javascript:;>"<!--<%order.order_url%>-->
										<div class="img-box"><img class="product-list-img" src="<%goods.goods_thumb%>"></div>
									</a>
								</li>
								<li class="box-flex reture-right-cont">
									<a href="javascript:;"><h4 class="onelist-hidden m-top02"><%goods.goods_name%></h4></a>
									<div class="dis-box reture-footer">
										<div class=""><span class="f-04 col-7">数量：×<%goods.goods_number%></span></div>
										<div class="box-flex">
											<a href="<%goods.apply_return_url%>" class="btn-submit1 n-return-btn fr">申请售后</a>
										</div>
									</div>
								</li>
							</ul>
						</section>
						<%/each%>
						<!--order-list e-->
					</section>
					<%/each%>
					<%else%>
					<div class="no-return-list">
						<i class="iconfont icon-biaoqingleiben"></i>
						<p>亲，此处没有内容～！</p>
					</div>
					<%/if%>
					</script>					
				</section>
				<section class="swiper-slide refound_info">
					<script id="j-refound" type="text/html">
					<%if refound_list != null%>
					<%each refound_list as refound%>
					<!--list s-->
					<section class="flow-checkout-pro m-top08">
						<div class="padding-all m-top1px b-color-f n-reture-num">
							<h4 class="t-remark2"><label class="t-remark">订单号：</label><%refound.order_sn%><!--<span class="t-jiantou-2"><i class="iconfont icon-jiantou tf-180"></i></span>--></h4>
							<p class="t-remark3 m-top04"><%refound.apply_time%></p>
						</div>
						<!--order-list s-->
						<section class="n-return-list-box b-color-f">
							<ul class="dis-box">
								<li class="reture-left-img">
									<div class="img-box"><img class="product-list-img" src="<%refound.goods_thumb%>"></div>
								</li>
								<li class="box-flex reture-right-cont">
									<h4 class="onelist-hidden"><%refound.goods_name%></h4>
									<div class="dis-box reture-footer">
										<div class=""><span class="f-04 col-7">数量：× <%refound.return_number%></span></div>
										<div class="box-flex">
											<div class="fr">
											<%if refound.refound_cancel_url%>
											<a class="btn-default n-return-btn" href="<%refound.refound_cancel_url%>" onclick="if(!confirm('确定取消申请？'))return false;">取消</a>
											<%/if%>
											<a class="btn-submit1 n-return-btn" href="<%refound.refound_detail_url%>">查看详情</a>
											</div>
										</div>
									</div>
								</li>
							</ul>
						</section>
						<!--order-list e-->
					</section>
					<!--list e-->
					<%/each%>
					<%else%>
					<div class="no-return-list">
						<i class="iconfont icon-biaoqingleiben"></i>
						<p>亲，此处没有内容～！</p>
					</div>
					<%/if%>
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
		onSlideChangeStart: function() {
			$(".j-tab-title .active").removeClass('active')
			$(".j-tab-title li").eq(tabsSwiper.activeIndex).addClass('active')
		}
	})
	$(".j-tab-title li").on('touchstart mousedown', function(e) {
		e.preventDefault()
		$(".j-tab-title .active").removeClass('active')
		$(this).addClass('active')
		tabsSwiper.slideTo($(this).index())
	})
	$(".j-tab-title li").click(function(e) {
		e.preventDefault()
	})
	//订单列表
	var infinite1 = $('.order_info').infinite({url: "<?php echo url('user/refound/index');?>", template: 'j-order', params: 'type=0&order_id='+<?php echo $order_id; ?>});
	var infinite2 = $('.refound_info').infinite({url: "<?php echo url('user/refound/index');?>", template: 'j-refound', params: 'type=1&order_id='+<?php echo $order_id; ?>});
</script>
</body>
</html>