<?php

namespace Wangruyi\RedisKit\Tests;

use PHPUnit\Framework\TestCase;
use Wangruyi\RedisKit\RedisLockFactory;
use Wangruyi\RedisKit\RedisLock;

/**
 * Class RedisLockFactoryTest
 */
class RedisLockFactoryTest extends TestCase
{
    protected static $config = [
        'host'     => '127.0.0.1',
        'port'     => 6379,
        'timeout'  => 5.0,
        'password' => '',
        'select'   => 0,
    ];

    public function testCreate()
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('redis extension not installed');
        }

        $lock = RedisLockFactory::create(self::$config);
        $this->assertInstanceOf(RedisLock::class, $lock);

        // 测试锁功能
        $key = 'factoryTest';
        $this->assertTrue($lock->acquire($key, 2, 0));
        $lock->release($key);
    }
}
