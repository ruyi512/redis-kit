<?php
namespace Wangruyi\RedisKit;

/**
 * 基于Redis Stream结构的消息队列
 */
class MessageQueue
{
    const OFFSET_UNREAD = '>';          # 从未读的数据开始
    const OFFSET_UNCONFIRMED = '0';     # 从未确认数据第一条开始

    protected $queueName;
    protected $group;
    protected $consumer;
    protected $client;

    public function __construct($queueName, $client, $group=null, $consumer=null)
    {
        $this->queueName = $queueName;
        $this->group = $group;
        $this->consumer = $consumer;
        $this->client = $client;

        if ($this->group) {
            $this->init();
        }
    }

    /**
     * 入队
     * @param array $messages
     */
    public function push($messages)
    {
        if (count($messages) == 1) {
            $content = ['message' => $messages[0]];
            $this->client->xAdd($this->queueName, '*', $content);
        }

        $pipline = $this->client->pipeline();
        foreach ($messages as $message) {
            $content = ['message' => $message];
            $this->client->xAdd($this->queueName, '*', $content);
        }
        $pipline->exec();
    }

    /**
     * 出队
     * @param int $count 消息数
     * @param null|int $block 阻塞时间，毫秒
     * @return array
     */
    public function pop($count, $block=null, $offset=self::OFFSET_UNREAD)
    {
        $streams = [$this->queueName => $offset];
        $data = $this->client->xReadGroup($this->group, $this->consumer, $streams, $count, $block);

        $messages = [];
        if ($data) {
            $rows = $this->toArray($data[$this->queueName]);
            $messages = $rows;
        }

        return $messages;
    }

    /**
     * 重新处理待处理的消息
     */
    public function getPending($count)
    {
        return $this->pop($count, null, self::OFFSET_UNCONFIRMED);
    }

    /**
     * 声明消费组
     */
    public function initGroup()
    {
        if ($this->client->xPending($this->queueName, $this->group) === false) {
            $this->client->xGroup('CREATE', $this->queueName, $this->group, 0);
        }
    }

    /**
     * 如果队列不存在，创建一个空队列并声明一个消费组
     */
    public function init()
    {
        # redis没办法创建空队列，先发布一条消息再删除
        if (!$this->client->exists($this->queueName)) {
            $messages = ['message' => ''];
            $messageId = $this->client->xAdd($this->queueName, '*', $messages);
            $this->delete([$messageId]);
        }

        $this->initGroup();
    }

    /**
     * 确认消息已处理
     */
    public function ack($messageIds)
    {
        $this->client->xAck($this->queueName, $this->group, $messageIds);
    }

    /**
     * 删除消息
     * @param $messageIds
     */
    public function delete($messageIds)
    {
        $this->client->xDel($this->queueName, $messageIds);
    }

    /**
     * 根据ID取消息
     * @param $messageId
     */
    public function getMessage($messageId)
    {
        $data = $this->client->xRange($this->queueName, $messageId, '+', 1); # 空代表取不到消息

        $message = null;
        if ($data) {
            $rows = $this->toArray($data);
            $message = $rows[0];
        }

        return $message;
    }

    /**
     * 格式化消息
     * @param $messages
     */
    protected function toArray($messages)
    {
        $rows = [];
        foreach ($messages as $messageId => $message) {
            $rows[] = [
                '_id' => $messageId,
                'data' => $message['message'] ?? null # 消息被删除$message是空
            ];
        }
        return $rows;
    }
}