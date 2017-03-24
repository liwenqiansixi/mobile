<?php
namespace app\behavior;

/**
 * 系统行为扩展：模板解析
 */
class ParseTemplateBehavior
{

    // 行为扩展的执行入口必须是run
    public function run(&$content)
    {
        $content = $this->templateContentReplace($content);
    }

    /**
     * 模板内容替换
     * @access protected
     * @param string $content 模板内容
     * @return string
     */
    protected function templateContentReplace($content)
    {
        $label = array(
            /**variable label
                {$name} => <?php echo $name;?>
                {$user['name']} => <?php echo $user['name'];?>
                {$user.name}    => <?php echo $user['name'];?>
            */
            '/{(\\$[a-zA-Z_]\w*(?:\[[\w\.\"\'\[\]\$]+\])*)}/i' => "<?php echo $1; ?>",
            '/\$(\w+)\.(\w+)\.(\w+)\.(\w+)/is' => "\$\\1['\\2']['\\3']['\\4']",
            '/\$(\w+)\.(\w+)\.(\w+)/is' => "\$\\1['\\2']['\\3']",
            '/\$(\w+)\.(\w+)/is' => "\$\\1['\\2']",

            /**constance label
            {CONSTANCE} => <?php echo CONSTANCE;?>
            */
            '/\{([A-Z_\x7f-\xff][A-Z0-9_\x7f-\xff]*)\}/s' => "\\1/",

            /**include label
                {include file="test"}
            */
            '/{include\s*file=\"(.*)\"}/i' => "{include file=\"$1\" /}",

            /**if label
                {if $name==1}       =>  <?php if ($name==1){ ?>
                {elseif $name==2}   =>  <?php } elseif ($name==2){ ?>
                {else}              =>  <?php } else { ?>
                {/if}               =>  <?php } ?>
            */
            '/\{if\s+(.+?)\}/' => "<?php if(\\1) { ?>",
            '/\{else\}/' => "<?php } else { ?>",
            '/\{elseif\s+(.+?)\}/' => "<?php } elseif (\\1) { ?>",
            '/\{\/if\}/' => "<?php } ?>",

            '/\s+heq\s+/' => '===',
            '/\s+nheq\s+/' => '!==',
            '/\s+eq\s+/' => '==',
            '/\s+neq\s+/' => '!=',
            '/\s+egt\s+/' => '>=',
            '/\s+gt\s+/' => '>',
            '/\s+elt\s+/' => '<=',
            '/\s+lt\s+/' => '<',

            /**for label
                {for $i=0;$i<10;$i++}   =>  <?php for($i=0;$i<10;$i++) { ?>
                {/for}                  =>  <?php } ?>
            */
            '/\{for\s+(.+?)\}/' => "<?php for(\\1) { ?>",
            '/\{\/for\}/' => "<?php } ?>",

            /**foreach label
                {foreach $arr as $vo}           =>  <?php $n=1; if (is_array($arr) foreach($arr as $vo){ ?>
                {foreach $arr as $key => $vo}   =>  <?php $n=1; if (is_array($array) foreach($arr as $key => $vo){ ?>
                {/foreach}                  =>  <?php $n++;}unset($n) ?>
            */

            '/\{foreach\s+(\S+)\s+as\s+(\S+)\}/' => "<?php \$n=1;if(is_array(\\1)) foreach(\\1 as \\2) { ?>",
            '/\{foreach\s+(\S+)\s+as\s+(\S+)\s*=>\s*(\S+)\}/' => "<?php \$n=1; if(is_array(\\1)) foreach(\\1 as \\2 => \\3) { ?>",
            '/\{\/foreach\}/' => "<?php \$n++;}unset(\$n); ?>",
            '/\{foreach\s+from=\$(\S+?)\s+item=(\S+?)\}/' => "<?php \$n=1;if(is_array($\\1)) foreach($\\1 as $\\2) { ?>",
            '/\{foreach\s+from=\$(\S+?)\s+item=(\S+?)\s+key=(\S+?)\}/' => "<?php \$n=1; if(is_array($\\1)) foreach($\\1 as $\\3 => $\\2) { ?>",

            /**function label
                {date('Y-m-d H:i:s')}   =>  <?php echo date('Y-m-d H:i:s');?>
                {$date('Y-m-d H:i:s')}  =>  <?php echo $date('Y-m-d H:i:s');?>
            */
            '/\{([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff:]*\(([^{}]*)\))\}/' => "<?php echo \\1;?>",
            '/\{(\\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff:]*\(([^{}]*)\))\}/' => "<?php echo \\1;?>"
        );

        foreach ($label as $key => $value) {
            $content = preg_replace($key, $value, $content);
        }
        return $content;
    }
}
