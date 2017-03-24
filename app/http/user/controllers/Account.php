<?php
namespace app\http\user\controllers;

use app\http\base\controllers\Frontend;
use ectouch\Http;

class Account extends Frontend {

    public $user_id; //  用户id
    /**
     * 构造，加载文件语言包和helper文件
     */
    public function __construct(){
        parent::__construct();
        $this->user_id = $_SESSION['user_id'];
        $this->actionchecklogin();
        L(require(LANG_PATH  . C('shop.lang') . '/user.php'));
        L(require(LANG_PATH  . C('shop.lang') . '/flow.php'));

        $files = array(
            'order',
            'clips',
            'payment',
            'transaction',
        );
        $this->load_helper($files);

    }

    /**
     * 频道页
     */
    public function actionIndex()
    {
        // 当前余额
        $surplus_amount = get_user_surplus($this->user_id);

        $this->assign('surplus_amount',$surplus_amount ? $surplus_amount : 0);
        //当前冻结资金
        $frozen_money=get_user_frozen($this->user_id);
        $this->assign('frozen_money',$frozen_money ? $frozen_money : 0);
        // 红包数量
        $this->assign('record_count',my_bonus($this->user_id));
       //银行卡数量
        $drp_card= $this->db->getOne("SELECT COUNT(*) FROM {pre}user_bank WHERE user_id = '$this->user_id'");
        $this->assign(drp_card,$drp_card ? $drp_card : 0);
  
        // 积分数量
        $pay_points = $this->db->getOne("SELECT  pay_points FROM {pre}users WHERE user_id='$this->user_id'");
        $this->assign('pay_points',$pay_points ? $pay_points : 0);
        //$this->assign('page_title','资金管理');
        $this->assign('page_title',L('label_user_surplus'));
        $this->display();
    }

