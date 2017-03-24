<?php
namespace app\classes;

use Symfony\Component\Filesystem\Filesystem;
use ectouch\Http;

class Compile
{
    public static $savePath = '';

    /**
     * 初始化
     */
    public static function init()
    {
        self::$savePath = ROOT_PATH . 'storage/app/diy';
        if (!is_dir(self::$savePath)) {
            $fs = new Filesystem();
            $fs->mkdir(self::$savePath);
        }
    }

    /**
     * 保存可视化编辑的配置数据
     */
    public static function setModule($file = 'index', $data = array())
    {
        self::init();
        if (!empty($data)) {
            $data = '<?php exit("no access");' . serialize($data);
            file_put_contents(self::$savePath . '/' . $file . '.php', $data);
        }
    }

    /**
     * 获取可视化配置的数据
     */
    public static function getModule($file = 'index')
    {
        self::init();
        $filePath = self::$savePath . '/' . $file . '.php';
        if (is_file($filePath)) {
            $data = file_get_contents($filePath);
            $data = str_replace('<?php exit("no access");', '', $data);
            return unserialize($data);
        }
        return false;
    }

    /**
     * 清空模块
     */
    public static function cleanModule($file = 'index')
    {
        self::init();
        $filePath = self::$savePath . '/' . $file . '.php';
        if (is_file($filePath)) {
            return unlink($filePath);
        }
        return true;
    }

