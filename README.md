# RedisKit - PHP Redis 工具包

一个简单易用的 PHP Redis 组件库，提供 Redis 客户端连接、分布式锁、基于 Stream 的消息队列等功能。

## 功能特性

- **Redis 客户端**：单例连接管理，支持多配置实例
- **分布式锁（RedisLock）**：基于 Redis 的带阻塞机制的分布式锁，支持自动过期与信号唤醒
- **消息队列（MessageQueue）**：基于 Redis Stream 结构，支持消费者组、未读/待处理消息重放、消息确认与删除
- **锁工厂（RedisLockFactory）**：便捷创建分布式锁实例

## 安装

通过 Composer 安装：

```bash
composer require wangruyi/redis-kit
```

## 使用示例

### 1. Redis 客户端

```php
use Wangruyi\RedisKit\Redis;

$config = [
    'host'     => '127.0.0.1',
    'port'     => 6379,
    'timeout'  => 5.0,
    'password' => '', // 可选
    'select'   => 0,  // 可选数据库编号
];

$redis = Redis::getInstance($config);

$redis->set('key', 'value');
echo $redis->get('key'); // value
```

### 2. 分布式锁（RedisLock）

通过 `RedisLockFactory` 创建锁实例：

```php
use Wangruyi\RedisKit\RedisLockFactory;

$lock = RedisLockFactory::create($config); // $config 同上

// 申请锁（键名、过期时间、阻塞时间）
if ($lock->acquire('myLock', 10, 5)) {
    // 成功获得锁，执行临界区代码
    echo 'Lock acquired';
    // ...
    $lock->release('myLock'); // 释放锁
} else {
    echo 'Failed to acquire lock';
}
```

直接使用 `RedisLock`：

```php
use Wangruyi\RedisKit\Redis;
use Wangruyi\RedisKit\RedisLock;

$redis = Redis::getInstance($config);
$identifier = uniqid('', true); // 唯一标识
$lock = new RedisLock($redis, $identifier);

// 同样调用 acquire / release
```

### 3. 消息队列（MessageQueue）

```php
use Wangruyi\RedisKit\Redis;
use Wangruyi\RedisKit\MessageQueue;

$redis = Redis::getInstance($config);
$queue = new MessageQueue($redis, 'myStream', 'myGroup', 'consumer1');

// 初始化队列（如果不存在会创建）
$queue->init();

// 生产消息
$queue->push(['Hello', 'World']);

// 消费消息（最多2条，阻塞1000毫秒）
$messages = $queue->pop(2, 1000);
foreach ($messages as $msg) {
    echo $msg['_id'] . ': ' . $msg['data'] . PHP_EOL;
    // 确认消息
    $queue->ack([$msg['_id']]);
}

// 重新处理待处理的消息（pending）
$pending = $queue->getPending(10);
// ...
```
