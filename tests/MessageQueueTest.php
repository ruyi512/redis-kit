<?php

namespace Wangruyi\RedisKit\Tests;

use PHPUnit\Framework\TestCase;
use Wangruyi\RedisKit\Redis;
use Wangruyi\RedisKit\MessageQueue;

/**
 * Class MessageQueueTest
 */
class MessageQueueTest extends TestCase
{
    protected static $config = [
        'host'     => '127.0.0.1',
        'port'     => 6379,
        'timeout'  => 5.0,
        'password' => '',
        'select'   => 0,
    ];

    protected $redis;
    protected $queueName = 'test_stream';
    protected $group = 'test_group';
    protected $consumer = 'test_consumer';

    protected function setUp(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('redis extension not installed');
        }
        $this->redis = Redis::getInstance(self::$config);
        // 删除测试流
        $this->redis->del($this->queueName);
    }

    protected function tearDown(): void
    {
        if ($this->redis) {
            $this->redis->del($this->queueName);
        }
    }

    public function testPushAndPop()
    {
        $queue = new MessageQueue($this->redis, $this->queueName, $this->group, $this->consumer);
        $queue->init();

        $messages = ['Hello', 'World'];
        $queue->push($messages);

        $popped = $queue->pop(2, 1000);
        $this->assertCount(2, $popped);
        $this->assertEquals('Hello', $popped[0]['data']);
        $this->assertEquals('World', $popped[1]['data']);

        // 确认消息
        $ids = array_column($popped, '_id');
        $queue->ack($ids);

        // 消息已被确认，再次pop应该得不到（使用未读偏移）
        $empty = $queue->pop(2, 100);
        $this->assertEmpty($empty);
    }

    public function testPending()
    {
        $queue = new MessageQueue($this->redis, $this->queueName, $this->group, $this->consumer);
        $queue->init();

        $queue->push(['PendingMsg']);
        // 消费但不确认
        $popped = $queue->pop(1, 100);
        $this->assertCount(1, $popped);

        // 获取pending消息
        $pending = $queue->getPending(10);
        $this->assertCount(1, $pending);
        $this->assertEquals('PendingMsg', $pending[0]['data']);

        // 确认
        $queue->ack([$popped[0]['_id']]);
        $pendingAfter = $queue->getPending(10);
        $this->assertEmpty($pendingAfter);
    }

    public function testDelete()
    {
        $queue = new MessageQueue($this->redis, $this->queueName, $this->group, $this->consumer);
        $queue->init();

        $queue->push(['ToDelete']);
        $popped = $queue->pop(1, 100);
        $id = $popped[0]['_id'];
        // 删除消息
        $queue->delete([$id]);
        // 消息已删除，getMessage 应返回 null
        $msg = $queue->getMessage($id);
        $this->assertNull($msg);
    }
}
