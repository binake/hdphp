<?php
// .-----------------------------------------------------------------------------------
// |  Software: [HDPHP framework]
// |   Version: 2013.01
// |      Site: http://www.hdphp.com
// |-----------------------------------------------------------------------------------
// |    Author: 向军 <2300071698@qq.com>
// | Copyright (c) 2012-2013, http://houdunwang.com. All Rights Reserved.
// |-----------------------------------------------------------------------------------
// |   License: http://www.apache.org/licenses/LICENSE-2.0
// '-----------------------------------------------------------------------------------

/**
 * 视图处理抽象层
 * @package     View
 * @author      后盾向军 <houdunwangxj@gmail.com>
 */
abstract class View
{

    /**
     * 获得模版文件
     */
    protected function getTemplateFile($file)
    {
        if (is_null($file)) {
            /**
             * 没有传参时使用 动作为为文件名
             */
            $file = CONTROLLER_VIEW_PATH . ACTION;
        } else if (!strstr($file, '/')) {
            /**
             * 没有路径时使用控制器视图目录
             */
            $file = CONTROLLER_VIEW_PATH . $file;
        }
        /**
         * 没有设置扩展名时，添加扩展名
         */
        if (!preg_match('/\.\w+$/', $file)) {
            $file .= C('TPL_FIX');
        }
        /**
         * 模板文件检测
         */
        if (is_file($file)) {
            return $file;
        } else {
            DEBUG && halt("模板不存在:$file");
            return false;
        }
    }
}