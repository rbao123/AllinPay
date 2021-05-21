<?php


namespace bao\allinpay;



use  bao\allinpay\Common\CurlTool;
use  bao\allinpay\Common\Sign;
use  bao\allinpay\Exceptions\AllinPayException;
use  bao\allinpay\Exceptions\SignVerifyException;

class AllinPay
{
    protected $url = 'https://vsp.allinpay.com/apiweb/unitorder/pay';//接口地址
//    protected $url = 'https://test.allinpaygd.com/apiweb/unitorder/pay';//测试地址

    private $signModel;
    private $appid;
    private $cusid;
    private $to_cusid;
    private $private_key;
    private $version = 11;
    private $config = [];
    private $do = 10;

    protected $pay_type = [
        'wechat_jsapi' => ['return_type' => 'jump', 'pay_type' => 'W02'],
        'wechat_scan_code' => ['return_type' => 'h5', 'pay_type' => 'W01'],
        'miniProgram' => ['return_type' => 'callWechatPayInfo', 'pay_type' => 'W06'],
        'alipay_scan_code' => ['return_type' => 'h5', 'pay_type' => 'A01'],
        'alipay_jsapi' => ['return_type' => 'jump', 'pay_type' => 'A02'],
        'app' => ['return_type' => 'app', 'pay_type' => 'A02'],
        'qq_scan_code' => ['return_type' => 'h5', 'pay_type' => 'Q01'],
        'qq_jsapi' => ['return_type' => 'jump', 'pay_type' => 'Q02'],
        'unionpay_scan_code' => ['return_type' => 'h5', 'pay_type' => 'U01'],
        'unionpay_jsapi' => ['return_type' => 'jump', 'pay_type' => 'U02'],
    ];


    public function __construct($config)
    {
        $this->signModel = new Sign();
        $this->config = $config;
        $this->appid = $config['appid'];
        $this->cusid = $config['cusid'];
        $this->private_key = $config['private_key'];
        $this->to_cusid = $config['to_cusid'];
    }

    /**
     * 获取支付类型
     * @param $payData
     * @return array
     * @throws AllinPayException
     */
    public function getPayInfo($payData)
    {
        if (isset($this->pay_type[$payData['method']])) {
            $data = $this->createdPay($payData, $this->pay_type[$payData['method']]['pay_type']);
            $returnType = $this->pay_type[$payData['method']]['return_type'];
        } else {
            throw new AllInPayException('支付方式不存在', 401);
        }
        return ['type' => $returnType, 'data' => $data];
    }

    /**
     * 拼接支付信息
     * @param $payData array 支付数据
     * @param $paytype string 支付类型
     * @return array
     */
    public function createdPay($payData, $paytype)
    {
        $param = [
            'trxamt' => $payData['fee'] * 100,
            'body' => '支付单号:' . $payData['payment_sn'],
            'reqsn' => $payData['payment_sn'],
            'paytype' => $paytype,
            'acct' => $payData['openid'],
            'asinfo' => $this->to_cusid . ':02:' . $payData['income'],
        ];
        return $this->request($param);
    }


    /**
     * 请求通联API
     * @param $service
     * @param $method
     * @param $param
     * @return mixed
     */
    public function request($param)
    {
        $postField = $this->generatePostField($param);
        $result = CurlTool::https_post($this->url, $postField);
        $result = json_decode($result, 1);

        if ($result['retcode'] == 'FAIL') {
            throw new AllInPayException('通联下单失败', 500);
        }
        if ($result['trxstatus'] != '0000') {
            throw new AllInPayException('通联下单失败', 500);
        }
        return json_decode($result['payinfo'], true);
    }

    /**
     * 添加请求参数
     * @param $param
     * @return string
     */
    public function generatePostField($param)
    {
        $randomstr = time();
        $notify_url = 'http://' . $_SERVER['HTTP_HOST'] . '/common/notify/' . $this->do;
        $param['cusid'] = $this->cusid;
        $param['orgid'] = $this->to_cusid;
        $param['appid'] = $this->appid;
        $param['sub_appid'] = $this->config['sub_appid'];
        $param['version'] = $this->version;
        $param['randomstr'] = $randomstr;
        $param['notify_url'] = $notify_url;
        $param['signtype'] = 'RSA';
        $param['sign'] = $this->signModel->getSignStr($param, $this->private_key);
        return $param;
    }

    /**
     * 将请求数据解析成通联所需格式
     * @param array $params 请求参数
     * @return string
     */
    private function paramsToString($params)
    {
        $postField = '';
        foreach ($params as $k => $v) {
            $postField .= $k . '=' . urlencode($v) . '&';
        }
        $postField = trim($postField, "&");
        return $postField;
    }

    public function notify($data)
    {
        //验签
        $this->checkResult($data);
        //状态码对应信息
        $trxstatusArr = [
            2008 => "交易处理中,请查询交易,如果是实时交易(例如刷卡支付,交易撤销,退货),建议每隔一段时间(10秒)查询交易",
            1001 => "交易不存在",
            2000 => "交易处理中,请查询交易,如果是实时交易(例如刷卡支付,交易撤销,退货),建议每隔一段时间(10秒)查询交易",
            3 => "开头的错误码代表交易失败",
            3888 => "流水号重复",
            3099 => "渠道商户错误",
            3014 => "交易金额小于应收手续费",
            3031 => "校验实名信息失败",
            3088 => "交易未支付(在查询时间区间内未成功支付,如已影响资金24小时内会做差错退款处理)",
            3089 => "撤销异常,如已影响资金24小时内会做差错退款处理",
            3999 => "其他错误",
        ];
        $trxstatus = $data['trxstatus'];
        if ($trxstatus == "0000") {
            if (!in_array($data['trxcode'], ['VSP501', 'VSP511'])) {
                throw new AllInPayException('类型错误', $trxstatus);
            }
            $orderPayment = new PaymentOrder();
            $orderPayment->notify([
                'order_sn' => trim($data['cusorderid']),
                'fee' => trim($data['trxamt'] / 100),
                'transaction_id' => trim($data['chnltrxid']),
                'total_fee' => trim($data['trxamt'] / 100),
                'pay_style' => $data['trxcode'] == 'VSP501' ? 2 : ($data['trxcode'] == 'VSP511' ? 1 : 0),
            ]);
        } else {
            $msg = !!$_POST['errmsg'] ? $_POST['errmsg'] : $trxstatusArr[$trxstatus] ?? '未知';
            throw new AllInPayException($msg, $trxstatus);
        }
    }


    /**
     * 检查请求结果
     * @param array $result 请求结果
     * @throws
     */
    private function checkResult($result)
    {
        if (!$result) {
            throw new AllInPayException('通联请求失败', 500);
        }
        if (!$this->signModel->validSign($result)) {
            throw new SignVerifyException('通联验签失败', 20019);
        }

        if ($result['status'] == "error") {
            throw new AllInPayException($result['message'], $result['errorCode']);
        }
    }
}