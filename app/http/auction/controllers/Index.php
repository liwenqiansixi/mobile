<?php
namespace app\http\auction\controllers;

use app\http\base\controllers\Frontend;

class Index extends Frontend
{
    public $area_id = 0;
    public $region_id = 0;

    public function __construct(){
        parent::__construct();
        L(require(LANG_PATH  . C('shop.lang') . '/user.php'));
        L(require(LANG_PATH  . C('shop.lang') . '/flow.php'));
        $area_info = get_area_info($this->province_id);
        $this->area_id = $area_info['region_id'];

        $where = "regionId = '$province_id'";
        $date = array('parent_id');
        $this->region_id = get_table_date('region_warehouse', $where, $date, 2);

        if(isset($_COOKIE['region_id']) && !empty($_COOKIE['region_id'])){
            $this->region_id = $_COOKIE['region_id'];
        }
    }

    public function actionIndex()
    {
        $size = intval(C('page_size')) > 0 ? intval(C('page_size')) : 10;
        $page = isset($_REQUEST['page']) && intval($_REQUEST['page']) > 0 ? intval($_REQUEST['page']) : 1;
        if(IS_AJAX){
            $default_sort_order_method = C('shop.sort_order_method') == 0 ? 'DESC' : 'ASC';
            $default_sort_order_type = C('shop.sort_order_type') == 0 ? 'act_id' : (C('shop.sort_order_type') == 1 ? 'start_time' : 'end_time');
            $sort = I('sort');
            $order = I('order');
            $sort = in_array($sort, array('act_id', 'start_time', 'end_time')) ? $sort : $default_sort_order_type;
            $order = in_array($order, array('ASC', 'DESC')) ? $order : $default_sort_order_method;
            $keyword = I('request.keyword');
            /* 取得拍卖活动总数 */
            $count = auction_count($keyword);
            if ($count > 0) {
                $page_count = ceil($count / $size);
                $page = $page > $page_count ? $page_count : $page;
            }
            if ($count > 0) {
                /* 取得当前页的拍卖活动 */
                $auction_list = auction_list($keyword, $sort, $order, $size, $page);
            }
            die(json_encode(array('list' => $auction_list, 'totalPage' => $page_count)));
        }

//        $this->assign('page_title', '拍卖活动'); // 页面标题
        $this->assign('page_title', L('auction_action')); // 页面标题
        $this->display();
    }

    public function actionDetail()
    {
        /* 取得参数：拍卖活动id */
        $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
        if ($id <= 0) {
            ecs_header("Location: ./\n");
            exit;
        }

        /* 取得拍卖活动信息 */
        $auction = auction_info($id);
        if (empty($auction)) {
            ecs_header("Location: ./\n");
            exit;
        }

        if ($_SESSION ['user_id']) {
            $where['user_id'] = $_SESSION ['user_id'];
            $where['goods_id'] = $auction['goods_id'];
            $rs = $this->db->table('collect_goods')->where($where)->count();
            if ($rs > 0) {
                $this->assign('goods_collect', 1);
            }
        }

        $auction['is_winner'] = 0;
        if ($auction['last_bid'] && $auction['status_no'] == FINISHED && $auction['last_bid']['bid_user'] == $_SESSION['user_id'] && $auction['order_count'] == 0) {
            $auction['is_winner'] = 1;
        }

        //取货品信息
        if ($auction['product_id'] > 0) {
            $goods_specifications = get_specifications_list($auction['goods_id']);

            $good_products = get_good_products($auction['goods_id'], 'AND product_id = ' . $auction['product_id']);

            $_good_products = explode('|', $good_products[0]['goods_attr']);
            $products_info = '';
            foreach ($_good_products as $value) {
                $products_info .= ' ' . $goods_specifications[$value]['attr_name'] . '：' . $goods_specifications[$value]['attr_value'];
            }
            $this->assign('products_info', $products_info);
            unset($goods_specifications, $good_products, $_good_products, $products_info);
        }

        $auction['gmt_end_time'] = local_strtotime($auction['end_time']);
        $auction['price_times'] = intval($auction['current_price_int']/$auction['amplitude']+1) ;
        $this->assign('auction', $auction);

        /* 取得拍卖商品信息 */
        $goods_id = $auction['goods_id'];
        $goods = goods_info($goods_id, 0, 0);
        if (empty($goods)) {
            ecs_header("Location: ./\n");
            exit;
        }
        $goods['url'] = build_uri('goods', array('gid' => $goods_id), $goods['goods_name']);
        $this->assign('pictures',            get_goods_gallery($goods_id));                    // 商品相册
        $this->assign('auction_goods', $goods);

        /* 出价记录 */
        $auction_log = auction_log($id);
        $this->assign('auction_log', $auction_log);
        $this->assign('auction_count', auction_log($id, 1));

        //推荐拍品
        $cat_id = I('cat_id', 0, 'intval');
        $integral_max = I('integral_max', 0);
        $integral_min = I('integral_min', 0);
        $children = get_children($cat_id);
        $hot_goods = get_exchange_recommend_goods('hot',  $children, $integral_min, $integral_max);
        $this->assign('hot_goods', $hot_goods);

        //模板赋值
        $this->assign('cfg', C('shop'));
        assign_template();

        $position = assign_ur_here(0, $goods['goods_name']);
        $this->assign('page_title', $position['title']);    // 页面标题

        assign_dynamic('auction');

        //更新商品点击次数
        $sql = 'UPDATE ' . $this->ecs->table('goods') . ' SET click_count = click_count + 1 ' .
            "WHERE goods_id = '" . $auction['goods_id'] . "'";
        $this->db->query($sql);

        $this->assign('now_time', gmtime());           // 当前系统时间
        $this->display();
    }

