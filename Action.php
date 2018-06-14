<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once 'lib/Payjs.php';
require_once 'lib/AlipayF2F.php';

class Reward_Action implements Widget_Interface_Do
{
    public $payjs;
    public $alipay;
    public $db;

    public function execute(){}
    public function action(){}

    public function __construct()
    {
        $con = Helper::options()->plugin('Reward');
        $this->payjsinit($con);
        $this->alipayf2finit($con);
        $this->db = Typecho_Db::get();
    }

    //初始化payjs
    public function payjsinit($con)
    {
        if (!empty(@$con->reward_ssl[0])) {
            $ssl = true;
        } else {
            $ssl = false;
        }
        $config = [
            'MerchantID' => $con->reward_payjs_id,
            'MerchantKey' => $con->reward_payjs_key,
            'NotifyURL' => $con->reward_payjs_notify_url,
            'ssl' => $ssl,
        ];
        return $this->payjs = new Payjs($config);
    }

    //初始化支付宝当面付
    public function alipayf2finit($con)
    {
        if (!empty(@$con->reward_ssl[0])) {
            $ssl = true;
        } else {
            $ssl = false;
        }
        $config = [
            'appId' => $con->reward_alipay_id,
            'rsaPrivateKey' => $con->reward_alipay_private_key,
            'alipayPublicKey' => $con->reward_alipay_public_key,
            'notifyUrl' => $con->reward_alipay_notify_url,
            'ssl' => $ssl,
        ];
        return $this->alipay = new AlipayF2F($config);
    }

    //轮询逻辑
    public function reward_order()
    {
        $_id = @$_GET['id'];
        $_type = @$_GET['type'];
        if (empty($_id) || empty($_type)) {
            exit('参数不正确');
        }

        switch ($_type){
            case 'wxpay':
                $order = $this->db->fetchRow(
                    $this->db->select()
                        ->from('table.order_wxpay')
                        ->where('out_trade_no = ?', $_id)
                        ->where('return_code = ?', 1)
                        ->limit(1)
                );
                break;
            case 'alipay':
                $order = $this->db->fetchRow(
                    $this->db->select()
                        ->from('table.order_alipay')
                        ->where('out_trade_no = ?', $_id)
                        ->where('trade_status = ?', 'TRADE_SUCCESS')
                        ->limit(1)
                );
                break;
        }
        $olddata = json_decode(@$order['json_file'], true);

        if (!empty($order)) {
            switch ($_type) {
                case 'alipay':
                    $status = $this->alipay->rsaCheck($olddata);
                    break;
                case 'wxpay':
                    $status = $this->payjs->Checking($olddata);
                    break;
                default:
                    $status = false;
                    break;
            }

            if ($status) {

                $this->db->query(
                    $this->db->update('table.order')
                        ->rows(['status' => 1])
                        ->where('unique_id = ?', $_id)
                );

                echo json_encode([
                    'code' => 'success',
                    'data' => [
                        'msg' => Helper::options()->plugin('Reward')->success_msg
                    ],
                ]);
            } else {
                echo json_encode([
                    'code' => 'error',
                ]);
            }

        } else {
            echo json_encode([
                'code' => 'wait',
            ]);
        }


    }

