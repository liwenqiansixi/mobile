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
<body>
    <div class="con mb-7">
        <header class="dis-box header-menu n-header-menu b-color color-whie">
            <a class="" href="javascript:history.go(-1);"><i class="iconfont icon-back"></i></a>
            <h3 class="box-flex">退换货详情</h3>
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

        <section class="flow-checkout-pro j-flow-checkout-pro" >
            <header class="b-color-f padding-all" style="border-bottom:1px solid #f6f6f9">商品列表</header>
            <div class="product-list-small b-color-f dis-box">
                <ul class="box-flex flow-checkout-bigpic" style="display:block;">
                    <li>
                        <div class="product-div">
							<?php if($return_detail['extension_code'] == 'package_buy') { ?>
                            <a class="product-div-link" href="<?php echo url('package/index/index');?>"></a>
							<?php } else { ?>
							<a class="product-div-link" href="<?php echo url('goods/index/index', array('id'=>$return_detail['goods_id']));?>"></a>
							<?php } ?>
                            <img class="product-list-img" src="<?php echo ($return_detail['goods_thumb']); ?>">
                            <div class="product-text">
                                <h4><?php echo ($return_detail['goods_name']); ?></h4>
                                <p>
                                    <span class="p-price t-first "><?php echo ($return_detail['goods_price']); ?><small class="fr t-remark">x<?php echo ($return_detail['return_number']); ?></small></span>
                                </p>
                                <p class="dis-box p-t-remark">
                                    <?php echo ($return_detail['attr_val']); ?>
                                </p>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </section>
        <header class="b-color-f padding-all m-top10">详细信息</header>
        <ul class="user-refound-box b-color-f m-top04">
        	<li class="dis-box">
        		<div>退换货单号:</div>
        		<div class="box-flex"><p class="t-first text-right"><?php echo ($return_detail['return_sn']); ?></p></div>
        	</li>
        	<li class="dis-box">
        		<div>申请时间:</div>
        		<div class="box-flex"><p class="col-3 text-right"><?php echo ($return_detail['apply_time']); ?></p></div>
        	</li>
        	<li class="dis-box">
        		<div>服务类型:</div>
        		<div class="box-flex"><p class="col-3 text-right">
        			<?php if($return_detail['return_type'] == 0) { ?>
                        维修
                        <?php } elseif ($return_detail['return_type'] == 1) { ?>
						退货
                        <?php } elseif ($return_detail['return_type'] == 2) { ?>
						换货
                        <?php } ?></p></div>
        	</li>
        	<li class="dis-box">
        		<div>订单状态:</div>
        		<div class="box-flex"><p class="col-3 text-right"><?php echo ($return_detail['return_status']); ?></p></div>
        	</li>
        	<li class="dis-box">
        		<div>退货原因:</div>
        		<div class="box-flex"><p class="col-3 text-right"> <?php echo ($return_detail['return_cause']); ?></p></div>
        	</li>
        	<li class="dis-box">
        		<div>退款金额:</div>
        		<div class="box-flex"><p class="text-right t-first"> <?php echo ($return_detail['refound_status']); ?></p></div>
        	</li>
         </ul>
		<?php if($return_detail['img_list']) { ?>
         <ul class="user-refound-box b-color-f m-top04">
        		<li class="dis-box">
        		<div>凭证图片:</div>
        		<div class="box-flex"><p class="col-3 text-right"> </p></div>
        	</li>
        	<div class="goods-evaluation-page b-color-f tab-con ">
				<div class="g-e-p-pic product-one-list of-hidden scrollbar-none j-g-e-p-pic swiper-container-horizontal">
					<div class="swiper-wrapper ">
						<?php $n=1;if(is_array($return_detail['img_list'])) foreach($return_detail['img_list'] as $img_url) { ?>
						<li class="swiper-slide swiper-slide-active">
              <div class="refound-img-list">
							<img class="product-list-img" src="<?php echo $img_url; ?>">
            </div>
						</li>

						<?php $n++;}unset($n); ?>
					</div>
				</div>
			</div>
		 </ul>
		<?php } ?>
         <ul class="user-refound-box b-color-f m-top04">
        	<li class="dis-box">
        		<div>联系人:</div>
        		<div class="box-flex"><p class="col-3 text-right"> <?php echo ($return_detail['addressee']); ?></p></div>
        	</li>
        	<li class="dis-box">
        		<div>联系电话:</div>
        		<div class="box-flex"><p class="col-3 text-right">  <?php echo ($return_detail['phone']); ?></p></div>
        	</li>
        	<li class="dis-box">
        		<div>联系地址:</div>
        		<div class="box-flex"><p class="col-3 text-right"> <?php echo ($return_detail['address_detail']); ?></p></div>
        	</li>
        </ul>
		<?php if($return_detail['agree_apply']) { ?>
		<header class="b-color-f padding-all m-top10" >用户寄出</header>
		<?php if($return_detail['back_shipp_shipping']) { ?>
		<ul class="user-refound-box b-color-f m-top04">
        	<li class="dis-box">
        		<div>快递公司:</div>
        		<div class="box-flex"><p class="col-3 text-right"> <?php echo ($return_detail['back_shipp_shipping']); ?></p></div>
        	</li>
        	<li class="dis-box">
        		<div>快递单号:</div>
        		<div class="box-flex"><p class="col-3 text-right">  <?php echo ($return_detail['back_invoice_no']); ?></p></div>
        	</li>
        	 <?php if($return_detail['back_invoice_no_btn']) { ?>
        	<li class="dis-box">
        		<div class="box-flex"><p class="col-3 text-right n-refound-btn"> <?php echo ($return_detail['back_invoice_no_btn']); ?></p></div>
        	</li>
        	<?php } ?>
        </ul>
        <?php } ?>
		<header class="b-color-f padding-all m-top10" >商家发货</header>
		<?php if($return_detail['out_shipp_shipping']) { ?>
		<ul class="user-refound-box b-color-f m-top04">
			<li class="dis-box">
				<div>快递公司:</div>
				<div class="box-flex"><p class="col-3 text-right"> <?php echo ($return_detail['out_shipp_shipping']); ?></p></div>
			</li>
			<li class="dis-box">
				<div>快递单号:</div>
				<div class="box-flex"><p class="col-3 text-right">  <?php echo ($return_detail['out_invoice_no']); ?></p></div>
			</li>
			<?php if($return_detail['out_invoice_no_btn']) { ?>
			<li class="dis-box">
				<div class="box-flex"><p class="col-3 text-right n-refound-btn"> <?php echo ($return_detail['out_invoice_no_btn']); ?></p></div>
			</li>
			<?php } ?>
		</ul>
		<?php } ?>
        <?php } ?>
    </div>
    <script>
    	/*店铺信息商品滚动*/
			var swiper = new Swiper('.j-g-e-p-pic', {
				scrollbarHide: true,
				slidesPerView: 'auto',
				centeredSlides: false,
				grabCursor: true
			});
    </script>
</body>
</html>