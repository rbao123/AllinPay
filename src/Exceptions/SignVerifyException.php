<?php


namespace bao\allinpay\Exceptions;


class SignVerifyException extends AllInPayException
{
    protected $error;

    public function __construct($message = "")
    {
        $this->error =$message;
        parent::__construct();

    }

    public function render($request){
        return errorMessage($this->error);

    }
}
