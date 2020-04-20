<?php


namespace Anthony\Breaker\Core;

use Anthony\Breaker\Exception\CircuitBreakerException;

/**
 * Class BaseCircuitBreaker
 * @package App\Components\Gateway\CircuitBreaker
 */
abstract class BaseCircuitBreaker implements ICircuitBreaker
{
    /**
     * 最大请求计数
     */
    protected const MAX_REQUEST = 99888;

    /**
     * 限制最大重试时间为30秒
     */
    protected const MAX_RETRY_TIME = 30;

    /**
     * 计量单位（失败次数/请求次数/状态更新时间/重试次数/拒绝次数）
     * 说明：所有统计次数都用一个bigint类型值做标记，每个位都表示不同都含义
     * 例：当前时间戳是1587191965，总共请求了10次，失败了6次，重试了2次，直接拒绝了3次
     * 值为：30000000000000 + 2000000000000 + 965000000000 + 100000 + 6 = 32965000100006
     * 注意：如当前时间戳是1587191965，则取后三位 965
     *
     * @var int
     */
    protected const
        FAILURE = 1, NORMAL = 10000, TS = 1000000000, RETRY_COUNT = 1000000000000, REJECTED = 10000000000000;

    /**
     * 计数单位string.
     *
     * @var string
     */
    protected const
        FAILURE_CODE = 'FAILURE', NORMAL_CODE = 'NORMAL', REJECTED_CODE = 'REJECTED', RETRY_COUNT_CODE = 'RETRY', TS_CODE = 'TS';

    /**
     * 计数单位表.
     *
     * @var array
     */
    protected const UNITS = [
        self::REJECTED_CODE    => self::REJECTED,
        self::RETRY_COUNT_CODE => self::RETRY_COUNT,
        self::TS_CODE          => self::TS,
        self::NORMAL_CODE      => self::NORMAL,
        self::FAILURE_CODE     => self::FAILURE,
    ];

    /**
     * 错误记录更新
     * 如：成功3次是：30000，失败2次是：30002
     */
    protected const COMBINED_FAILURE = self::FAILURE + self::NORMAL;

    /**
     * 记录的时间基数
     *
     * @var int
     */
    protected const TS_BASE = 1000;

    /**
     * 半开对象后缀
     */
    protected const
        HALF_OPEN_SUFFIX = ':half_open',
        MOVE_CLOSE_SUFFIX = ':move_to_close',
        TS_CHANGE_SUFFIX = ':ts_change',
        RETRY_COUNT_SUFFIX = ':retry_count';

    /**
     * 参数对象
     *
     * @var Option
     */
    protected $option;

    /**
     * 周期内总访问量
     *
     * @var int
     */
    protected $totalCount;

    /**
     * 周期内成功量统计
     *
     * @var int
     */
    protected $successCount;

    /**
     * 周期内失败量统计
     *
     * @var int
     */
    protected $failCount;

    /**
     * 断路器打开后拒绝访问的次数
     *
     * @var int
     */
    protected $rejectedCount;

    /**
     * 状态最后改变时间
     *
     * @var int
     */
    protected $statusChangeAt;

    /**
     * 断路器半开状态时，连续失败重试的次数
     * 配合延长时间使用，延长时间 = $retryErrorCount * 延时设置
     *
     * @var int
     */
    protected $retryErrorCount;

    /**
     * 原始计数
     *
     * @var int
     */
    protected $rawRecord;

    /**
     * 是否有效记录
     *
     * @var bool
     */
    protected $isEffective;

    /**
     * 半开对象
     *
     * @var BaseCircuitBreaker
     */
    protected $halfOpenInstance;

    /**
     * BaseCircuitBreaker constructor.
     *
     * @param Option $option
     * @throws CircuitBreakerException
     */
    public function __construct(Option $option)
    {
        $this->setOptions($option);
        $this->init();
    }

