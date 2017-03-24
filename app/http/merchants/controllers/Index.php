<?php
namespace app\http\merchants\controllers;

use app\http\base\controllers\Frontend;

class Index extends Frontend{
    public $user_id;

    /**
     * 构造函数
     */

    public function __construct()
    {
        parent::__construct();
        $this->user_id = $_SESSION['user_id'];
        //验证登录
        $this->actionchecklogin();
        L(require(LANG_PATH  . C('shop.lang') . '/merchants.php'));
        $files = array(
            'clips',
            'transaction',
            'main'
        );
        $this->load_helper($files);
        $this->sid = 1;
    }

     /**
     * 入驻商家信息
     */
    public function actionIndex(){
        //验证商家是否申请
        $shop = $this->model->table('merchants_shop_information')->where(array('user_id'=>$this->user_id))->find();
        if($shop){
             ecs_header("Location: " . url('merchants/index/audit'));
        }
        if (IS_POST) {
            if(I('agree') == 1){
                $data['agreement'] = I('agree');
            }else{
                show_message('请同意用户协议', '', '', 'error');
            }
            $data['contactName'] = I('contactName');
            $data['contactPhone'] = I('contactPhone');
            $data['license_adress'] = I('license_adress');
            $data['company_located'] = I('province_region_id').','.I('city_region_id').','.I('district_region_id');
            if ($data['contactPhone']) {
                $preg = preg_match('#^13[\d]{9}$|^14[5,7]{1}\d{8}$|^15[^4]{1}\d{8}$|^17[0,6,7,8]{1}\d{8}$|^18[\d]{9}$#', $data['contactPhone']) ? true : false;
                if ($preg == FALSE) {
                    show_message(L('mobile_not_null'));
                }
            }
            if (empty($data['contactName'])) {
                show_message(L('msg_shop_owner_notnull'));
            }
            $data['user_id'] = $this->user_id;
            if ($this->model->table('merchants_steps_fields')->data($data)->add()) {
                    ecs_header("Location: " . url('merchants/index/shop'));
            }else{
                show_message(L('add_error'));
            }

        }
        $this->assign('page_title',L('business_information'));
        $this->display();
    }

     /**
     * 入驻店铺信息
     */
    public function actionShop(){
        if (IS_POST) {
            $data = I('');
            if (empty($data['rz_shopName'])) {
                show_message(L('msg_shop_name_notnull'));
            }
            if (empty($data['hopeLoginName'])) {
                show_message(L('msg_login_shop_name_notnull'));
            }
            $data['user_id'] = $this->user_id;
            if ($this->model->table('merchants_shop_information')->data($data)->add()) {
                    ecs_header("Location: " . url('merchants/index/audit'));
            }else{
                show_message(L('add_error'));
            }
        }

        $parent_id = 0;
        $sql = "select cat_id, cat_name from {pre}category where parent_id = '$parent_id'";
        $category = $this->db->getAll($sql);
        $this->assign('category',$category);
        $this->assign('page_title',L('store_information'));
        $this->display();
    }

    /**
     * 等待审核
     */
    public function actionAudit(){
        //店铺状态
        $shop = $this->model->table('merchants_shop_information')->field('merchants_audit,merchants_message')->where(array('user_id'=>$this->user_id))->find();
        $this->assign('shop',$shop);
        $this->assign('img',elixir('img/shenqing-loding.gif'));

        $this->assign('page_title',L('review_the_status'));
        $this->display();
    }

    /**
     * 入驻须知
     */
    public function actionGuide(){
        $sql = "select process_title, process_article from {pre}merchants_steps_process where process_steps = '$this->sid'";
        $row = $this->db->getRow($sql);
        if($row['process_article'] > 0){
            $row['article_centent'] = $this->db->getOne("select content from {pre}article where article_id = '" .$row['process_article']. "'");
        }
        $this->assign('row',$row);
        $this->assign('page_title',L('instructions'));
        $this->display();
    }

    /**
     * 验证是否登录
     */
    public function actionchecklogin()
    {

        if (!$this->user_id) {
            $url = urlencode(__HOST__ . $_SERVER['REQUEST_URI']);
            if (IS_POST) {
                $url = urlencode($_SERVER['HTTP_REFERER']);
            }
            ecs_header("Location: " . U('user/login/index', array('back_act' => $url)));
            exit;
        }
    }


}