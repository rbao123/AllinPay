<?php


namespace bao\allinpay;




class AllinPayRun
{
    public function pay($data)
    {

        $config = $this->getConfig();
        $config['cusid'] = 1212;
        $allinpay = new AllinPay($config);
        $data['income'] = 12345646;
        $result = $allinpay->getPayInfo($data);
        return $result;
    }

    public function notify($requestData)
    {
        $config = [
            'appid' => 'xxxxx',
            'orgid' => 'xxxxxxxxxx',
        ];
        $allinpay = new AllinPay($config);
        $allinpay->notify($requestData);
    }

    public function toRefund($refundData)
    {
        $alipay = new $this->model();
        $alipay->setDire();
        $result = $alipay->toRefund($refundData);
        return $result;
    }

    public function getConfig()
    {
        $sdk = new SdkConfig();
        $config_info = $sdk->setDrive('tl_wx')->getConfig();
        $config = [
            'appid' => $config_info['appid'],
//            'cusid' => $seller->cusid,
            'to_cusid' => $config_info['mch_id'],
            'sub_appid' => $config_info['miniProgram_appid'],
            'private_key' => $config_info['key_path']
        ];
        return $config;
    }
}