    /**
     * 资金明细
     */
    public function actionDetail(){

       // $page = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;

        $account_type = 'user_money';

        /* 获取记录条数 */
        $sql = "SELECT COUNT(*) FROM  {pre}account_log WHERE user_id = ".$this->user_id .
            " AND $account_type <> 0 ";
        $record_count = $this->db->getOne($sql);

        //分页函数
        $pager = get_pager('user.php', array('act' => $action), $record_count, $page);

        //获取剩余余额
        $surplus_amount = get_user_surplus($this->user_id);

        //获取余额记录
        $account_log = array();
        $sql = "SELECT * FROM {pre}account_log WHERE user_id = ".$this->user_id." AND $account_type <> 0 ORDER BY log_id DESC limit 0,10";
        $res = $this->db->getAll($sql);
        foreach($res as $row)
        {
            $row['change_time'] = local_date($GLOBALS['_CFG']['date_format'], $row['change_time']);
            $row['type'] = $row[$account_type] > 0 ? '+' : '';
            $row['short_change_desc'] = sub_str($row['change_desc'], 60);
            $temp = explode(',',$row['short_change_desc']);
            if(count($temp) == 2){
               $row['short_change_desc_part1'] = $temp[0];
               $row['short_change_desc_part2'] = $temp[1];
            }
            $row['amount'] = $row[$account_type];
            $account_log[] = $row;
        }
        //模板赋值
        $this->assign('account_log',    $account_log);
        //$this->assign('page_title','账户明细');
        $this->assign('page_title',L('account_detail'));
        $this->display();
    }
    /**
     * 用户充值
     */
    public function actionDeposit(){
        $surplus_id = isset($_GET['id']) ? intval($_GET['id']) : 2;
        $account    = get_surplus_info($surplus_id);
        $payment_list = get_online_payment_list(false);

        foreach($payment_list as  $key => $val){
            if(!file_exists(ADDONS_PATH . 'payment/'.$val['pay_code'].'.php')){
                unset($payment_list[$key]);
            }
        }
        $this->assign('payment', $payment_list);
        $this->assign('order',   $account);
        $this->assign('process_type',$surplus_id);
        //$this->assign('page_title','用户充值');
        $this->assign('page_title',L('account_user_charge'));
        $this->display();
    }
    /**
     *  会员退款申请界面  提现
     */
    public function actionAccountRaply(){

        // 获取剩余余额
        $surplus_amount = get_user_surplus($this->user_id);
        if (empty($surplus_amount)) {
            $surplus_amount = 0;
        }
        $sql = "SELECT * FROM {pre}user_bank WHERE user_id=".$this->user_id;
        $bank = $this->db->getAll($sql);
        if(!$bank){
            ecs_header("Location: ".url('user/account/addcard'));
        }
     
        
        $this->assign('bank',$bank);
        $this->assign('surplus_amount', price_format($surplus_amount, false));
        $this->assign('page_title', L('label_user_surplus'));
        $this->display();
    }
    /**
     * 申请提现页面   对会员余额申请的处理 
     */
    public function actionAccount(){
        
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        if ($amount <= 0)
        {
            show_message(L('amount_gt_zero'));
        }
        /* 变量初始化 */
        $surplus = array(
            'user_id'      => $this->user_id,
            'rec_id'       => !empty($_POST['rec_id'])      ? intval($_POST['rec_id'])       : 0,
            'process_type' => isset($_POST['surplus_type']) ? intval($_POST['surplus_type']) : 0,
            'payment_id'   => isset($_POST['payment_id'])   ? intval($_POST['payment_id'])   : 0,
            'user_note'    => isset($_POST['user_note'])    ? trim($_POST['user_note'])      : '',
            'amount'       => $amount
        );
        
        /* 退款申请的处理 */
        if ($surplus['process_type'] == 1)
        {
            /* 判断是否有足够的余额的进行退款的操作 */
            $sur_amount = get_user_surplus($this->user_id);
            if ($amount > $sur_amount)
            {
                $content = L('surplus_amount_error');
                show_message($content,L('back_page_up'), '', 'info');
            }
            if(empty($_POST['bank_number']) || empty($_POST['real_name'])){
//                $content = '请选择提现方式，';
                  $content = L('account_withdraw_deposit');
                show_message($content,L('account_submit_information'), '', 'warning');
            }
            //插入会员账目明细
            $amount = '-'.$amount;
            $surplus['payment'] = '';
            $surplus['rec_id']  = insert_user_account($surplus, $amount);

            /* 如果成功提交 */
            if ($surplus['rec_id'] > 0)
            {
                //by wang提现记录扩展信息start
                $user_account_fields=array(
                    'user_id'=>$surplus['user_id'],
                    'account_id'=>$surplus['rec_id'],
                    'bank_number'=>!empty($_POST['bank_number'])?trim($_POST['bank_number']):'',
                    'real_name'=>!empty($_POST['real_name'])?trim($_POST['real_name']):''
                );

                insert_user_account_fields($user_account_fields);

                //by wang提现记录扩展信息end

                $content = L('surplus_appl_submit');
                show_message($content, L('back_account_log'), url('log'), 'info');
            }
            else
            {
                $content = L('process_false');
                show_message($content, L('back_page_up'), '', 'info');
            }
        }
        /* 如果是会员预付款，跳转到下一步，进行线上支付的操作 */
        else
        {
            if ($surplus['payment_id'] <= 0)
            {
                show_message(L('select_payment_pls'));
            }

            //获取支付方式名称
            $payment_info = array();
            $payment_info = payment_info($surplus['payment_id']);
            $surplus['payment'] = $payment_info['pay_name'];
            if ($surplus['rec_id'] > 0)
            {
                //更新会员账目明细
                $surplus['rec_id'] = update_user_account($surplus);
            }
            else
            {
                //插入会员账目明细
                $surplus['rec_id'] = insert_user_account($surplus, $amount);
            }
            //取得支付信息，生成支付代码
            $payment = unserialize_config($payment_info['pay_config']);

            //生成伪订单号, 不足的时候补0
            $order = array();
            $order['order_sn']       = $surplus['rec_id'];
            $order['user_name']      = $_SESSION['user_name'];
            $order['surplus_amount'] = $amount;

            //计算支付手续费用
            $payment_info['pay_fee'] = pay_fee($surplus['payment_id'], $order['surplus_amount'], 0);

            //计算此次预付款需要支付的总金额
            $order['order_amount']   = $amount + $payment_info['pay_fee'];

            //记录支付log
            $order['log_id'] = insert_pay_log($surplus['rec_id'], $order['order_amount'], $type=PAY_SURPLUS, 0);
           if(!file_exists(ADDONS_PATH . 'payment/' . $payment_info['pay_code'] . '.php')){
               unset($payment_info['pay_code'] );
                ecs_header("Location: ".url('user/account/log'));
          } else {
            /* 调用相应的支付方式文件 */
            include_once(ADDONS_PATH . 'payment/' . $payment_info['pay_code'] . '.php');
            
            /* 取得在线支付方式的支付按钮 */
            $pay_obj = new $payment_info['pay_code'];
           
            $payment_info['pay_button'] = $pay_obj->get_code($order, $payment);

            /* 模板赋值 */

            $this->assign('payment', $payment_info);
            $this->assign('pay_fee', price_format($payment_info['pay_fee'], false));
            $this->assign('amount',  price_format($amount, false));
            $this->assign('order',   $order);
            $this->assign('type',   1);
            //$this->assign('page_title','账户充值');
            $this->assign('page_title',L('account_charge'));
            $this->assign('but', $payment_info['pay_button']);
            $this->display();
          }
           
        }
    }

