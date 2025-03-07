<?php
namespace Wangruyi\RedisKit;

/**
 * 基于Redis Stream结构的消息队列
 */
class StreamQueue
{
    const OFFSET_UNREAD = '>';          # 从未读的数据开始
    const OFFSET_UNCONFIRMED = '0';     # 从未确认数据第一条开始

    protected $topic;
    protected $group;
    protected $consumer;
    protected $client;

    public function __construct($topic, $client, $group=null, $consumer=null)
    {
        $this->topic = $topic;
        $this->group = $group;
        $this->consumer = $consumer;
        $this->client = $client;

        if ($this->group) {
            $this->initTopic();
            $this->initGroup();
        }
    }

    /**
     * 发布消息
     * @param string $message
     */
    public function produce($message)
    {
        $messages = ['message' => $message];
        $this->client->xAdd($this->topic, '*', $messages);
    }

    /**
     * 消费消息
     * @param int $count 消息数
     * @param null|int $block 阻塞时间，毫秒
     * @return array
     */
    public function consume($count, $block=null, $offset=self::OFFSET_UNREAD)
    {
        $streams = [$this->topic => $offset];
        $data = $this->client->xReadGroup($this->group, $this->consumer, $streams, $count, $block);

        $messages = [];
        if ($data) {
            $rows = $this->toArray($data[$this->topic]);
            $messages = $rows;
        }

        return $messages;
    }

    /**
     * 重新处理待处理的消息
     */
    public function getPending($count)
    {
        return $this->consume($count, null, self::OFFSET_UNCONFIRMED);
    }

    /**
     * 声明消费组
     */
    public function initGroup()
    {
        $this->client->xGroup('CREATE', $this->topic, $this->group, 0);
    }

    /**
     * 创建一个空队列
     */
    public function initTopic()
    {
        # redis没办法创建空队列，先发布一条消息再删除
        if (!$this->client->exists($this->topic)) {
            $messages = ['message' => ''];
            $messageId = $this->client->xAdd($this->topic, '*', $messages);
            $this->delete([$messageId]);
        }
    }

    /**
     * 确认消息已处理
     */
    public function ack($messageIds)
    {
        $this->client->xAck($this->topic, $this->group, $messageIds);
    }

    /**
     * 删除消息
     * @param $messageIds
     */
    public function delete($messageIds)
    {
        $this->client->xDel($this->topic, $messageIds);
    }

    /**
     * 根据ID取消息
     * @param $messageId
     */
    public function getMessage($messageId)
    {
        $data = $this->client->xRange($this->topic, $messageId, '+', 1); # 空代表取不到消息

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