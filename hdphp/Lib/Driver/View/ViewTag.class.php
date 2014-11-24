<?php
// .-----------------------------------------------------------------------------------
// |  Software: [HDPHP framework]
// |   Version: 2013.01
// |      Site: http://www.hdphp.com
// |-----------------------------------------------------------------------------------
// |    Author: 向军 <houdunwangxj@gmail.com>
// | Copyright (c) 2012-2013, http://houdunwang.com. All Rights Reserved.
// |-----------------------------------------------------------------------------------
// |   License: http://www.apache.org/licenses/LICENSE-2.0
// '-----------------------------------------------------------------------------------

/**
 * HDPHP模板引擎标签解析类
 * @package        View
 * @subpackage  HDPHP模板
 * @author           后盾向军 <houdunwangxj@gmail.com>
 */
class ViewTag extends Tag
{
    //为Literal标签使用，记录literal标签号
    static $literal = array();
    /**
     * block 块标签       1为块标签  0独立标签
     * 块标题不用设置，行标签必须设置
     * 设置时不用加前面的_
     */
    public $tag = array(
        'foreach' => array('block' => 1, 'level' => 4),
        'while' => array('block' => 1, 'level' => 4),
        'if' => array('block' => 1, 'level' => 5),
        'elseif' => array('block' => 0,'level'=>0),
        'else' => array('block' => 0,'level'=>0),
        'switch' => array('block' => 1,'level'=>0),
        'case' => array('block' => 1,'level'=>0),
        'break' => array('block' => 0,'level'=>0),
        'default' => array('block' => 0,'level'=>0),
        'include' => array('block' => 0,'level'=>0),
        'list' => array('block' => 1, 'level' => 5),
        'js' => array('block' => 0,'level'=>0),
        'css' => array('block' => 0,'level'=>0),
        'noempty' => array('block' => 0,'level'=>0),
        'jsconst' => array('block' => 0,'level'=>0),
        'define' => array('block' => 0,'level'=>0),
        'literal' => array('block' => 1, 'level' => 1)
    );

    /**
     * 标签区域内的数据将被当作文本处理
     * @param $attr
     * @param $content
     * @return mixed
     */
    public function _literal($attr, $content)
    {
        self::$literal[] = $content;
        $id = count(self::$literal) - 1;
        return '###hd:Literal' . $id . '###';
    }

    //格式化参数 字符串加引号
    private function formatArg($arg)
    {
        $valueFormat = trim(trim($arg, "'"), '"');
        return is_numeric($valueFormat) ? $valueFormat : '"' . $valueFormat . '"';
    }

    /**
     * 替换标签属性变量或常量为php表示形式
     * @param array $attr 标签属性
     * @param bool $php 返回PHP语法格式
     * @return mixed
     */
    private function replaceAttrConstVar($attr, $php = true)
    {
        foreach ($attr as $k => $at) {
            //替换变量
            $attr[$k] = preg_replace('/\$\w+\[.*\](?!=\[)|\$\w+(?!=[a-z])/', '<?php echo \0;?>', $attr[$k]);
        }
        return $attr;
    }

    //定义常量
    public function _define($attr, $content)
    {
        $name = $attr['name'];
        $value = is_numeric($attr['value']) ? $attr['value'] : "'" . $attr['value'] . "'";
        $str = "";
        $str .= "<?php ";
        $str .= "define('{$name}',$value);";
        $str .= ";?>";
        return $str;
    }


    //加载CSS文件
    public function _css($attr, $content)
    {
        $attr = $this->replaceAttrConstVar($attr, true);
        return '<link type="text/css" rel="stylesheet" href="' . $attr['file'] . '"/>';
    }

    public function _js($attr, $content)
    {
        if (!isset($attr['file'])) return;
        $attr = $this->replaceAttrConstVar($attr, true);
        return '<script type="text/javascript" src="' . $attr['file'] . '"></script>';
    }