    /**
     * 申请记录
     */
     public function actionLog() {
        $sql = "SELECT COUNT(*) FROM {pre}user_account  WHERE user_id = '$this->user_id'  AND process_type " . db_create_in(array(SURPLUS_SAVE, SURPLUS_RETURN));
        $record_count = $this->db->getOne($sql);
        if (IS_AJAX) {
            $page = I('page', 1, 'intval');
            $offset = 10;
            $page_size = ceil($record_count / $offset);
            $limit = ' LIMIT ' . ($page - 1) * $offset . ',' . $offset;
            $log_list = get_account_log($this->user_id,'','','',$count, $limit);
            die(json_encode(array('log_list' => $log_list['log_list'], 'totalPage' => $page_size, 'count' => $count)));
        }
        $this->assign('page_title',L('account_apply_record'));
        $this->display();
    }
    //账户详情Log  详情
    
     public function actionAccountDetail() {
        $page = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;
        $id=isset($_REQUEST['id']) ? intval($_REQUEST['id']) : '';
        //获取余额记录
        $log_detail = get_account_log($this->user_id, $pager['size'], $pager['start'],$id);
        $account_log=$log_detail['log_list'];
        if(!$account_log) {
            $this->redirect('user/account/log');
        }
        foreach($account_log as $key => $val){
            $account_log[$key]['pay_fee'] = empty($val['pay_fee']) ? price_format(0) : price_format($val['pay_fee']);
        }
        //模板赋值
        $this->assign('surplus_amount', price_format($surplus_amount, false));
        $this->assign('account_log', $account_log);
        $this->assign('pager', $pager);
        //$this->assign('page_title', '详情');
        $this->assign('page_title',L('account_details'));
        $this->display('account');
    }
 
    /**
     * 操作取消
     */
    public function actionCancel(){
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
 
        if ($id == 0 || $this->user_id == 0)
        {
            ecs_header("Location: ".url('user/account/log'));
        exit;
        }

        $result = del_user_account($id, $this->user_id);
        if ($result)
        {
            ecs_header("Location: ".url('user/account/log'));
            exit;
        }
    }
    
