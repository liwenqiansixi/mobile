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
        <h3 class="box-flex">订单详情</h3>
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

	<div class="flow-checkout">

		<?php if($offline_store) { ?>
			<!--门店自提码  -->
			<section class="flow-checkout-adr padding-all">
				<a class="product-div-link" href="<?php echo url('offline_store/index/OfflineStoreDetail', array('id'=>$store_id));?>"></a>
				<div class="flow-have-adr">
					<p class="f-h-adr-title ">自提码<em class="f-05 col-7">（<?php echo ($order['pick_code']); ?> <?php echo ($offline_store['stores_name']); ?>）</em></p>
				</div>
				<span class="t-jiantou"><i class="iconfont icon-jiantou tf-180"></i></span>
			</section>
		<?php } else { ?>
			<section class="flow-checkout-adr padding-all">
				<div class="flow-have-adr">
					<p class="f-h-adr-title">
						<label><?php echo ($order['consignee']); ?> <?php echo ($order['mobile']); ?></label>
					</p>
					<p class="f-h-adr-con t-remark m-top04"><?php echo ($order['detail_address']); ?></p>
				</div>
			</section>
		<?php } ?>
		<section class="m-top10">

			<section class="flow-checkout-pro j-flow-checkout-pro">
				<header class="b-color-f padding-all"><?php echo ($order['shop_name']); ?></header>
				<div class="f-c-p-orderid padding-all m-top1px b-color-f">

					<h4 class="t-remark2">
						<label class="t-remark">订单号：</label><?php echo ($order['order_sn']); ?>
                        <!--拼团标识-->
                        <?php if($team) { ?>
                            <?php if($order['failure'] > 0) { ?>
                            <em class="em-promotion b-tag">拼团订单失效</em>
                            <?php } ?>
                        <?php } ?>
                        <!--拼团标识 end-->
					</h4>
					<p class="t-remark3 m-top04"><?php echo ($order['formated_add_time']); ?></p>
				</div>
				<div class="product-list-small b-color-f dis-box">
                    <?php if($goods_count > 1) { ?>
					<ul class="flow-checkout-smallpic box-flex">
						<?php $n=1;if(is_array($goods_list)) foreach($goods_list as $key=>$val) { ?>
                        <?php if($key < 3 && $val['extension_code'] != 'package_buy') { ?>
						<li><img class="product-list-img" src="<?php echo ($val['goods_thumb']); ?>" /></li>
                        <?php } ?>
						<?php $n++;}unset($n); ?>
					</ul>
					<ul class="box-flex flow-checkout-bigpic">
						<?php $n=1;if(is_array($goods_list)) foreach($goods_list as $val) { ?>
						<?php if($val['extension_code'] != 'package_buy') { ?>
						<li>
							<div class="product-div">
								<a class="product-div-link"
									href="<?php echo url('goods/index/index',array('id'=>$val['goods_id']));?>"></a>
								<img class="product-list-img" src="<?php echo ($val['goods_thumb']); ?>" />
								<div class="product-text">
									<h4><?php echo ($val['goods_name']); ?></h4>
									<p>
										<span class="p-price t-first "><?php echo ($val['goods_price']); ?><small
											class="fr t-remark">x<?php echo ($val['goods_number']); ?></small></span>
									</p>
									<p class="dis-box p-t-remark"><?php echo nl2br($val['goods_attr']);?></p>
                                                                        </div>
                                                                 <div class="ka-mo">
                                                                  <?php if(!empty($val['virtual_info'])) { ?>
                                                                    <p class=" p-t-remark">卡号：<?php echo ($val['virtual_info']['card_sn']); ?><i class="iconfont icon-guanbi2 ma-icon fr"></i></p>
                                                                    <p class=" p-t-remark">密码：<?php echo ($val['virtual_info']['card_password']); ?></p>
                                                                    <p class=" p-t-remark">截止日期：<?php echo ($val['virtual_info']['end_date']); ?></p>
                                                                    <?php } ?>
                                                                 </div>
                                                                <?php if(!empty($val['virtual_info'])) { ?>
                                                                 <span class="ka-order-btn click-show-attr add-to-cart">查看卡密</span>
                                                                <?php } ?>

							</div>
						</li>
						<?php } ?>
                        <?php $n++;}unset($n); ?>
					</ul>
					<span class="t-jiantou"><span class="f-c-a-count">共<?php echo $goods_count; ?> 件</span><i class="iconfont icon-jiantou tf-180"></i></span>
                    <?php } else { ?>
                    <ul class="box-flex flow-checkout-bigpic" style="display:block;">
                        <?php $n=1;if(is_array($goods_list)) foreach($goods_list as $val) { ?>
						<?php if($val['extension_code'] != 'package_buy') { ?>
						<li>
                            <div class="product-div">
                                <a   href="<?php echo url('goods/index/index',array('id'=>$val['goods_id']));?>">
                                <img class="product-list-img" src="<?php echo ($val['goods_thumb']); ?>" /></a>
                                <div class="product-text">
                                    <h4><?php echo ($val['goods_name']); ?></h4>
                                    <p>
										<span class="p-price t-first "><?php echo ($val['goods_price']); ?><small class="fr t-remark">x<?php echo ($val['goods_number']); ?></small></span>
                                    </p>
                                    <p class="dis-box p-t-remark"><?php echo nl2br($val['goods_attr']);?></p>
                                         <div class="ka-mo">
                                            <?php if(!empty($val['virtual_info'])) { ?>
                                                    <p class=" p-t-remark">卡号：<?php echo ($val['virtual_info']['card_sn']); ?><i class="iconfont icon-guanbi2 ma-icon fr"></i></p>
                                                    <p class=" p-t-remark">密码：<?php echo ($val['virtual_info']['card_password']); ?></p>
                                                    <p class=" p-t-remark">截止日期：<?php echo ($val['virtual_info']['end_date']); ?></p>
                                                    <?php } ?>
                                         </div>
                                           <?php if(!empty($val['virtual_info'])) { ?>
                                         <span class="ka-order-btn click-show-attr add-to-cart">查看卡密</span>
                                          <?php } ?>

                                </div>
                            </div>
                        </li>
						<?php } ?>
                        <?php $n++;}unset($n); ?>
                    </ul>
                    <?php } ?>
				</div>
			</section>
			<?php if($package_goods_count > 0) { ?>
			<?php $n=1;if(is_array($goods_list)) foreach($goods_list as $key=>$goods) { ?>
			<?php if($goods['extension_code'] == 'package_buy') { ?>
			<!--超级礼包-->
			<section class="m-top10">
				<section class="flow-checkout-pro j-flow-checkout-pro">
					<div class="product-list-small m-top1px b-color-f dis-box">
						<ul class="flow-checkout-smallpic box-flex" style="display:block;">
							<li class="p-r"><img class="product-list-img" src="<?php echo ($goods['goods_thumb']); ?>" /><div class="gift-tag">礼包</div></li>
							<div class="gift-cont">
								<h5 class="onelist-hidden">[超级礼包]<?php echo ($goods['goods_name']); ?></label></h5>
								<p class="f-05 color-red">套餐价:<?php echo ($goods['format_package_list_total']); ?></p>
								<p class="f-03">(已优惠 <?php echo ($goods['format_package_list_saving']); ?>)</p>
							</div>
						</ul>
						<span class="t-jiantou-gift">共<?php echo count($goods['package_goods_list']);?>件<i class="iconfont icon-jiantou tf-180 ts-5"></i></span>
					</div>
				</section>
			</section>
			<section class="gift-list-box">
				<section class="flow-checkout-pro j-flow-checkout-pro active">
					<div class="product-list-small m-top1px b-color-f dis-box">
						<ul class="box-flex flow-checkout-bigpic">
							<?php $n=1;if(is_array($goods['package_goods_list'])) foreach($goods['package_goods_list'] as $package) { ?>
							<li>
								<div class="product-div">
									<a class="product-div-link" href="<?php echo ($package['url']); ?>"></a>
									<img class="product-list-img" src="<?php echo ($package['goods_thumb']); ?>" />
									<div class="product-text">
										<h4><?php echo ($package['goods_name']); ?></h4>
										<p><span class="p-price t-first "><?php echo price_format($package['rank_price']);?><small class="fr t-remark">x<?php echo ($package['goods_number']); ?></small></span></p>
										<p class="dis-box p-t-remark"><?php echo ($package['goods_attr']); ?></p>

									</div>
								</div>
							</li>
							<?php $n++;}unset($n); ?>
						</ul>
					</div>
				</section>
			</section>
			<!--超级礼包-->
			<?php } ?>
			<?php $n++;}unset($n); ?>
			<?php } ?>

			<section class="flow-checkout-select m-top10 b-color-f">
				<ul>
                    <?php if($order['shipping_id']) { ?>
					<li>
						<section class="dis-box ">
							<label class="t-remark g-t-temark">配送方式</label>
							<div class="box-flex t-goods1 text-right onelist-hidden">
                                <?php if($offline_store) { ?>
                                <span>门店自提</span>
                                <?php } else { ?>
								<span><?php echo ($order['shipping_name']); ?></span>
                                <?php if($order['shipping_fee'] > 0) { ?><em class="t-first"><?php echo ($order['formated_shipping_fee']); ?></em><?php } ?>
                                <?php } ?>
							</div>
						</section>
					</li>
                    <?php } ?>
                    <?php if($order['point']) { ?>
					<li class="goods-site-li dis-box">
                        <label class="t-remark g-t-temark">自提点</label>
						<div class="box-flex t-goods1 text-right onelist-hidden">
                            <span><?php echo ($order['point']['name']); ?></span>
                        </div>
					</li>
                    <li class="goods-site-li dis-box">
                        <label class="t-remark g-t-temark">提货时间</label>
                        <div class="box-flex t-goods1 text-right onelist-hidden">
                            <span><?php echo ($order['point']['pickDate']); ?></span>
                        </div>
                    </li>
                    <?php } ?>
                    <?php if($order['pack_id']) { ?>
                    <li class="dis-box">
                        <label class="t-remark g-t-temark">商品包装</label>
                        <div class="box-flex t-goods1 text-right onelist-hidden">
                            <span><?php echo ($order['pack_name']); ?></span>
                            <em class="t-first"><?php echo ($order['formated_pack_fee']); ?></em>
                        </div>
                    </li>
                    <?php } ?>
                    <?php if($order['postscript']) { ?>
					<li><label class="t-remark g-t-temark">买家留言</label>
						<p class="m-top04" style="font-size: 1.3rem;"><?php echo ($order['postscript']); ?></p>
                    </li>
                    <?php } ?>
				</ul>
			</section>
		</section>

		<section class="m-top10">
			<ul>
				<li class="dis-box padding-all m-top1px b-color-f j-show-div" id="payment">
					<label class="t-remark g-t-temark">支付方式</label>
					<div class="box-flex t-goods1 text-right onelist-hidden">
						<span><?php echo ($order['pay_name']); ?></span> <?php if($order['pay_fee'] > 0) { ?><em class="t-first"><?php echo ($order['formated_pay_fee']); ?>手续费</em><?php } ?>
					</div>
					<?php if($payment_list) { ?>
					<span class="t-jiantou"><i class="iconfont icon-jiantou tf-180"></i></span>
					<!--支付方式star-->
					<div class="show-goods-dist ts-3 b-color-1 j-show-goods-text j-filter-show-div">
						<section class="goods-show-title of-hidden padding-all b-color-f">
							<h3 class="fl g-c-title-h3">切换支付方式</h3>
							<i class="iconfont icon-guanbi1 show-div-guanbi fr"></i>
						</section>
						<section class="s-g-list-con swiper-scroll">
							<div class="swiper-wrapper">
								<div class="swiper-slide select-two">
									<ul class="j-get-one padding-all">
										<?php $n=1;if(is_array($payment_list)) foreach($payment_list as $payment) { ?>
										<li class="ect-select goods-site"  date-payid="<?php echo ($payment['pay_id']); ?>">
											<label class="ts-1 <?php if($order['pay_id'] == $payment['pay_id']) { ?>active<?php } ?>">
												<dd>
													<span><?php echo ($payment['pay_name']); if($payment['pay_fee']) { ?><em class="t-remark">[手续费]</em><?php } ?></span>
													<?php if($payment['pay_fee']) { ?><em class="t-first"><?php echo ($payment['format_pay_fee']); ?></em><?php } ?>
												</dd>
												<i class="fr iconfont icon-gou ts-1"></i>
											</label>
										</li>
										<?php $n++;}unset($n); ?>
									</ul>
								</div>
							</div>
						</section>
					</div>
					<?php } ?>
				</li>

                <?php if($order['inv_type']) { ?>
				<li class=" padding-all m-top1px b-color-f ">
						<label class="t-remark g-t-temark">发票信息</label>
						<div class="box-flex t-goods1 text-right onelist-hidden">
							<span><?php echo ($order['inv_payee']); echo ($order['inv_content']); ?></span> <em class="t-first"><?php echo ($order['formated_tax']); ?></em>
						</div>
				</li>
                <?php } ?>
                <?php if($order['bonus_id']) { ?>
				<li class="dis-box padding-all m-top1px b-color-f"><label
					class="t-remark g-t-temark">红包</label>
					<div class="box-flex t-goods1 text-right onelist-hidden">
						<span>红包金额</span> <em class="t-first"><?php echo ($order['formated_bonus']); ?></em>
					</div>
                </li>
                <?php } ?>
				<?php if($order['coupons']) { ?>
				<?php $n=1;if(is_array($order['coupons'])) foreach($order['coupons'] as $val) { ?>
				<li class="dis-box padding-all m-top1px b-color-f"><label
						class="t-remark g-t-temark">优惠券</label>
					<div class="box-flex t-goods1 text-right onelist-hidden">
						<span>优惠券金额</span> <em class="t-first">-<?php echo ($val['cou_money']); ?></em>
					</div>
				</li>
				<?php $n++;}unset($n); ?>
				<?php } ?>
			</ul>
		</section>
		<section class="m-top10">
			<section class="flow-checkout-tprice">
				<header class="b-color-f padding-all">
					商品金额<span class="t-first fr"><?php echo ($order['formated_goods_amount']); ?></span>
				</header>
				<ul class="m-top1px b-color-f">
                    <?php if($order['discount'] > 0) { ?>
					<li class="padding-all of-hidden">
                        <label class="t-remark g-t-temark fl">商品优惠</label>
                        <span class="t-first fr">-<?php echo ($order['formated_discount']); ?></span>
                    </li>
                    <?php } ?>
                    <?php if($order['shipping_fee'] > 0 && empty($order['point'])) { ?>
					<li class="padding-all of-hidden">
                        <label class="t-remark g-t-temark fl">运费</label>
                        <span class="t-first fr">+<?php echo ($order['formated_shipping_fee']); ?></span>
					</li>
                    <?php } ?>
                    <?php if($order['integral']) { ?>
					<li class="padding-all of-hidden">
                        <label class="t-remark g-t-temark fl">积分</label>
                        <span class="fr t-first">-<?php echo ($order['formated_integral_money']); ?></span>
                    </li>
                    <?php } ?>
					<?php if($order['bonus']>0) { ?>
					<li class="padding-all of-hidden">
						<label class="t-remark g-t-temark fl">使用红包</label>
						<span class="fr t-first">-<?php echo ($order['formated_bonus']); ?></span>
					</li>
					<?php } ?>
					<?php if($order['coupons']>0) { ?>
					<?php $n=1;if(is_array($order['coupons'])) foreach($order['coupons'] as $val) { ?>
					<li class="padding-all of-hidden">
						<label class="t-remark g-t-temark fl">使用优惠券</label>
						<span class="fr t-first">-<?php echo ($val['cou_money']); ?></span>
					</li>
					<?php $n++;}unset($n); ?>
					<?php } ?>
                                       <li class="padding-all of-hidden">
                                           <label class="t-remark g-t-temark fl"><?php echo ($order['msg']); ?></label>
                                           <span class="fr t-first ect-button-more"><?php echo ($order['handler']); ?></span>
                                       </li>
				</ul>

			</section>
		</section>

	</div>

