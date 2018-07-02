<?php
/**
 * Dida Framework  -- A Rapid Development Framework
 * Copyright (c) Zeupin LLC. (http://zeupin.com)
 *
 * Licensed under The MIT License.
 * Redistributions of files must retain the above copyright notice.
 */

namespace Dida\EPayments;

class SmartPay
{
    const VERSION = '20180702';

    static $api_url = 'https://www.kiwifast.com/api/v1/info/smartpay';

    private $merchant_id = null;

    private $sign_key = null;


    public function __construct($merchant_id, $sign_key)
    {
        $this->merchant_id = $merchant_id;
        $this->sign_key = $sign_key;
    }


    public function createMiniAppPay(array $input)
    {
        $fields = [
            'merchant_id'      => [true, '商户ID'],
            'increment_id'     => [true, '订单号'],
            'sub_appid'        => [true, '小程序APPID'],
            'sub_openid'       => [true, '用户的openid'],
            'grandtotal'       => [true, '订单金额'],
            'currency'         => [true, '币种'],
            'valid_mins'       => [false, '有效分钟'],
            'payment_channels' => [true, '支付通道'],
            'notify_url'       => [true, '通知链接'],
            'subject'          => [false, '交易标题'],
            'describe'         => [true, '交易描述'],
            'nonce_str'        => [true, '随机字符串'],
            'service'          => [true, '请求服务'],
        ];

        $presets = [
            'merchant_id' => $this->merchant_id,
            'service'     => 'create_miniapp_pay',
            'nonce_str'   => $this->randomString(16),
        ];

        $temp = array_merge($input, $presets);

        $missing = [];
        foreach ($fields as $key => $rule) {
            if ($rule[0] == true && !array_key_exists($key, $temp)) {
                $missing[] = $key;
            }
        }
        if ($missing) {
            return [1, "缺少必填项" . implode(',', $missing), null];
        }

        $querystring = $this->makeSignedQueryString($temp);
        \Dida\Log\Log::write($querystring);

        $curl = new \Dida\CURL\CURL();
        $result = $curl->request([
            'url'    => self::$api_url,
            'method' => 'GET',
            'query'  => $querystring
        ]);
        list($code, $msg, $json) = $result;

        if ($code !== 0) {
            return [2, "createMiniAppPay申请失败", null];
        }

        $data = json_decode($json, true);
        \Dida\Log\Log::write($data);
        if ($data === null) {
            return [3, "createMiniAppPay收到非法应答", null];
        }

        if ($data['code'] === 0) {
            return [0, null, $data];
        } else {
            return [4, "createMiniAppPay拒绝:{$data['message']}", $data];
        }
    }


    protected function sign(array $data)
    {
        unset($data['sign_type'], $data['signature']);

        ksort($data);

        $sign_str = [];
        foreach ($data as $k => $v) {
            $sign_str[] = "$k=$v";
        }
        $sign_str = implode('&', $sign_str);

        $sign_str = $sign_str . $this->sign_key;

        $signature = md5($sign_str);

        return $signature;
    }


    protected function makeSignedQueryString(array $data)
    {
        unset($data['sign_type'], $data['signature']);

        $signature = $this->sign($data);

        $final = [];
        foreach ($data as $k => $v) {
            $final[] = "$k=" . urlencode($v);
        }
        $final[] = "signature=$signature";
        $final[] = "sign_type=MD5";
        $final = implode('&', $final);

        return $final;
    }


    public function checkSign(array $data)
    {
        if (!array_key_exists('signature', $data)) {
            return false;
        }

        $origin_signature = $data["signature"];

        unset($data['sign_type'], $data['signature']);

        $signature = $this->sign($data);

        if ($signature == $origin_signature) {
            return true;
        } else {
            return false;
        }
    }


    protected function randomString($num = 32, $set = null)
    {
        if (!$set) {
            $set = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        }
        $len = strlen($set);
        $r = [];
        for ($i = 0; $i < $num; $i++) {
            $r[] = substr($set, mt_rand(0, $len - 1), 1);
        }
        return implode('', $r);
    }
}