    /**
     * 红包列表
     * 10条
     * bonus_status 0 未使用 1 使用 2 未开始 3 已使用
     */
    public function actionBonus(){
        if(IS_AJAX){
            $page = I('page', 0, 'intval');
            $size = I('size', 0, 'intval');
            $type = I('type', 0, 'intval');
            $num = get_user_conut_bonus($this->user_id);

            $bonus = get_user_bouns_list($this->user_id, $type, $size, ($page-1)*$size);
            $result['totalPage'] = ceil($num/$size);

            $result['bonus'] = $bonus;
            echo json_encode($result);
            exit;
        }
        $bonus = get_user_bouns_list($this->user_id, null, 15, 0);

        $status['one'] = 0;
        $status['two'] = 0;
        $status['three'] = 0;
        foreach($bonus as $key => $val){
            if($val['bonus_status']===0 || $val['bonus_status']===2) {
                $status['one']++;
            }
            if($val['bonus_status']===1) {
                $status['two']++;
            }if($val['bonus_status']===3) {
                $status['three']++;
            }
        }
        $this->assign('status',$status);
        //$this->assign('page_title','优惠券列表');
        $this->assign('page_title',L('account_discount_list'));
//        $this->assign('bonus', $bonus);
        $this->display();
    }
    /**
     * 个人中心优惠券
     * 显示优惠券
     */
    public function actionCoupont(){
        $size = 10;
        $page = I('page', 1, 'intval');
        $status = I('status', 0, 'intval');
        if(IS_AJAX){
            $coupons_list = get_coupons_lists($size, $page, $status);
            exit(json_encode(array('coupons_list'=>$coupons_list, 'totalPage'=>$coupons_list['totalpage'])));
        }
        $this->assign('status', $status);
        $this->assign('page_title',L('coupont_list'));
        $this->display();
    }

    /**
     * 添加红包
     */
    public function actionAddbonus(){
        if(IS_POST) {
            $bouns_sn = isset($_POST['bonus_sn']) ? intval($_POST['bonus_sn']) : '';
            $bouns_password = isset($_POST['bouns_password']) ? $_POST['bouns_password'] : '';

            if (add_bonus($this->user_id, $bouns_sn, $bouns_password)) {
                show_message(L('add_bonus_sucess'), L('back_up_page'), url('user/account/bonus'), 'info');
            } else {
                show_message(L('add_bonus_false'), L('back_up_page'), url('user/account/bonus'));
            }
        }
        //$this->assign('page_title','添加优惠券');
        $this->assign('page_title',L('add_bonus'));
        $this->display();
    }

    public function actionExchange(){
        $page = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;

        $account_type = 'pay_points';

        /* 获取记录条数 */
        $sql = "SELECT COUNT(*) FROM {pre}account_log  WHERE user_id = '$this->user_id'  AND $account_type <> 0 ";
        $record_count = $this->db->getOne($sql);

        //分页函数
        $pager = get_pager(url('user/account/exchange'), array(), $record_count, $page);

        //获取剩余余额
        $pay_points = $this->db->getOne("SELECT  pay_points FROM {pre}users WHERE user_id='$this->user_id'");

        if (empty($pay_points))
        {
            $pay_points = 0;
        }

        //获取余额记录
        $account_log = array();
        $sql = "SELECT * FROM {pre}account_log  WHERE user_id = '$this->user_id'  AND $account_type <> 0   ORDER BY log_id DESC";
        $res = $GLOBALS['db']->selectLimit($sql, $pager['size'], $pager['start']);
        foreach($res as $row)
        {
            $row['change_time'] = local_date(C('shop.date_format'), $row['change_time']);
            $row['type'] = $row[$account_type] > 0 ? L('account_inc') : L('account_dec');
            $row['user_money'] = price_format(abs($row['user_money']), false);
            $row['frozen_money'] = price_format(abs($row['frozen_money']), false);
            $row['rank_points'] = abs($row['rank_points']);
            $row['pay_points'] = abs($row['pay_points']);
            $row['short_change_desc'] = sub_str($row['change_desc'], 60);
            $row['amount'] = $row[$account_type];
            $account_log[] = $row;
        }
        //模板赋值
        $this->assign('pay_points', $pay_points);
        $this->assign('account_log',    $account_log);
        $this->assign('pager',          $pager);
        $this->display();
    }

