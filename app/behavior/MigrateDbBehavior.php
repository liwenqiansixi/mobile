<?php
namespace app\behavior;

use ectouch\Install;
use Symfony\Component\Filesystem\Filesystem;
use app\classes\Mysql;

/**
 * 系统行为扩展：数据迁移
 */
class MigrateDbBehavior
{
    private $model;
    private $fs;
    private $upgrade_file = 'storage/upgrade.php';
    private $migrate_path = 'database/migrations/';
    private $migration_files = array();
    private $version = 0;

    // 行为扩展的执行入口必须是run
    public function run()
    {
        $this->upgrade_file = ROOT_PATH . $this->upgrade_file;
        if (is_file($this->upgrade_file) || isset($_GET['force_migrate'])) {
            // 初始版本
            $this->initVersion();
            // 转换版本格式
            $this->convertVersion();
            // 准备迁移文件
            $this->beforeMigrate();
            // 迁移数据
            $this->migrations();
            // 移除迁移锁定文件
            if (!APP_DEBUG) {
                $this->fs->remove($this->upgrade_file);
            }
        }
    }

    /**
     * 准备版本记录
     */
    private function initVersion()
    {
        $this->model = new Mysql();

        $this->fs = new Filesystem();

        $result = $this->model->table('shop_config')->where(array('code' => 'migrate_version'))->find();
        if (empty($result)) {
            // 兼容1.9以下版本
            $migration_version = ROOT_PATH . $this->migrate_path . '.version';

            if (file_exists($migration_version)) {
                $version = floatval(file_get_contents($migration_version));
            } else {
                $version = 0;
            }

            // 初次创建迁移版本记录
            $data = array(
                'parent_id' => 9,
                'code' => 'migrate_version',
                'type' => 'hidden',
                'value' => $version,
                'sort_order' => 1,
            );

            $result = $this->model->table('shop_config')->add($data);

            if ($result && file_exists($migration_version)) {
                $this->fs->remove($migration_version);
            }

            $this->version = $version;
        }

        $this->version = $result['value'];
    }

    /**
     * 转换版本
     */
    private function convertVersion()
    {
        // 获取已迁移数据文件
        $this->migration_files = glob(ROOT_PATH . $this->migrate_path . 'migrate-*.sql');

        // 移除1.9之前的命名文件
        foreach ($this->migration_files as $vo) {
            if (substr(basename($vo), 0, 12) == 'migrate-2016') {
                $this->fs->remove($vo);
            }
        }

        // 更新 201610191600
        $result = $this->model->table('shop_config')->where(array('code' => 'migrate_version'))->find();

        if (substr($result['value'], 0, 4) == '2016') {
            $data['value'] = strtotime($result['value']);
            $this->model->table('shop_config')->where(array('code' => 'migrate_version'))->save($data);
        }
    }

    /**
     * 准备迁移文件
     * @throws \Exception
     */
    private function beforeMigrate()
    {
        // 生成文件hash
        $migration_hash = array();

        foreach ($this->migration_files as $vo) {
            $migration_hash[] = hash_file('md5', $vo);
        }

        // 获取待迁移数据文件
        $app_db_list = glob(BASE_PATH . "http/*/database/*.sql");

        // 模块排序
        foreach ($app_db_list as $key => $file) {
            if (stripos($file, 'http/wechat/database/db.sql') !== false) {
                $wechat = $app_db_list[$key];
                unset($app_db_list[$key]);
                array_unshift($app_db_list, $wechat);
            }
        }

        // 迁移文件
        foreach ($app_db_list as $key => $original) {
            // 生成文件hash
            $hash = hash_file('md5', $original);
            if (!in_array($hash, $migration_hash)) {
                // 待迁移文件名
                $file_time = time() + $key;
                $migration = ROOT_PATH . $this->migrate_path . 'migrate-' . $file_time . '0.sql';
                $migrate_path = dirname($migration);
                // 目录检测
                if (!is_dir($migrate_path)) {
                    if (!mkdir($migrate_path, 0777, true)) {
                        throw new \Exception("Can not create dir '{$migrate_path}'", 500);
                    }
                }
                if (!is_writable($migrate_path)) chmod($migrate_path, 0777);
                // 移动数据库文件
                if (is_file($original)) {
                    $this->fs->copy($original, $migration);
                }
            }
        }
    }

    /**
     * 迁移数据
     */
    private function migrations()
    {
        // 迁移文件版本排序
        asort($this->migration_files);
        // 开始迁移
        foreach ($this->migration_files as $file) {
            $current_version = $this->getVersionFromFile($file);
            if ($current_version <= $this->version) {
                continue;
            }
            $res = Install::mysql($file, '{pre}', config('DB_PREFIX'));
            // 执行sql
            if (is_array($res)) {
                foreach ($res as $sql) {
                    $this->model->execute($sql);
                }
            }
            // 更新迁移版本
            $data = array('value' => $current_version);
            $this->model->table('shop_config')->where(array('code' => 'migrate_version'))->save($data);
        }
    }

    /**
     * 获取文件版本号
     * @param $file
     * @return float
     */
    private function getVersionFromFile($file)
    {
        $filename = basename($file, '.sql');
        return floatval(substr($filename, strlen('migrate-')));
    }
}
