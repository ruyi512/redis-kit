<?php
namespace Wangruyi\RedisKit;

/**
 * redis分布锁对象的创建工厂
 */
class RedisLockFactory
{

    /**
     * 创建一个锁对象
     * @param $config
     * @return RedisLock
     */
    public static function create($config)
    {
        $redis = Redis::getInstance($config);
        $identifier = uniqid('', true);

        return new RedisLock($redis, $identifier);
    }
}