    /**
     * 默认初始化数据
     * @return array
     */
    public static function initModule()
    {
        $data = array();
        //　加载搜索模块
        $search = self::load('search');
        $data[] = $search;

        // 加载图片模块
        $slider = self::load('picture');
        $res = insert_ads(array('id' => 256, 'num' => 10), true);
        $picImgList = array();
        foreach ($res as $key => $vo) {
            $picImgList[$key] = array(
                "desc" => '', // $vo['ad_name'],
                "img" => get_data_path($vo['ad_code'], 'afficheimg'),
                "link" => $vo['ad_link']
            );
        }
        $slider['data']['imgList'] = $picImgList;
        $data[] = $slider;

        // 加载导航模块
        $nav = self::load('nav');
        $res = dao('touch_nav')->where('ifshow=1')->order('vieworder asc, id asc')->select();
        $navImgList = array();
        foreach ($res as $key => $vo) {
            $navImgList[$key] = array(
                "desc" => $vo['name'],
                "img" => get_image_path('data/attached/nav/' . $vo['pic']),
                "link" => $vo['url']
            );
        }
        $nav['data']['imgList'] = $navImgList;
        $data[] = $nav;

        // 加载快报模块
        $notice = self::load('Announcement');
        $condition = array(
            'is_open' => 1,
            'cat_id' => 12
        );
        $list = dao('article')->field('article_id, title, author, add_time, file_url, open_type')
            ->where($condition)->order('article_type DESC, article_id DESC')->limit(5)->select();
        $res = array();
        foreach ($list as $key => $vo) {
            $res[$key]['text'] = $vo['title'];
            $res[$key]['url'] = build_uri('article', array('aid' => $vo['article_id']));
        }
        $notice['data']['contList'] = $res;
        $data[] = $notice;

        // 加载空白行模块
        $blank = self::load('Blank');
        $blank['data']['valueHeight'] = 5;
        $data[] = $blank;

        // 加载促销模块
        $spike = self::load('Spike');
        $list = self::goodsList(array('intro' => 'promotion'));
        $res = array();
        $endtime = gmtime(); // time() + 7 * 24 * 3600;
        foreach ($list as $key => $vo) {
            $res[$key]['desc'] = $vo['name']; // 描述
            $res[$key]['sale'] = $vo["sales_volume"]; // 销量
            $res[$key]['stock'] = $vo['goods_number']; // 库存
            $res[$key]['price'] = $vo['shop_price']; // 价格
            $res[$key]['marketPrice'] = $vo["market_price"]; // 市场价
            $res[$key]['img'] = $vo['goods_thumb']; // 图片地址
            $res[$key]['link'] = $vo['url']; // 图片链接
            $endtime = $vo['promote_end_date'] > $endtime ? $vo['promote_end_date'] : $endtime;
        }
        $spike['data']['moreLink'] = url('category/index/search', array('intro' => 'new'));
        $spike['data']['imgList'] = $res;
        $spike['data']['endTime'] = date('Y-m-d H:i:s', $endtime);
        $data[] = $spike;

        // 加载空白行模块
        $blank = self::load('Blank');
        $blank['data']['valueHeight'] = 5;
        $data[] = $blank;

        // 加载广告模块
        $slider = self::load('picture');
        $res = insert_ads(array('id' => 257, 'num' => 10), true);
        $picImgList = array();
        foreach ($res as $key => $vo) {
            $picImgList[$key] = array(
                "desc" => '', // $vo['ad_name'],
                "img" => get_data_path($vo['ad_code'], 'afficheimg'),
                "link" => $vo['ad_link']
            );
        }
        $slider['data']['imgList'] = $picImgList;
        $data[] = $slider;

        // 加载空白行模块
        $blank = self::load('Blank');
        $blank['data']['valueHeight'] = 5;
        $data[] = $blank;

        // 加载精品推荐模块
        $title = self::load('Title');
        $title['data']['title'] = '精品推荐';
        $data[] = $title;

        // 加载猜你喜欢商品模块
        $product = self::load('Product');
        $list = self::goodsList(array('intro' => 'best'));
        $res = array();
        foreach ($list as $key => $vo) {
            $res[$key]['desc'] = $vo['name']; // 描述
            $res[$key]['sale'] = $vo["sales_volume"]; // 销量
            $res[$key]['stock'] = $vo['goods_number']; // 库存
            $res[$key]['price'] = $vo['shop_price']; // 价格
            $res[$key]['marketPrice'] = $vo["market_price"]; // 市场价
            $res[$key]['img'] = $vo['goods_thumb']; // 图片地址
            $res[$key]['link'] = $vo['url']; // 图片链接
        }
        $product['data']['imgList'] = $res;
        $data[] = $product;

        // 加载空白行模块
        $blank = self::load('Blank');
        $blank['data']['valueHeight'] = 5;
        $data[] = $blank;

        // 加载广告模块
        $slider = self::load('picture');
        $res = insert_ads(array('id' => 258, 'num' => 10), true);
        $picImgList = array();
        foreach ($res as $key => $vo) {
            $picImgList[$key] = array(
                "desc" => '', // $vo['ad_name'],
                "img" => get_data_path($vo['ad_code'], 'afficheimg'),
                "link" => $vo['ad_link']
            );
        }
        $slider['data']['imgList'] = $picImgList;
        $data[] = $slider;

        // 加载空白行模块
        $blank = self::load('Blank');
        $blank['data']['valueHeight'] = 5;
        $data[] = $blank;

        // 加载猜你喜欢模块
        $title = self::load('Title');
        $title['data']['title'] = '猜你喜欢';
        $title['data']['isStyleSel'] = 1;
        $title['data']['isShowStyle'] = 'text-center';
        $data[] = $title;

        // 加载猜你喜欢商品模块
        $product = self::load('Product');
        $list = self::goodsList(array('intro' => 'hot'));
        $res = array();
        foreach ($list as $key => $vo) {
            $res[$key]['desc'] = $vo['name']; // 描述
            $res[$key]['sale'] = $vo["sales_volume"]; // 销量
            $res[$key]['stock'] = $vo['goods_number']; // 库存
            $res[$key]['price'] = $vo['shop_price']; // 价格
            $res[$key]['marketPrice'] = $vo["market_price"]; // 市场价
            $res[$key]['img'] = $vo['goods_thumb']; // 图片地址
            $res[$key]['link'] = $vo['url']; // 图片链接
        }
        $product['data']['imgList'] = $res;
        $data[] = $product;

        self::setModule('index', $data);
        return $data;
    }

    public static function load($module = '')
    {
        $modulePath = BASE_PATH . 'modules/components/' . ucfirst($module) . '.php';
        if (!empty($module) && is_file($modulePath)) {
            return require($modulePath);
        }
        return false;
    }

    /**
     * 返回商品列表
     * @param string $param
     * @return array
     */
    public static function goodsList($param = array())
    {
        $data = array(
            'id' => 0,
            'brand' => 0,
            'intro' => '',
            'price_min' => 0,
            'price_max' => 0,
            'filter_attr' => 0,
            'sort' => 'goods_id',
            'order' => 'desc',
            'keyword' => '',
            'isself' => 0,
            'hasgoods' => 0,
            'promotion' => 0,
            'page' => 1,
            'type' => 1,
            'size' => 10,
            config('VAR_AJAX_SUBMIT') => 1
        );
        $data = array_merge($data, $param);
        $cache_id = md5(serialize($data));
        $list = cache($cache_id);
        if ($list === false) {
            $url = url('category/index/products', $data, false, true);
            $res = Http::doGet($url);
            if ($res) {
                $data = json_decode($res, 1);
                $list = empty($data['list']) ? false : $data['list'];
                cache($cache_id, $list, 600);
            }
        }
        return $list;
    }
}