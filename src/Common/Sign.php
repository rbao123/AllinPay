<?php


namespace bao\allinpay\Common;


class Sign
{
    protected $private_key;

    /**
     * 获取商户私钥
     * @param $private_key
     * @return string
     */
    public function getPrivateKey($private_key)
    {
                $private_key = chunk_split($private_key, 64, "\n");
        $key = "-----BEGIN RSA PRIVATE KEY-----\n" . wordwrap($private_key) . "-----END RSA PRIVATE KEY-----";
        return $key;
    }

    public function getPublicKey(){
        $public_key = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCm9OV6zH5DYH/ZnAVYHscEELdCNfNTHGuBv1nYYEY9FrOzE0/4kLl9f7Y9dkWHlc2ocDwbrFSm0Vqz0q2rJPxXUYBCQl5yW3jzuKSXif7q1yOwkFVtJXvuhf5WRy+1X5FOFoMvS7538No0RpnLzmNi3ktmiqmhpcY/1pmt20FHQQIDAQAB';
        $public_key = chunk_split($public_key, 64, "\n");
        $key = "-----BEGIN PUBLIC KEY-----\n$public_key-----END PUBLIC KEY-----\n";
        return $key;
    }


    /**
     * 获取签名字符串
     * @param $array array 请求参数
     * @param $private_key
     * @return mixed
     */
    public function getSignStr($array,$private_key)
    {
        ksort($array);
        $bufSignSrc = $this->toUrlParams($array);
        $private_key = $this->getPrivateKey($private_key);
        openssl_sign($bufSignSrc, $signature, $private_key);
        return base64_encode($signature);
    }

    /**
     * 将变量url化
     * @param $array array 请求参数
     * @return string
     */
    public function toUrlParams($array)
    {
        $buff = "";
        foreach ($array as $k => $v) {
            if ($v != "" && !is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }
        $buff = trim($buff, "&");
        return $buff;
    }

    /**
     * 验签
     * @param array $array
     * @return bool
     */
    public function validSign($array)
    {
        $sign = $array['sign'];
        unset($array['sign']);
        ksort($array);
        $bufSignSrc = $this->toUrlParams($array);
        $key = $this->getPublicKey();
        return (bool)openssl_verify($bufSignSrc,base64_decode($sign), $key );
    }
}