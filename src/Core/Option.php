<?php

namespace Anthony\Breaker\Core;

use Anthony\Breaker\Exception\CircuitBreakerException;
/**
 * Class Options
 * @package App\Components\CircuitBreaker
 * @method string getAppId();
 * @method int getCycleTime()
 * @method int getOpenTimeout()
 * @method int getThreshold()
 * @method int getPercent()
 * @method int getMinSample()
 * @method int getLengthen()
 * @method int getHalfOpenSuccess()
 * @method int getHalfOpenFail()
 */
class Option
{
    /**
     * 参数限定
     */
    protected const
        MIN_CYCLE_TIME = 5, MAX_CYCLE_TIME = 200,
        MIN_THRESHOLD = 1, MAX_THRESHOLD = 4999,
        MIN_PERCENT = 0.05, MAX_PERCENT = 1.00,
        MIN_OPEN_TIMEOUT = 5, MAX_OPEN_TIMEOUT = 60,
        MIN_SAMPLE = 1, MAX_SAMPLE = 1000,
        MIN_LENGTHEN = 0, MAX_LENGTHEN = 10,
        MIN_HALF_OPEN_COUNT = 1, MAX_HALF_OPEN_COUNT = 1000;

    /**
     * 断路器名称前缀
     */
    protected const NAME_PREFIX = 'circuit_breaker:';

    /**
     * 断路器应用ID
     *
     * @var string
     */
    protected $appId;

    /**
     * 探测一个轮次的时间周期
     *
     * @var int
     */
    protected $cycleTime = 30;

    /**
     * 断路器开启时间
     *
     * @var int
     */
    protected $openTimeout = 8;

    /**
     * 周期内打开断路器的伐值
     *
     * @var int
     */
    protected $threshold = 10;

    /**
     * 请求失败百分比设置
     *
     * @var float
     */
    protected $percent = 0.9;

    /**
     * 请求的最小样本
     * 当请求次数大于 $sample 时，统计才有意义
     *
     * @var int
     */
    protected $minSample = 5;

    /**
     * 当断路器连续多次打开后，叠加记时
     *
     * @var int
     */
    protected $lengthen = 0;

    /**
     * 半开状态下成功设置
     * 连续成功N次后，状态变更为关闭
     *
     * @var int
     */
    protected $halfOpenSuccess = 2;

    /**
     * 半开状态下失败设置
     * 连续失败N次之后，状态继续开启
     *
     * @var int
     */
    protected $halfOpenFail = 1;

    /**
     * 处理get方法
     *
     * @param $name
     * @param $args
     * @return mixed
     */
    public function __call($name, $args)
    {
        if (0 === \strpos($name, 'get')) {
            $attr = \lcfirst(\substr($name, 3));
            return $this->{$attr};
        }

        return null;
    }

    public function __clone()
    {
        $this->reset();
    }

    /**
     * 设置应用名称
     *
     * @param string $name
     * @param bool $addPrefix
     * @return $this
     */
    public function setAppId(string $name, bool $addPrefix = true): self
    {
        $this->appId = $addPrefix ? static::NAME_PREFIX . $name : $name;
        return $this;
    }

    /**
     * 设置探测周期
     *
     * @param int $cycle
     * @return $this
     * @throws CircuitBreakerException
     */
    public function setCycleTime(int $cycle): self
    {
        if ($cycle < static::MIN_CYCLE_TIME || $cycle > static::MAX_CYCLE_TIME) {
            throw new CircuitBreakerException(trans('breaker.cycle_time', ['min' => static::MIN_CYCLE_TIME, 'max' => static::MAX_CYCLE_TIME]));
        }

        $this->cycleTime = $cycle;
        return $this;
    }