    /**
     * 克隆对象
     *
     * @throws CircuitBreakerException
     */
    protected function __clone()
    {
        $appId  = $this->option->getAppId() . static::HALF_OPEN_SUFFIX;
        $option = clone $this->option;
        $option
            ->setAppId($appId, false)
            ->setCycleTime($this->getAdjustedStatusTimeout())
            ->setThreshold($this->option->getHalfOpenFail())
            ->setHalfOpenStatusMove($this->option->getHalfOpenSuccess());
        $this->resetSessionData();
        $this->option = $option;
        $this->init();
    }

    /**
     * 初始化记录
     *
     * @param int|null $initRecord
     */
    protected function init(int $initRecord = null)
    {
        // 获取当前的统计记录
        $record = $initRecord ?? $this->fetchRecord() ?: 0;
        // 第一次访问时，所有属性初始化为0
        [
            static::TS_CODE          => $this->statusChangeAt,
            static::FAILURE_CODE     => $this->failCount,
            static::REJECTED_CODE    => $this->rejectedCount,
            static::NORMAL_CODE      => $this->totalCount,
            static::RETRY_COUNT_CODE => $this->retryErrorCount,
        ] = $this->parseRecord($record);
        // 保存没有解析前的原始记录
        $this->rawRecord = $record;
        // 计算成功的请求次数
        $this->successCount = $this->totalCount - $this->failCount;
        // 总请求记录大于最小样本表示有效记录
        $this->isEffective = $this->totalCount >= $this->option->getMinSample();
    }

    /**
     * 核心运行
     *
     * @param callable $handler 执行的业务回调
     * @param callable $checker 校验器
     * @param mixed $openStatusResponse 断路器打开时返回的结果
     * @return mixed
     * @throws CircuitBreakerException
     */
    public function run(callable $handler, callable $checker, $openStatusResponse)
    {
        // 校验核心参数是否正确
        $this->validate();
        // 没有打开情况下
        if (!$this->isOpen()) {
            // 获取开始时间
            $startTime = microtime(true);
            // 执行业务
            $result = $handler();
            // 计算耗时
            $consume = microtime(true) - $startTime;
            // 校验结果
            $ok = (bool)$checker($result, $consume);
            // 根据结果更新记录
            $this->updateStatus($ok);
            return $result;
        }

        // 更新拒绝次数，返回预定义结果
        $this->updateRejectedCount();
        return $openStatusResponse;
    }

    /**
     * 校验核心参数
     *
     * @throws CircuitBreakerException
     */
    protected function validate()
    {
        if ($this instanceof APCuCircuitBreaker && \PHP_SAPI === 'cli') {
            throw new CircuitBreakerException(trans('breaker::message.apcu_cli'));
        }

        if (!$this->option instanceof Option) {
            throw new CircuitBreakerException(trans('breaker::message.option'));
        }

        if (empty($this->option->getAppId())) {
            throw new CircuitBreakerException(trans('breaker::message.app_id'));
        }

        if ($this->option->getThreshold() < $this->option->getMinSample()) {
            throw new CircuitBreakerException(trans('breaker::message.threshold_less_sample'));
        }

        if ($this->option->getCycleTime() < $this->option->getOpenTimeout()) {
            throw new CircuitBreakerException(trans('breaker::message.cycle_less_timeout'));
        }
    }

    /**
     * 判断断路器是否开启
     *
     * @return bool
     */
    protected function isOpen(): bool
    {
        $isOpen = !$this->isClosed();
        if ($isOpen && $halfOpenBreaker = $this->getHalfOpenStatus()) {
            $isOpen = $this->tripStatus($halfOpenBreaker);
        }

        return $isOpen;
    }

    /**
     * 验证是否是关闭状态
     *
     * @return bool
     */
    protected function isClosed(): bool
    {
        $ret = true;
        // 记录无效或没有错误，则直接跳过
        if (!$this->isEffective || $this->failCount <= 0) {
            return $ret;
        }
        // 查看是否达到开启条件(错误计次达到阀值)，与百分比二选一，一个达到条件就开启
        $ret = $this->failCount < $this->option->getThreshold();
        // 查看设置的百分比是否到达条件
        if ($ret && $this->option->getPercent() > 0) {
            return $this->option->getPercent() > ($this->failCount / $this->totalCount);
        }

        return $ret;
    }

