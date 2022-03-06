<?php

namespace App\Payments;

class Bifu365 {
    public function __construct($config) {
        $this->config = $config;
    }

    public function form()
    {
        return [
            'bifu365_url' => [
                'label' => '接口地址',
                'description' => '',
                'type' => 'input',
            ],
            'bifu365_mid' => [
                'label' => '商户ID',
                'description' => '',
                'type' => 'input',
            ],
            'bifu365_key' => [
                'label' => '商户密钥',
                'description' => '',
                'type' => 'input',
            ],
        ];
    }

    public function pay($order) {

        $params = [
            'mid' => $this->config['bifu365_mid'],
            'oid' => $order['trade_no'],
            'amt' => sprintf('%.2f', $order['total_amount'] / 100),
            'notify' => $order['notify_url'],
            'back' => $order['return_url'],
            'ts' => time()
        ];

        $mysign = self::GetSign($this->config['bifu365_key'], $params);
        $params["sign"] = $mysign;
        // 网关连接
        $ret_raw = self::_curlPost($this->config['bifu365_url'], $params);
        $ret = @json_decode($ret_raw, true);

        if(empty($ret['data']['paymentUrl'])) {
            abort(500, $ret["msg"]);
        }
        return [
            'type' => 1, // Redirect to url
            'data' => $ret['data']['paymentUrl'],
        ];

    }

    public function notify($params) {
        $content = file_get_contents('php://input');
        //$content = file_get_contents('php://input', 'r');

        $json_param = json_decode($content, true); //convert JSON into array
        $bifu365_sign = $json_param['sign'];
        unset($json_param['sign']);
        $sign = self::GetSign($this->config['bifu365_key'], $json_param);
        if ($sign !== $bifu365_sign) {
            echo json_encode(['status' => 400]);
            return false;
        }
        $out_trade_no = $json_param['oid'];
        $pay_trade_no=$json_param['uuid'];

        return [
            'trade_no' => $out_trade_no,
            'callback_no' => $pay_trade_no
        ];
    }

    public function GetSign($secret, $params)
    {

        $p=ksort($params);
        reset($params);

        if ($p) {
            $str = '';
            foreach ($params as $k => $val) {
                $str .= $k . '=' .  $val . '&';
            }
            $strs = rtrim($str, '&');
        }
        $strs .='&key='.$secret;

        $signature = md5($strs);

        //$params['sign'] = base64_encode($signature);
        return $signature;
    }

    private function _curlPost($url,$params=false){

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); //设置超时
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }


}