</div>
<?php if($payment_list) { ?>
<div class="mask-filter-div"></div>
<?php } ?>

<!--悬浮btn star-->
<div class="filter-btn f-checkout-filter-btn  dis-box">
	<p class="u-o-checkout-price t-remark text-left box-flex m-top04">
            应付总额：<span class="t-first"><?php echo ($order['formated_order_amount']); ?></span>
	</p>
    <?php if($team) { ?>
        <?php if($order['failure'] > 0) { ?>
        <div class="n-right-width" >
            拼团订单失效，请重新参团
            </div>
        <?php } else { ?>
            <?php if($order['order_amount'] > 0 && empty($order['hidden_pay_button'])) { ?>
                <div class="n-right-width" >
                <?php echo ($order['pay_online']); ?>
                </div>
            <?php } ?>
        <?php } ?>
    <?php } else { ?>
         <?php if($order['order_amount'] > 0 && empty($order['hidden_pay_button'])) { ?>
            <div class="n-right-width" >
            <?php echo ($order['pay_online']); ?>
            </div>
        <?php } ?>
    <?php } ?>

	<?php if($order['invoice_no']) { ?>
	<?php echo ($order['invoice_no']); ?>
	<?php } ?>
</div>
<!--悬浮btn end-->
<div class="mask-filter-div-box"></div>
<script>


	var scorll_swiper = new Swiper('.swiper-scroll', {
		direction : 'vertical',
		slidesPerView : 'auto',
		mousewheelControl : true,
		freeMode : true
	});
</script>
<script>
	$(function () {

		$(".ect-select").click(function(){
			var url = "<?php echo url('changepayment');?>";
			var args = new Array(2);
			args['order_id'] = <?php echo ($order['order_id']); ?>;
			args['pay_id'] = $(this).attr('date-payid');
			StandardPost(url, args);
		})
		function StandardPost(url, args) {
			var form = $("<form method='post'></form>");
			form.attr({"action": url});
			for (arg in args) {
				var input = $("<input type='hidden'>");
				input.attr({"name": arg});
				input.val(args[arg]);
				form.append(input);
			}
			form.submit();
		}
	})
</script>
</body>

</html>