    /**
     * 验证是否登录
     */
    public function actionchecklogin(){

        if(!$this->user_id){
            $url = urlencode(__HOST__ . $_SERVER['REQUEST_URI']);
            if(IS_POST) {
                $url = urlencode($_SERVER['HTTP_REFERER']);
            }
            ecs_header("Location: ".url('user/login/index',array('back_act'=>$url)));
            exit;
        }
    }
     /**
     * 会员通过帐目明细列表进行再付款的操作
     */
    public function actionPay()
    {
        //变量初始化
        $surplus_id = isset($_GET['id'])  ? intval($_GET['id'])  : 0;
        $payment_id = isset($_GET['pid']) ? intval($_GET['pid']) : 0;
        if ($surplus_id == 0)
        {
            ecs_header("Location: ".url('User/account_log'));
            exit;
        }
    
        //如果原来的支付方式已禁用或者已删除, 重新选择支付方式
        if ($payment_id == 0)
        {
            ecs_header("Location: " . url('User/account_deposit',array('id'=>$surplus_id)));
            exit;
        }
    
        //获取单条会员帐目信息
        $order = array();
        $order =get_surplus_info($surplus_id);
    
        //支付方式的信息
        $payment_info = array();
        $payment_info = payment_info($payment_id);
    
        /* 如果当前支付方式没有被禁用，进行支付的操作 */
        if (!empty($payment_info))
        {
            //取得支付信息，生成支付代码
            $payment = unserialize_config($payment_info['pay_config']);
    
            //生成伪订单号
            $order['order_sn'] = $surplus_id;
    
            //获取需要支付的log_id
            $order['log_id'] =get_paylog_id($surplus_id, $pay_type = PAY_SURPLUS);
    
            $order['user_name']      = $_SESSION['user_name'];
            $order['surplus_amount'] = $order['amount'];
    
            //计算支付手续费用
            $payment_info['pay_fee'] = pay_fee($payment_id, $order['surplus_amount'], 0);
    
            //计算此次预付款需要支付的总金额
            $order['order_amount']   = $order['surplus_amount'] + $payment_info['pay_fee'];
    
            //如果支付费用改变了，也要相应的更改pay_log表的order_amount
            $order_amount =$this->db->getOne("SELECT order_amount FROM {pre}pay_log WHERE log_id = '$order[log_id]'");
            $this->db->getOne("SELECT COUNT(*) FROM {pre}order_goods WHERE order_id='$order[order_id]'AND is_real = 1");
            if ($order_amount <> $order['order_amount'])
            {
               $this->db->query("UPDATE {pre}pay_log SET order_amount = '$order[order_amount]' WHERE log_id = '$order[log_id]'");
            }
            if(!file_exists(ADDONS_PATH . 'payment/' . $payment_info ['pay_code'] . '.php')){
                unset($payment_info ['pay_code']);
            }else{
                /* 调用相应的支付方式文件 */
                include_once (ADDONS_PATH . 'payment/' . $payment_info ['pay_code'] . '.php');
                /* 取得在线支付方式的支付按钮 */
                $pay_obj = new $payment_info['pay_code']();
                $payment_info['pay_button'] = $pay_obj->get_code($order, $payment);
            }
           
        }
        
    }
    //银行卡列表
    public function actionCardList() {
        if(IS_AJAX){
           $id = I('id');
           if(empty($id)){
              exit();
           }
           $this->model->table('user_bank')->where(array('id'=>$id))->delete();
           exit();
        }
        $card_list = get_card_list($this->user_id);
        $this->assign('card_list', $card_list);
        //$this->assign('page_title', '银行卡列表');
        $this->assign('page_title',L('account_card_list'));
        $this->display();
    }
    
