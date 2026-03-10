<?php

namespace Wangruyi\RedisKit\Tests;

use PHPUnit\Framework\TestCase;
use Wangruyi\RedisKit\Redis;
use Wangruyi\RedisKit\RedisLock;

/**
 * Class RedisLockTest
 */
class RedisLockTest extends TestCase
{
    protected static $config = [
        'host'     => '127.0.0.1',
        'port'     => 6379,
        'timeout'  => 5.0,
        'password' => '',
        'select'   => 0,
    ];

    protected $redis;

    protected function setUp(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('redis extension not installed');
        }
        $this->redis = Redis::getInstance(self::$config);
        // 清理可能遗留的键
        $this->redis->del('test-lock', 'test-signal');
    }

    protected function tearDown(): void
    {
        if ($this->redis) {
            $this->redis->del('test-lock', 'test-signal');
        }
    }

    public function testAcquireAndRelease()
    {
        $identifier = uniqid('', true);
        $lock = new RedisLock($this->redis, $identifier);

        $this->assertTrue($lock->acquire('test', 3, 0));
        // 再次获取应失败（非阻塞）
        $lock2 = new RedisLock($this->redis, $identifier . '2');
        $this->assertFalse($lock2->acquire('test', 3, 0));

        $lock->release('test');
        // 释放后可以再次获取
        $this->assertTrue($lock2->acquire('test', 3, 0));
        $lock2->release('test');
    }

    public function testBlocking()
    {
        $identifier = uniqid('', true);
        $lock = new RedisLock($this->redis, $identifier);

        // 立即获得锁
        $this->assertTrue($lock->acquire('testBlock', 2, 0));
        // 另一个客户端在阻塞时间内等待
        $start = microtime(true);
        $lock2 = new RedisLock($this->redis, $identifier . '2');
        // 因为锁被占用，blocking=1秒，期望在锁释放后获得
        // 但我们需要在一个单独的流程中释放锁，这里我们直接测试超时情况？
        // 为了简化，我们测试在锁存在时，阻塞调用会等待直到超时（因为我们不会释放锁）
        // 但acquire方法在超时后会返回false。由于我们占用锁2秒，blocking设为1，它应等待1秒后超时返回false。
        $result = $lock2->acquire('testBlock', 2, 1);
        $end = microtime(true);
        $this->assertFalse($result);
        // 等待时间应该大约1秒
        $this->assertGreaterThanOrEqual(0.9, $end - $start);
        $this->assertLessThan(2.0, $end - $start);
        $lock->release('testBlock');
    }
}
