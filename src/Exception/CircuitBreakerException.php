<?php

namespace Anthony\Breaker\Exception;


/**
 * 断路器触发异常
 * Class CircuitBreakerException
 * @package App\Exceptions
 */
class CircuitBreakerException extends \Exception
{
    protected $code = 7001;

    public function __construct($message = '')
    {
        $message = trans('breaker::message.reason') . $message;
        parent::__construct($message, $this->code, null);
    }
}
