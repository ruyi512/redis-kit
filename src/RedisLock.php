<?php
namespace Wangruyi\RedisKit;

/**
 * 基于Redis带阻塞机制的分布式锁
 */
class RedisLock
{
    # 信号过期时间（毫秒）
    const SIGN_EXPIRES = 1000;

    # 释放锁的lua脚本，只有值相同才能释放
    const RELEASE_SCRIPT = <<<LUA
    if redis.call("get", KEYS[1]) ~= ARGV[1] then
        return 1
    else
        redis.call("del", KEYS[2])
        redis.call("lpush", KEYS[2], 1)
        redis.call("pexpire", KEYS[2], ARGV[2])
        redis.call("del", KEYS[1])
        return 0
    end
LUA;

    protected $identifier;
    protected $redis;

    public function __construct($redis, $identifier)
    {
        $this->redis = $redis;
        $this->identifier = $identifier;
    }

    /**
     * 申请一个锁
     * @param $key
     * @param int $expires
     * @params int $blocking 阻塞时间（秒），0代表不阻塞
     */
    public function acquire($key, $expires=3, $blocking=0)
    {
        $waiting = true;
        $timedOut = false;
        $result = false;

        while ($waiting) {
            $result = $this->redis->set($key . '-lock', $this->identifier, array('NX', 'EX' => $expires));
            if ($result || $timedOut || !$blocking) {
                $waiting = false;
            } else {
                $timedOut = !$this->redis->blPop($key . '-signal', $blocking);  # 取不到值，说明超时
            }
        }

        return $result;
    }

    /**
     * 释放一个锁
     * @param $key
     */
    public function release($key)
    {
        $args = [
            $key . '-lock',     # 锁key
            $key . '-signal',   # 信号key
            $this->identifier,   # 锁的value
            self::SIGN_EXPIRES
        ];
        $this->redis->eval(self::RELEASE_SCRIPT, $args, 2);
    }

}