    //生成二维码
    public function reward_query()
    {
        $total = @$_GET['total'];
        $_type = @$_GET['type'];
        if (empty($total) || empty($_type)) {
            exit('参数不正确');
        }

        $unique_id = $this->genRandomChar();

        switch ($_type) {
            case 'alipay':
                $ret = $this->alipay->qrPay([
                    'outTradeNo' => $unique_id,
                    'totalFee' => $total,
                    'orderName' => 'Reward article',
                ]);
                if (@$ret['alipay_trade_precreate_response']['code'] != 10000) {
                    $status = false;
                } else {
                    $status = true;
                    $title = '支付宝扫码';
                    $qrtmp = $this->getQRcodeURL($ret['alipay_trade_precreate_response']['qr_code']);
                    $qrcode_url = $qrtmp;
                }
                break;
            case 'wxpay':
                $_total = $total * 100;
                $ret = $this->payjs->qrPay([
                     'TotalFee' => $_total,
                     'Body' => 'Reward article',
                     'outTradeNo' => $unique_id,
                ]);
                if ($ret->return_code == 1) {
                    $status = true;
                    $title = '微信扫码';
                    $qrcode_url = $ret->qrcode;
                } else {
                    $status = false;
                }
                break;

            default:
                $status = false;
                echo json_encode([
                    'code' => 'error',
                ]);
                break;
        }


        if ($status) {

            $arr = [
                "unique_id" => $unique_id,
                "type" => $_type,
                "content" => '得到大佬打赏，￥' . $total,
                "order_total" => $total,
                "created_at" => time(),
            ];

            $this->db->query($this->db->insert('table.order')->rows($arr));

            echo json_encode([
                'code' => 'success',
                'data' => [
                    'id' => $unique_id,
                    'url' => $qrcode_url,
                    'title' => $title,
                ],
            ]);
        } else {
            echo json_encode([
                'code' => 'error',
                'msg' => '生成二维码错误',
            ]);
        }
    }

    public function reward_alipay_notify()
    {
        if (!empty(@$_REQUEST['notify_id'])) {
            $arr = [
                'out_trade_no' => @$_REQUEST['out_trade_no'],
                'buyer_logon_id' => @$_REQUEST['buyer_logon_id'],
                'trade_status' => @$_REQUEST['trade_status'],
                'total_amount' => @$_REQUEST['total_amount'],
                'receipt_amount' => @$_REQUEST['receipt_amount'],
                'buyer_pay_amount' => @$_REQUEST['buyer_pay_amount'],
                'notify_time' => @$_REQUEST['notify_time'],
                'notify_id' => @$_REQUEST['notify_id'],
                'json_file' => json_encode($_REQUEST),
            ];
            $this->db->query($this->db->insert('table.order_alipay')->rows($arr));
        }
        echo 'success';
    }

    public function reward_payjs_notify()
    {
        if (!empty(@$_REQUEST['return_code'])) {
            $arr = [
                'time_end' => @$_REQUEST['time_end'],
                'return_code' => @$_REQUEST['return_code'],
                'total_fee' => @$_REQUEST['total_fee'],
                'out_trade_no' => @$_REQUEST['out_trade_no'],
                'payjs_order_id' => @$_REQUEST['payjs_order_id'],
                'attach' => @$_REQUEST['attach'],
                'transaction_id' => @$_REQUEST['transaction_id'],
                'openid' => @$_REQUEST['openid'],
                'mchid' => @$_REQUEST['mchid'],
                'sign' => @$_REQUEST['sign'],
                'json_file' => json_encode($_REQUEST),
            ];
            $this->db->query($this->db->insert('table.order_wxpay')->rows($arr));
        }
        echo 'success';
    }


    //使用二维码api
    public function getQRcodeURL($str)
    {
        $type = Helper::options()->plugin('Reward')->recode_type;
        $size = 300;
        switch ($type) {
            case 1:
                return 'http://bshare.optimix.asia/barCode?site=weixin&url=' . $str;
                break;
            case 2:
                return 'https://www.kuaizhan.com/common/encode-png?large=true&data=' . $str;
                break;
            case 3:
                return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . $str;
                break;
            default:
                return 'http://bshare.optimix.asia/barCode?site=weixin&url=' . $str;
                break;
        }
    }

    //生成随机数
    public function genRandomChar($length = 8)
    {
        if (version_compare(PHP_VERSION, '7.0.0', '<')) {
            $random = random(pow(10, ($length - 1)), pow(10, $length) - 1);
        } else {
            $random = random_int(pow(10, ($length - 1)), pow(10, $length) - 1);
        }
        return date("YmdHis", time()) . $random;
    }

}