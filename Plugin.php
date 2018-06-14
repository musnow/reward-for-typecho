<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 我觉得可以，使用payjs打赏
 *
 * @package Reward
 * @author  沐雪秋风
 * @version 1.0
 * @link https://yubanmei.com/
 */

class Reward_Plugin implements Typecho_Plugin_Interface
{
    //启用插件
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Archive')->footer = array('Reward_Plugin', 'footer');
        Typecho_Plugin::factory('Widget_Archive')->singleHandle = array('Reward_Plugin','render');

        //增加路由
        Helper::addRoute("reward_alipay_notify","/reward/alipay/notify","Reward_Action",'reward_alipay_notify');
        Helper::addRoute("reward_payjs_notify","/reward/payjs/notify","Reward_Action",'reward_payjs_notify');
        Helper::addRoute("reward_order","/reward/order","Reward_Action",'reward_order');
        Helper::addRoute("reward_query","/reward/query","Reward_Action",'reward_query');

        //写入数据库架构
        $db = Typecho_Db::get();
        /** 初始化数据库结构 */
        $scripts = file_get_contents ('usr/plugins/Reward/full_table.sql');
        $scripts = str_replace('typecho_',$db->getPrefix(), $scripts);
        $scripts = explode(';', $scripts);
        foreach ($scripts as $script) {
            $script = trim($script);
            if ($script) {
                $db->query($script, Typecho_Db::WRITE);
            }
        }

        return _t("插件已启用");
    }
    /* 个人用户的配置方法 */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
    /* 插件配置方法 */
    public static function config(Typecho_Widget_Helper_Form $form){

        $notify_wxpay_url = Helper::options()->siteUrl . 'reward/payjs/notify';
        $notify_alipay_url = Helper::options()->siteUrl . 'reward/alipay/notify';
        $settings = [
            new Typecho_Widget_Helper_Form_Element_Text('reward_payjs_id', NULL, '', _t('payjs商户号')),
            new Typecho_Widget_Helper_Form_Element_Text('reward_payjs_key', NULL, '', _t('payjs接口通信密钥')),
            new Typecho_Widget_Helper_Form_Element_Text('reward_payjs_notify_url', NULL, $notify_wxpay_url, _t('payjs接口回调地址'),_t('没有特殊需求请不要更改')),

            new Typecho_Widget_Helper_Form_Element_Text('reward_alipay_id', NULL, '', _t('支付宝APPID')),
            new Typecho_Widget_Helper_Form_Element_Text('reward_alipay_private_key', NULL, '', _t('支付宝RSA2(SHA256)应用密钥'),_t('就是你自己生成的rsa密钥')),
            new Typecho_Widget_Helper_Form_Element_Text('reward_alipay_public_key', NULL, '', _t('支付宝RSA2(SHA256)公钥'),_t('支付宝公钥，支付宝公钥，支付宝公钥')),
            new Typecho_Widget_Helper_Form_Element_Text('reward_alipay_notify_url', NULL, $notify_alipay_url, _t('支付宝接口回调地址'),_t('没有特殊需求请不要更改')),

            new Typecho_Widget_Helper_Form_Element_Text('success_msg', NULL, '感谢大佬的打赏，定当尽心尽力更新文章', _t('充值成功时，返回信息'),_t('不要随便关闭插件，会导致数据库数据清空，已经存在数据的请先备份数据库')),

            new Typecho_Widget_Helper_Form_Element_Checkbox(
                'reward_ssl',
                array(true => _t('验证ssl证书')),
                array(true),
                _t('是否验证ssl证书')),

            new Typecho_Widget_Helper_Form_Element_Radio(
                'recode_type',
                array(
                    1 => _t('iClick'),
                    2 => _t('快站'),
                    3 => _t('QR Code Generator'),
                ),
                1,
                _t('二维码生成方式')),
        ];

        foreach ($settings as $key){
            $form->addInput($key);
        }
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
        Helper::removeRoute("reward_order");
        Helper::removeRoute("reward_query");
        Helper::removeRoute("reward_alipay_notify");
        Helper::removeRoute("reward_payjs_notify");
        return _t("插件已禁用");
    }

    //插入页面尾部
    public static function footer(){
        echo '<script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>';
        echo '<script src="'.Helper::options()->pluginUrl .'/Reward/js/app.js'.'"></script>';
    }

    //插入打赏代码
    public static function render($archive){
        //不能使用缩进，会解析空格和tab，支持markdown语法
        $rewardhtml = '
<p id="plugin">
    <button id="plugin-render">打赏</button>
</p>
';
        $archive->text .= $rewardhtml;
    }
}