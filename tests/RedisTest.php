<?php

namespace Wangruyi\RedisKit\Tests;

use PHPUnit\Framework\TestCase;
use Wangruyi\RedisKit\Redis;

/**
 * Class RedisTest
 */
class RedisTest extends TestCase
{
    protected static $config = [
        'host'     => '127.0.0.1',
        'port'     => 6379,
        'timeout'  => 5.0,
        'password' => '',
        'select'   => 0,
    ];

    public function testGetInstance()
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('redis extension not installed');
        }

        $redis = Redis::getInstance(self::$config);
        $this->assertInstanceOf(\Redis::class, $redis);

        // test connectivity
        $this->assertTrue($redis->set('test_key', 'value'));
        $this->assertEquals('value', $redis->get('test_key'));
        $redis->del('test_key');
    }

    public function testSingleton()
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('redis extension not installed');
        }

        $instance1 = Redis::getInstance(self::$config);
        $instance2 = Redis::getInstance(self::$config);
        $this->assertSame($instance1, $instance2);
    }

    public function testDifferentConfig()
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('redis extension not installed');
        }

        $config1 = self::$config;
        $config2 = array_merge(self::$config, ['select' => 1]);
        $instance1 = Redis::getInstance($config1);
        $instance2 = Redis::getInstance($config2);
        $this->assertNotSame($instance1, $instance2);
    }
}