    public function _list($attr, $content)
    {
        if (!isset($attr['from']) || !isset($attr['name'])) return;
        $var = $attr['from'];
        $name = str_replace('$', '', $attr['name']);
        $empty = isset($attr['empty']) ? $attr['empty'] : ''; //无数据时
        $start = isset($attr['start']) ? intval($attr['start'] - 1) : 0;
        $step = isset($attr['step']) ? (int)$attr['step'] : 1;
        $php = '<?php ';
        $php .= '$hd["list"]["' . $name . '"]["total"]=0;'; //初始总计录条数
        $php .= 'if(isset(' . $var . ') && !empty(' . $var . ')):';
        $php .= '$_id_' . $name . '=0;'; //记录集中的第几条
        $php .= '$_index_' . $name . '=0;'; //采用的第几条
        $row = isset($attr['row']) ? (int)$attr['row'] * $step : 1000;
        $php .= '$last' . $name . '=min(' . $row . ',count(' . $var . '));' . "\n"; //共取几条记录
        $php .= '$hd["list"]["' . $name . '"]["first"]=true;' . "\n"; //第一条记录
        $php .= '$hd["list"]["' . $name . '"]["last"]=false;' . "\n"; //第最后一条记录
        $php .= '$_total_' . $name . '=ceil($last' . $name . '/' . $step . ');'; //共有多少条记录
        $php .= '$hd["list"]["' . $name . '"]["total"]=$_total_' . $name . ";\n"; //总记录条数
        $php .= "\$_data_" . $name . " = array_slice($var,$start,\$last" . $name . ");" . "\n"; //取要遍历的数据
        $php .= 'if(count($_data_' . $name . ')==0):echo "' . $empty . '";' . "\n"; //数组为空
        $php .= 'else:' . "\n"; //数组不为空时进行遍历
        $php .= 'foreach($_data_' . $name . ' as $key=>$' . $name . '):' . "\n";
        $php .= 'if(($_id_' . $name . ')%' . $step . '==0):$_id_' . $name . '++;else:$_id_' . $name . '++;continue;endif;' . "\n";
        $php .= '$hd["list"]["' . $name . '"]["index"]=++$_index_' . $name . ';' . "\n"; //第一条记录
        $php .= 'if($_index_' . $name . '>=$_total_' . $name . '):$hd["list"]["' . $name . '"]["last"]=true;endif;?>' . "\n"; //最后一条
        $php .= $content;
        $php .= '<?php $hd["list"]["' . $name . '"]["first"]=false;' . "\n";
        $php .= 'endforeach;' . "\n";
        $php .= 'endif;' . "\n";
        $php .= 'else:' . "\n";
        $php .= 'echo "' . $empty . '";' . "\n";
        $php .= 'endif;?>';
        return $php;
    }

    public function _foreach($attr, $content)
    {
        if (!isset($attr['from'])) return;
        $php = ''; //组合成PHP
        $from = $attr['from'];
        $key = isset($attr['key']) ? $attr['key'] : '$key';
        $value = isset($attr['value']) ? $attr['value'] : '$value';
        $php .= "<?php if(is_array($from)){?>";
        $php .= '<?php ' . " foreach($from as $key=>$value){ ?>";
        $php .= $content;
        $php .= '<?php }}?>';
        return $php;
    }

    /**
     * 加载模板文件
     * @param $attr
     * @param $content
     * @return string
     */
    public function _include($attr, $content)
    {
        if (!isset($attr['file'])) return;
        $const = print_const(false, true);
        foreach ($const as $k => $v) {
            $attr['file'] = str_replace($k, $v, $attr['file']);
        }
        $file = str_replace(__ROOT__ . '/', '', trim($attr['file']));
        $view = new ViewHd();
        $view->fetch($file);
        return $view->getCompileContent();
    }

    public function _switch($attr, $content, $res)
    {
        $value = $attr['value'];
        $php = ''; //组合成PHP
        $php .= '<?php ' . " switch($value):?>\r\n";
        $php .= preg_replace("/\s*<case/i", "<case", $content);
        $php .= '<?php endswitch;?>';
        return $php;
    }

    public function _case($attr, $content, $res)
    {
        $value = $this->formatArg($attr['value']);
        $php = ''; //组合成PHP
        $php .= '<?php ' . " case $value:{?>";
        $php .= $content;
        $php .= '<?php break;}?>';
        return $php;
    }

    public function _break($attr, $content, $res)
    {
        return '<?php break;?>';
    }

    public function _default($attr, $content, $res)
    {
        return '<?php default;?>';
    }

    public function _if($attr, $content, $res)
    {
        if (empty($attr['value'])) return;
        $value = $attr['value'];
        $php = ''; //组合成PHP
        $php .= '<?php if(' . $value . '){?>';
        $php .= $content;
        $php .= '<?php }?>';
        return $php;
    }

    public function _elseif($attr, $content, $res)
    {
        $value = $attr['value'];
        $php = ''; //组合成PHP
        $php .= '<?php ' . " }elseif($value){ ?>";
        $php .= $content;
        return $php;
    }

    public function _else($attr, $content, $res)
    {
        $php = ''; //组合成PHP
        $php .= '<?php ' . " }else{ ?>";
        return $php;
    }

    public function _while($attr, $content, $res)
    {
        if (empty($attr['value'])) return;
        $value = $attr['value'];
        $php = ''; //组合成PHP
        $php .= '<?php ' . " while($value){ ?>";
        $php .= $content;
        $php .= '<?php }?>';
        return $php;
    }

    public function _empty($attr, $content, $res)
    {
        if (empty($attr['value'])) return;
        $value = $attr['value'];
        $php = "";
        $php = '<?php $_emptyVar =isset(' . $value . ')?' . $value . ':null?>';
        $php .= '<?php ' . ' if( empty($_emptyVar)){?>';
        $php .= $content;
        $php .= '<?php }?>';
        return $php;
    }

    public function _noempty($attr, $content)
    {
        return '<?php }else{ ?>';
    }

    //设置js常量
    public function _jsconst($attr, $content)
    {
        $const = get_defined_constants(true);
        $arr = preg_grep("/http/", $const['user']);
        $str = "<script type='text/javascript'>\n";
        foreach ($arr as $k => $v) {
            $k = str_replace('_', '', $k);
            $str .= $k . " = '$v';\n";
        }
        $str .= "</script>";
        return $str;
    }
}