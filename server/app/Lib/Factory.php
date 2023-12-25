<?php


namespace App\Lib;

/**
 * Class Factory
 *
 * @DOC     单例
 * @package App\Lib
 */
class Factory
{
    private static $_instance;
    private static $Factory;

    /**
     * @DOC
     * @return Factory
     */
    public static function getInstance()
    {
        if (!(self::$_instance instanceof self)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * @DOC 获取实例化对象
     *
     * @param $className
     * @param $options
     *
     * @return mixed
     */
    public static function Get($className, $options = null)
    {
        if ((!isset(self::$Factory[$className]) || !self::$Factory[$className])
            && class_exists($className)
        ) {
            self::$Factory[$className] = new $className($options);
        }

        return self::$Factory[$className];
    }
}