    /**
     * 半开状态下的状态转移处理
     *
     * @param BaseCircuitBreaker $halfOpenBreaker
     * @return bool
     */
    protected function tripStatus(self $halfOpenBreaker): bool
    {
        $isFailCondition = (function () {
            return $this->failCount > $this->option->getThreshold();
        })->call($halfOpenBreaker);
        if ($isFailCondition) {
            $this->moveToOpen();
            $this->updateRetryCount();
            $halfOpenBreaker->reset(false);
            $this->halfOpenInstance = null;
        } else {
            $isSuccessCondition = (function () {
                return $this->successCount > $this->option->getHalfOpenSuccess();
            })->call($halfOpenBreaker);
            if ($isSuccessCondition) {
                $this->moveToClosed();
                $halfOpenBreaker->reset();
                $this->halfOpenInstance = null;
            }

            return false;
        }

        return true;
    }

    /**
     * 断路器关闭
     *
     * @return bool
     */
    protected function moveToClosed()
    {
        $ret = true;
        $key = $this->option->getAppId() . static::MOVE_CLOSE_SUFFIX;
        if ($this->lock($key)) {
            $ret = $this->reset();
            $this->unlock($key);
        }

        return $ret;
    }

    /**
     * 清除状态
     *
     * @param bool $tryCreateNewEntity
     * @return bool
     */
    protected function reset(bool $tryCreateNewEntity = true)
    {
        $key = $this->option->getAppId();
        $ret = $this->deleteRecord($key) && $this->resetSessionData();
        $tryCreateNewEntity && $this->addRecord($key, 0);
        return $ret;
    }

    /**
     * 重置
     *
     * @return bool
     */
    protected function resetSessionData()
    {
        $this->rawRecord       = null;
        $this->failCount       = null;
        $this->rejectedCount   = null;
        $this->totalCount      = null;
        $this->retryErrorCount = null;
        $this->successCount    = null;
        $this->isEffective     = null;
        $this->statusChangeAt  = null;
        return true;
    }


    /**
     * 更新重试次数
     *
     * @return bool
     */
    protected function updateRetryCount(): bool
    {
        $ret = true;
        if ($this->retryErrorCount >= 9 || $this->retryErrorCount * $this->option->getLengthen() > $this->option->getCycleTime() * 0.5) {
            $lockKey = $this->option->getAppId() . static::RETRY_COUNT_SUFFIX;
            $incr    = -($this->retryErrorCount - 1);
            if ($this->lock($lockKey)) {
                $ret = $this->updateRecord(self::RETRY_COUNT * $incr);
                $this->unlock($lockKey);
            }
        } else {
            $ret = $this->updateRecord(self::RETRY_COUNT);
        }

        return $ret;
    }

    /**
     * 状态转移到开启
     *
     * @return bool
     */
    protected function moveToOpen()
    {
        return $this->updateChangedTs();
    }

    /**
     * 更新重试次数
     *
     * @return bool
     */
    protected function updateRejectedCount()
    {
        return $this->rejectedCount > self::MAX_REQUEST
            ? true
            : $this->updateRecord(self::REJECTED);
    }

    /**
     * 设置参数对象
     *
     * @param Option $option
     * @throws CircuitBreakerException
     */
    protected function setOptions(Option $option): void
    {
        if (null === $option) {
            throw new CircuitBreakerException(trans('breaker.option'));
        }

        $this->option = $option;
    }

    /**
     * 解析记录
     *
     * @param int $num
     * @return array
     */
    protected function parseRecord(int $num): array
    {
        $result = [];
        foreach (self::UNITS as $name => $unit) {
            $modulus       = $num % $unit;
            $result[$name] = ($num - $modulus) / $unit;
            $num           = $modulus;
        }

        return $result;
    }

