<?php
namespace Wangruyi\RedisKit;

/**
 * redis客户端
 */
class Redis
{
    # 静态实例数组
    private static $_instances = [];

    private function __construct($name, $config)
    {
        $instance = new \Redis();

        $result = $instance->connect($config['host'], $config['port'], $config['timeout']);
        if ($result === false) {
            throw new \RedisException('redis connect error');
        }

        if ($config['password']) {
            $instance->auth($config['password']);
        }
        if (0 != $config['select']) {
            $instance->select($config['select']);
        }

        self::$_instances[$name] = $instance;
    }

    /**
     * 获取静态实例
     */
    public static function getInstance($config)
    {
        $name = md5(serialize($config));
        if (self::$_instances[$name]) {
            return self::$_instances[$name];
        }

        new self($name, $config);
    }

    /**
     * 禁止clone
     */
    private function __clone()
    {
    }

}