    /**
     * 断路器超时时间设置
     *
     * @param int $timeout
     * @return $this
     * @throws CircuitBreakerException
     */
    public function setOpenTimeout(int $timeout): self
    {
        if ($timeout < static::MIN_OPEN_TIMEOUT || $timeout > static::MAX_OPEN_TIMEOUT) {
            throw new CircuitBreakerException(trans('breaker.open_timeout', ['min' => static::MIN_OPEN_TIMEOUT, 'max' => static::MAX_OPEN_TIMEOUT]));
        }

        $this->openTimeout = $timeout;
        return $this;
    }

    /**
     * 设置触发断路器打开的阀值
     *
     * @param int $cnt
     * @return $this
     * @throws CircuitBreakerException
     */
    public function setThreshold(int $cnt): self
    {
        if ($cnt < static::MIN_THRESHOLD || $cnt > static::MAX_THRESHOLD) {
            throw new CircuitBreakerException(trans('breaker.threshold', ['min' => static::MIN_THRESHOLD, 'max' => static::MAX_THRESHOLD]));
        }

        $this->threshold = $cnt;
        return $this;
    }

    /**
     * 设置触发百分比阀值
     * 和 $threshold 一起构成打开断路器的阀值
     *
     * @param float $percent
     * @return $this
     * @throws CircuitBreakerException
     */
    public function setPercent(float $percent): self
    {
        if ($percent < static::MIN_PERCENT || $percent > static::MAX_PERCENT) {
            throw new CircuitBreakerException(trans('breaker.percent', ['min' => static::MIN_PERCENT, 'max' => static::MAX_PERCENT]));
        }

        $this->percent = $percent;
        return $this;
    }

    /**
     * 最小样本设置
     *
     * @param int $sample
     * @return $this
     * @throws CircuitBreakerException
     */
    public function setMinSample(int $sample)
    {
        if ($sample < static::MIN_SAMPLE || $sample > static::MAX_SAMPLE) {
            throw new CircuitBreakerException(trans('breaker.min_sample', ['min' => static::MIN_SAMPLE, 'max' => static::MAX_SAMPLE]));
        }

        $this->minSample = $sample;
        return $this;
    }

    /**
     * 断路器连续打开后，延长每次时间
     *
     * @param int $lengthen
     * @return $this
     * @throws CircuitBreakerException
     */
    public function setLengthen(int $lengthen): self
    {
        if ($lengthen < static::MIN_LENGTHEN || $lengthen > static::MAX_LENGTHEN) {
            throw new CircuitBreakerException(trans('breaker.lengthen', ['min' => static::MIN_LENGTHEN, 'max' => static::MAX_LENGTHEN]));
        }

        $this->lengthen = $lengthen;
        return $this;
    }

    /**
     * 半开状态下，请求成功或失败后大状态转移设置
     *
     * @param int $success
     * @param int $fail
     * @return $this
     * @throws CircuitBreakerException
     */
    public function setHalfOpenStatusMove(int $success, int $fail = 1): self
    {
        if ($success < static::MIN_HALF_OPEN_COUNT || $success > static::MAX_HALF_OPEN_COUNT) {
            throw new CircuitBreakerException(trans('breaker.op_success', ['min' => static::MIN_HALF_OPEN_COUNT, 'max' => static::MAX_HALF_OPEN_COUNT]));
        }

        if ($fail < static::MIN_HALF_OPEN_COUNT || $fail > static::MAX_HALF_OPEN_COUNT) {
            throw new CircuitBreakerException(trans('breaker.op_fail', ['min' => static::MIN_HALF_OPEN_COUNT, 'max' => static::MAX_HALF_OPEN_COUNT]));
        }

        $this->halfOpenSuccess = $success;
        $this->halfOpenFail    = $fail;
        return $this;
    }

    /**
     * 重置
     */
    protected function reset()
    {
        $this->appId           = '';
        $this->cycleTime       = 0;
        $this->openTimeout     = 0;
        $this->threshold       = 0;
        $this->percent         = 0.00;
        $this->minSample       = 0;
        $this->lengthen        = 0;
        $this->halfOpenSuccess = 0;
        $this->halfOpenFail    = 0;
    }
}