    public function actionAuctionLog(){
        $id = I('id', 0, 'intval');
        if ($id <= 0) {
            ecs_header("Location: ./\n");
            exit;
        }
        /* 取得拍卖活动信息 */
        $auction = auction_info($id);
        if (empty($auction)) {
            ecs_header("Location: ./\n");
            exit;
        }

        $auction_log = auction_log($id);

        $this->assign('auction', $auction);
        $this->assign('auction_log', $auction_log);
        $this->assign('auction_count', auction_log($id, 1));

        $this->display();
    }

    public function actionBid()
    {
        $this->load_helper('order');

        /* 取得参数：拍卖活动id */
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id <= 0) {
            ecs_header("Location: ./\n");
            exit;
        }

        /* 取得拍卖活动信息 */
        $auction = auction_info($id);
        if (empty($auction)) {
            ecs_header("Location: ./\n");
            exit;
        }

        /* 活动是否正在进行 */
        if ($auction['status_no'] != UNDER_WAY) {
            show_message(L('au_not_under_way'), '', '', 'error');
        }

        /* 是否登录 */
        $user_id = $_SESSION['user_id'];
        if ($user_id <= 0) {
            $url = url('auction/index/detail', array('id'=>$id));
            show_message(L('au_bid_after_login'), '登录', url('user/login/index', array('back_act'=>urlencode($url))));
        }
        $user = user_info($user_id);

        /* 取得出价 */
        $price_times = I('price_times', 0, 'intval');
        $bid_price = $price_times ? round(floatval($price_times * $auction['amplitude']), 2) : 0;
        if ($bid_price <= 0) {
            show_message(L('au_bid_price_error'), '', '', 'error');
        }

        /* 如果有一口价且出价大于等于一口价，则按一口价算 */
        $is_ok = false; // 出价是否ok
        if ($auction['end_price'] > 0) {
            if ($bid_price >= $auction['end_price']) {
                $bid_price = $auction['end_price'];
                $is_ok = true;
            }
        }

        /* 出价是否有效：区分第一次和非第一次 */
        if (!$is_ok) {
            if ($auction['bid_user_count'] == 0) {
                /* 第一次要大于等于起拍价 */
                $min_price = $auction['start_price'];
            } else {
                /* 非第一次出价要大于等于最高价加上加价幅度，但不能超过一口价 */
                $min_price = $auction['last_bid']['bid_price'] + $auction['amplitude'];
                if ($auction['end_price'] > 0) {
                    $min_price = min($min_price, $auction['end_price']);
                }
            }

            if ($bid_price < $min_price) {
                show_message(sprintf(L('au_your_lowest_price'), price_format($min_price, false)), '', '', 'error');
            }
        }

        /* 检查联系两次拍卖人是否相同 */
        if ($auction['last_bid']['bid_user'] == $user_id && $bid_price != $auction['end_price']) {
            show_message(L('au_bid_repeat_user'), '', '', 'error');
        }

        /* 是否需要保证金 */
        if ($auction['deposit'] > 0) {
            /* 可用资金够吗 */
            if ($user['user_money'] < $auction['deposit']) {
                show_message(L('au_user_money_short'), '', '', 'error');
            }

            /* 如果不是第一个出价，解冻上一个用户的保证金 */
            if ($auction['bid_user_count'] > 0) {
                log_account_change($auction['last_bid']['bid_user'], $auction['deposit'], (-1) * $auction['deposit'],
                    0, 0, sprintf(L('au_unfreeze_deposit'), $auction['act_name']));
            }

            /* 冻结当前用户的保证金 */
            log_account_change($user_id, (-1) * $auction['deposit'], $auction['deposit'],
                0, 0, sprintf(L('au_freeze_deposit'), $auction['act_name']));
        }

