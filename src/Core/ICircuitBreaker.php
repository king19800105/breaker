<?php

namespace Anthony\Breaker\Core;


/**
 * Interface ICircuitBreaker
 * @package App\Components\CircuitBreaker
 */
interface ICircuitBreaker
{
    /**
     * 锁后缀
     */
    public const LOCK_SUFFIX = ':lock';

    /**
     * 断路器运行
     *
     * @param callable $handler 业务处理
     * @param callable $checker 校验结果
     * @param mixed $openStatusResponse 断路器开启状态下的返回结果
     * @return mixed
     */
    public function run(callable $handler, callable $checker, $openStatusResponse);

    /**
     * 获取记录
     *
     * @return mixed
     */
    public function fetchRecord();

    /**
     * 原子操作
     *
     * @param int $record 记录值
     * @param bool $success 执行结果
     * @return mixed
     */
    public function incRecord(int $record, bool $success = true);

    /**
     * 删除记录
     *
     * @param string $key 要删除的key
     * @return mixed
     */
    public function deleteRecord(string $key);

    /**
     * 添加记录
     *
     * @param string $key 要添加的key
     * @param int $val 值
     * @return mixed
     */
    public function addRecord(string $key, int $val);

    /**
     * 原子锁操作
     *
     * @param string $key 锁的key
     * @param int $timeout 锁超时时间
     * @param int|null $count 计数值
     * @return bool
     */
    public function lock(string $key, int $timeout = 3, int $count = null): bool;

    /**
     * 释放锁操作
     *
     * @param string $key 锁的key
     * @param bool $semaphoreType 信号量类型
     * @return bool
     */
    public function unlock(string $key, bool $semaphoreType = false): bool;
}
