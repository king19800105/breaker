<?php

namespace Anthony\Breaker\Core;

use Illuminate\Support\Facades\Redis;

class RedisCircuitBreaker extends BaseCircuitBreaker
{
    public function fetchRecord()
    {
        return Redis::get($this->option->getAppId());
    }

    public function incRecord(int $record, $success = true)
    {
        $pip = Redis::pipeline();
        $pip->incrBy($this->option->getAppId(), $record);
        $pip->expire($this->option->getAppId(), $this->option->getCycleTime());
        $res = $pip->exec();
        return $res[0];
    }

    public function deleteRecord(string $key)
    {
        return (bool)Redis::del($key);
    }

    public function addRecord(string $key, $val)
    {
        return Redis::set($key, $val, 'EX', $this->option->getCycleTime(), 'NX');
    }

    public function lock(string $key, int $timeout = 3, int $count = null): bool
    {
        if ($count) {
            $pip = Redis::pipeline();
            $pip->incr($key);
            $pip->expire($key, $timeout);
            $res = $pip->exec();
            return $count > $res[0];
        }

        return Redis::set($key . static::LOCK_SUFFIX, 1, 'EX', $timeout, 'NX');
    }

    public function unlock(string $key, bool $semaphoreType = false): bool
    {
        if ($semaphoreType) {
            $exists = Redis::exists($key);
            if (!$exists) {
                return false;
            }

            $pip = Redis::pipeline();
            $pip->decr($key);
            $pip->expire($key, 1);
            $pip->exec();

            return true;
        }

        Redis::del($key . static::LOCK_SUFFIX);
        return true;
    }
}