        /* 插入出价记录 */
        $auction_log = array(
            'act_id' => $id,
            'bid_user' => $user_id,
            'bid_price' => $bid_price,
            'bid_time' => gmtime()
        );
        $this->db->autoExecute($this->ecs->table('auction_log'), $auction_log, 'INSERT');

        /* 出价是否等于一口价 */
        if ($bid_price == $auction['end_price']) {
            /* 结束拍卖活动 */
            $sql = "UPDATE " . $this->ecs->table('goods_activity') . " SET is_finished = 1 WHERE act_id = '$id' LIMIT 1";
            $this->db->query($sql);
        }

        /* 跳转到活动详情页 */
        $url = url('detail', array('id'=>$id));
        ecs_header("Location: ".$url);
        exit;
    }

    public function actionBuy()
    {
        /* 查询：取得参数：拍卖活动id */
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id <= 0) {
            ecs_header("Location: ./\n");
            exit;
        }

        /* 查询：取得拍卖活动信息 */
        $auction = auction_info($id);
        if (empty($auction)) {
            ecs_header("Location: ./\n");
            exit;
        }

        /* 查询：活动是否已结束 */
        if ($auction['status_no'] != FINISHED) {
            show_message(L('au_not_finished'), '', '', 'error');
        }

        /* 查询：有人出价吗 */
        if ($auction['bid_user_count'] <= 0) {
            show_message(L('au_no_bid'), '', '', 'error');
        }

        /* 查询：是否已经有订单 */
        if ($auction['order_count'] > 0) {
            show_message(L('au_order_placed'));
        }

        /* 查询：是否登录 */
        $user_id = $_SESSION['user_id'];
        if ($user_id <= 0) {
            show_message(L('au_buy_after_login'));
        }

        /* 查询：最后出价的是该用户吗 */
        if ($auction['last_bid']['bid_user'] != $user_id) {
            show_message(L('au_final_bid_not_you'), '', '', 'error');
        }

        /* 查询：取得商品信息 */
        $goods = goods_info($auction['goods_id']);

        /* 查询：处理规格属性 */
        $goods_attr = '';
        $goods_attr_id = '';
        if ($auction['product_id'] > 0) {
            $product_info = get_good_products($auction['goods_id'], 'AND product_id = ' . $auction['product_id']);

            $goods_attr_id = str_replace('|', ',', $product_info[0]['goods_attr']);

            $attr_list = array();
            $sql = "SELECT a.attr_name, g.attr_value " .
                "FROM " . $this->ecs->table('goods_attr') . " AS g, " .
                $this->ecs->table('attribute') . " AS a " .
                "WHERE g.attr_id = a.attr_id " .
                "AND g.goods_attr_id " . db_create_in($goods_attr_id);
            $res = $this->db->query($sql);
            foreach ($res as $row) {
                $attr_list[] = $row['attr_name'] . ': ' . $row['attr_value'];
            }
            $goods_attr = join(chr(13) . chr(10), $attr_list);
        } else {
            $auction['product_id'] = 0;
        }

        /* 清空购物车中所有拍卖商品 */
        $this->load_helper('order');
        clear_cart(CART_AUCTION_GOODS);

        /* 加入购物车 */
        $cart = array(
            'user_id' => $user_id,
            'session_id' => SESS_ID,
            'goods_id' => $auction['goods_id'],
            'goods_sn' => addslashes($goods['goods_sn']),
            'goods_name' => addslashes($goods['goods_name']),
            'market_price' => $goods['market_price'],
            'goods_price' => $auction['last_bid']['bid_price'],
            'goods_number' => 1,
            'goods_attr' => $goods_attr,
            'goods_attr_id' => $goods_attr_id,
            'warehouse_id'   => $this->region_id, //ecmoban模板堂 --zhuo 仓库
            'area_id'        => $this->area_id, //ecmoban模板堂 --zhuo 仓库地区
            'is_real'        => $goods['is_real'],
            'ru_id'          => $goods['user_id'],
            'extension_code' => addslashes($goods['extension_code']),
            'parent_id' => 0,
            'rec_type' => CART_AUCTION_GOODS,
            'is_gift' => 0
        );
        $this->db->autoExecute($this->ecs->table('cart'), $cart, 'INSERT');

        /* 记录购物流程类型：团购 */
        $_SESSION['flow_type'] = CART_AUCTION_GOODS;
        $_SESSION['extension_code'] = 'auction';
        $_SESSION['extension_id'] = $id;
        $_SESSION['direct_shopping'] = 2;

        /* 进入收货人页面 */
//        ecs_header("Location: ./flow.php?step=consignee\n");
        $this->redirect('flow/index/index');
        exit;
    }

}