    //添加银行卡
    public function actionAddCard() {
        if(IS_POST){
            $bank_card = I('bank_card','');
            $pre='/^\d*$/';
            if(!preg_match($pre,$bank_card)){
                show_message("请输入正确的卡号");
            }
            $bank_region = I('bank_region','');
            $bank_name = I('bank_name','');
            $bank_user_name = I('bank_user_name','');
            $user_id = $this->user_id;
            if($this->user_id<0){
               show_message('请重新登录');
            }
            $sql = "INSERT INTO {pre}user_bank (bank_name,bank_region,bank_card,bank_user_name,user_id)
                    value('$bank_name','$bank_region',$bank_card,'$bank_user_name',$user_id)";
            if($this->db->query($sql)){
//               show_message('添加成功','返回列表',url('card_list'),'success');
                 show_message(L('account_add_success'),L('account_back_list'),url('card_list'),'success');
            }else{
//               show_message('添加失败','继续添加',url('add_card'),'fail');
                show_message(L('account_add_error'),L('account_add_continue'),url('add_card'),'fail');
            }
        }
        //$this->assign('page_title', '添加银行卡');
        $this->assign('page_title',L('account_add_card'));
        $this->display();
    }



    /***
     * 获取用户拥有的优惠券 默认返回所有用户所拥有的优惠券; bylu
     * @param string $user_id 用户ID;
     * @param bool|false $is_use 找出当前用户可以使用的
     * @param bool|false $total 订单总价
     * @param bool|false $cart_goods 商品信息
     * @param bool|false $user 用于区分是否会员中心里取数据(会员中心里的优惠券不能分组)
     * @return mixed 优惠券数组
     */
    public  function get_user_coupons_list($user_id = '', $is_use = false, $total = false, $cart_goods = false, $user = true) {

        $time = gmtime();

        //可使用的(平台用平台发的,商家用商家发的,当订单中混合了平台与商家的商品时,各自计算各自的商品总价是否达到各自发放的优惠券门槛,达到的话当前整个订单即可使用该优惠券)
        if ($is_use && $total && $cart_goods) {

            //获取到订单中不同商家或平台的所有商品总价
            foreach($cart_goods as $k=>$v){
                $res[$v['ru_id']][]=$v;
            }
            foreach($res as $k=>$v){
                foreach($v as $m=>$n){
                    $store_total[$k]+=$n['goods_price'] * $n['goods_number'];
                }
            }

            //获取当前订单可使用的优惠券
            foreach ($cart_goods as $k => $v) {
                foreach($store_total as $m=>$n){
                    $where = " WHERE cu.is_use=0 AND c.cou_end_time > $time AND $time>c.cou_start_time AND " . $n . " >= c.cou_man AND cu.user_id ='$user_id'
                        AND (c.cou_goods =0 OR FIND_IN_SET('".$v['goods_id']."',c.cou_goods)) AND c.ru_id='" . $v['ru_id'] . "'";
                    $sql = " SELECT c.*,cu.*,o.order_sn,o.add_time FROM " . $GLOBALS['ecs']->table('coupons_user') . " cu LEFT JOIN " . $GLOBALS['ecs']->table('coupons') . " c ON c.cou_id=cu.cou_id LEFT JOIN " . $GLOBALS['ecs']->table('order_info') . " o ON cu.order_id=o.order_id $where ";
                    $arrr[] = $GLOBALS['db']->getAll($sql);
                }
            }
            foreach ($arrr as $k => $v) {
                foreach ($v as $m => $n) {
                    $arr[$n['uc_id']] = $n;
                }
            }

            return $arr;

        } else {

            if (!empty($user_id) && $user) {
                $where = " WHERE cu.user_id IN(" . $user_id . ")";
            } else if (!empty($user_id)) {
                $where = " WHERE cu.user_id IN(" . $user_id . ") GROUP BY c.cou_id";
            }

            $res = $GLOBALS['db']->getAll(" SELECT c.*,cu.*,o.order_sn,o.add_time FROM " .
                $GLOBALS['ecs']->table('coupons_user') . " cu LEFT JOIN " . $GLOBALS['ecs']->table('coupons') .
                " c ON c.cou_id=cu.cou_id LEFT JOIN " . $GLOBALS['ecs']->table('order_info') .
                " o ON cu.order_id=o.order_id $where ");

            return $res;
        }
    }



}