    /**
     * 更新状态处理
     *
     * @param bool $ok 运行结果是否成功
     * @return bool
     */
    protected function updateStatus(bool $ok)
    {
        $ret = $ok ? $this->updateTotalCount() : $this->updateFailureCount();
        if ($this->halfOpenInstance) {
            $this->updateHalfOpenStatusRecord($ok);
        }

        return $ret;
    }

    /**
     * 更新半开状态记录
     *
     * @param bool $ok
     * @return bool
     */
    protected function updateHalfOpenStatusRecord(bool $ok)
    {
        return $ok
            ? $this->halfOpenInstance->updateTotalCount()
            : $this->halfOpenInstance->updateFailureCount();
    }

    /**
     * 更新总数量
     *
     * @return bool
     */
    protected function updateTotalCount(): bool
    {
        return $this->updateRecord(self::NORMAL);
    }

    /**
     * 更新失败次数
     *
     * @return bool
     */
    protected function updateFailureCount(): bool
    {
        return $this->updateRecord(self::COMBINED_FAILURE);
    }

    /**
     * 更新开启时间
     *
     * @return bool
     */
    protected function updateChangedTS(): bool
    {
        $ret = true;
        $key = $this->option->getAppId() . static::TS_CHANGE_SUFFIX;
        if ($this->lock($key)) {
            $lastChangeTS    = $this->statusChangeAt;
            $currentChangeTS = $this->getCurrentTS();
            if (0 === $lastChangeTS) {
                $result = $currentChangeTS;
            } else {
                $result = $currentChangeTS !== $lastChangeTS ? $currentChangeTS - $lastChangeTS : 0;
            }

            if ($result) {
                $this->statusChangeAt = $currentChangeTS;
                $ret                  = $this->updateRecord(static::TS * $result, false);
            }

            $this->unlock($key);
        }

        return $ret;
    }

    /**
     * 获取当前时间戳标记
     *
     * @param int $base
     * @return int
     */
    protected function getCurrentTS(int $base = self::TS_BASE): int
    {
        return \time() % $base;
    }

    /**
     * 更新记录
     *
     * @param int $incr 更新的值
     * @param bool $updateSession 是否需要重新分配属性
     * @return bool
     */
    protected function updateRecord(int $incr, bool $updateSession = true): bool
    {
        $success = true;
        if ($this->totalCount > self::MAX_REQUEST || $this->failCount > self::MAX_REQUEST / 10) {
            return true;
        }

        $record = $this->incRecord($incr, $success);
        if ($success) {
            $this->rawRecord = $record;
            $updateSession && $this->init($record);
        }

        return $success;
    }

    /**
     * 获取半开状态对象
     *
     * @return $this|null
     */
    protected function getHalfOpenStatus(): ?self
    {
        $ret = null;
        if ($this->halfOpenInstance instanceof self) {
            return $this->halfOpenInstance;
        }

        if (0 === $this->statusChangeAt) {
            $this->updateChangedTS();
            return $ret;
        }

        // 查看当前时间是否进入半开状态
        if ($this->getStatusChangedTSDiff($this->getCurrentTs()) >= $this->getAdjustedStatusTimeout()) {
            return $this->halfOpenInstance = clone $this;
        }

        return $ret;
    }

    /**
     * 时间比较，是否进入半开状态
     *
     * @param $ts
     * @param int $base
     * @return int
     */
    protected function getStatusChangedTSDiff($ts, int $base = self::TS_BASE): int
    {
        if ($ts === $this->statusChangeAt) {
            return 0;
        }

        return $ts > $this->statusChangeAt
            ? ($ts - $this->statusChangeAt)
            : ($ts + $base - $this->statusChangeAt);
    }

    /**
     * 自定调整时间
     *
     * @return int
     */
    protected function getAdjustedStatusTimeout()
    {
        $retryTime = (int)($this->retryErrorCount * $this->option->getLengthen() + $this->option->getOpenTimeout());
        if ($retryTime > static::MAX_RETRY_TIME) {
            $retryTime = static::MAX_RETRY_TIME;
        }

        return $this->retryErrorCount
            ? $retryTime
            : $this->option->getOpenTimeout();
    }
}
