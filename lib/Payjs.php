<?php

class Payjs
{
    private $ssl = true;
    private $requestUrl = 'https://payjs.cn/api/';
    private $MerchantID;
    private $MerchantKey;
    private $NotifyURL = null;
    private $AutoSign = true;
    private $ToObject = true;

    /**
     * Payjs constructor.
     * @param $config
     */
    public function __construct($config = null)
    {
        if (!is_array($config)) {
            return false;
        }
        foreach ($config as $key => $val) {
            if (isset($key)) {
                $this->$key = $val;
            }
        }
    }

    /*
     * 扫码支付
     * @return json
     */
    public function qrPay($data = [])
    {
        return $this->merge('native', [
            'total_fee' => $data['TotalFee'],
            'body' => $data['Body'],
            'attach' => @$data['Attach'],
            'out_trade_no' => $data['outTradeNo']
        ]);
    }

    /*
     * 收银台支付
     * @return mixed
     */
    public function Cashier($data = [])
    {
        return $this->merge('cashier', [
            'total_fee' => $data['TotalFee'],
            'body' => $data['Body'],
            'attach' => @$data['Attach'],
            'out_trade_no' => $data['outTradeNo'],
            'callback_url' => @$data['callbackUrl']
        ]);
    }

    /*
     * 订单查询
     * @return mixed
     */
    public function Query($data = [])
    {
        return $this->merge('check', [
            'payjs_order_id' => $data['PayjsOrderId']
        ]);
    }

    /*
     * 关闭订单
     * @return json
     */
    public function Close($data = [])
    {
        return $this->merge('close', [
            'payjs_order_id' => $data['PayjsOrderId']
        ]);
    }

    /*
     * 获取用户资料
     * @return json
     */
    public function User($data = [])
    {
        return $this->merge('user', [
            'openid' => $data['openid']
        ]);
    }

    /*
     * 获取商户资料
     * @return json
     */
    public function Info()
    {
        return $this->merge('info');
    }

    /*
     * 验证notify数据
     * @return Boolean
     */
    public function Checking($data = array())
    {
        $beSign = $data['sign'];
        unset($data['sign']);
        if ($this->Sign($data) == $beSign) {
            return true;
        } else {
            return false;
        }
    }

    /*
     * 数据签名
     * @return string
     */
    protected function Sign(array $data)
    {
        ksort($data);
        return strtoupper(md5(urldecode(http_build_query($data)) . '&key=' . $this->MerchantKey));
    }

    /*
     * 预处理数据
     * @return mixed
     */
    protected function merge($method, $data = [])
    {
        if ($this->AutoSign) {
            if (!array_key_exists('payjs_order_id', $data)) {
                $data['mchid'] = $this->MerchantID;
                if (!empty($this->NotifyURL)) {
                    $data['notify_url'] = $this->NotifyURL;
                }
                if (is_null(@$data['attach'])) {
                    unset($data['attach']);
                }
            }
            $data['sign'] = $this->Sign($data);
        }
        return $this->Curl($method, $data);
    }

    /*
     * curl
     * @return mixed
    */
    protected function Curl($method, $data, $options = array())
    {
        $url = $this->requestUrl . $method;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        if (!empty($options)) {
            curl_setopt_array($ch, $options);
        }
        if (!$this->ssl) {
            //https请求 不验证证书和host
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        $cexecute = curl_exec($ch);
        curl_close($ch);
        if ($cexecute) {
            if ($this->ToObject) {
                return json_decode($cexecute);
            } else {
                return $cexecute;
            }
        } else {
            return false;
        }
    }
}