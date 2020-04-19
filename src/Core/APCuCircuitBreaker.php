<?php


namespace Anthony\Breaker\Core;

/**
 * Class APCuCircuitBreaker
 * @package App\Components\CircuitBreaker
 */
class APCuCircuitBreaker extends BaseCircuitBreaker
{
    /**
     * 获取记录
     *y
     * @return int
     */
    public function fetchRecord()
    {
        return \apcu_fetch($this->option->getAppId(), $success);
    }

    /**
     * 自增操作
     *
     * @param int $record
     * @param $success
     * @return false|int
     */
    public function incRecord(int $record, bool $success = true)
    {
        return \apcu_inc($this->option->getAppId(), $record, $success, $this->option->getCycleTime());
    }

    /**
     * 加锁
     *
     * @param string $key
     * @param int $timeout
     * @param int $count
     *
     * @return bool
     */
    public function lock(string $key, int $timeout = 3, int $count = null): bool
    {
        return $count ? $count > \apcu_inc($key, 1, $success, $timeout) : \apcu_add($key . static::LOCK_SUFFIX, 1, $timeout);
    }

    /**
     * 解锁
     *
     * @param string $key
     * @param bool $semaphoreType
     *
     * @return bool
     */
    public function unlock(string $key, bool $semaphoreType = false): bool
    {
        return $semaphoreType ? \apcu_exists($key) && \apcu_dec($key, 1, $success, 1) : \apcu_delete($key . static::LOCK_SUFFIX);
    }

    /**
     * 删除记录
     *
     * @param string $key
     * @return bool|mixed|string[]
     */
    public function deleteRecord(string $key)
    {
        return \apcu_delete($key);
    }

    /**
     * 添加记录
     *
     * @param string $key
     * @param int $val
     * @return array|bool|mixed
     */
    public function addRecord(string $key, $val)
    {
        return \apcu_add($key, $val, $this->option->getCycleTime());
    }
}
