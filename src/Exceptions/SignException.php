<?php

namespace bao\allinpay\Exceptions;




class SignException extends AllinPayException
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
