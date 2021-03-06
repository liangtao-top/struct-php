<?php
declare(strict_types=1);
// +----------------------------------------------------------------------
// | CodeEngine
// +----------------------------------------------------------------------
// | Copyright 艾邦
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: TaoGe <liangtao.gz@foxmail.com>
// +----------------------------------------------------------------------
// | Version: 2.0 2021/5/10 9:38
// +----------------------------------------------------------------------

namespace top\liangtao\struct;

use Error;
use ReflectionEnum;
use Throwable;
use ArrayAccess;
use PhpEnum\Enum;
use JsonSerializable;
use ReflectionClass;
use ReflectionException;
use JetBrains\PhpStorm\Internal\LanguageLevelTypeAware;

/**
 * 结构体抽象类
 */
abstract class Struct implements JsonSerializable, ArrayAccess
{

    /**
     * Struct constructor.
     * @param array|null $data
     */
    public function __construct(array $data = null)
    {
        if (!is_null($data)) {
            $this->mergeFromArray($data);
        }
    }

    /**
     * 从数组合并到当前类
     * @param array $data
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2022/6/28 15:14
     */
    public function mergeFromArray(array $data): void
    {
        $ref = new ReflectionClass($this);
        foreach ($data as $key => $value) {
            if (!$ref->hasProperty($key)) {
                $key = Utils::parseName($key, 1, false);
            }
            if ($ref->hasProperty($key)) {
                $property = $ref->getProperty($key);
                if (!$property->getType()->isBuiltin()) {
                    try {
                        $isType  = false;
                        $refType = new ReflectionClass($property->getType()->getName());
                        try {
                            if ($refType->isInstance($value)) {
                                $isType = true;
                            }
                        } catch (Throwable) {
                        }
                        if ($isType === false) {
                            if (enum_exists($refType->getName())) {
                                $refEnum = new ReflectionEnum($refType->getName());
                                $value   = $refEnum->getCase($value)->getValue();
                            } else {
                                $value = $refType->newInstance($value);
                            }
                        }
                    } catch (ReflectionException) {
                    }
                }
                $methodName = 'set' . Utils::parseName($property->getName(), 1);
                if ($ref->hasMethod($methodName)) {
                    try {
                        $ref->getmethod($methodName)->invoke($this, $value);
                    } catch (ReflectionException) {
                    }
                } else {
                    $property->setValue($this, $value);
                }
            }
        }
    }

    /**
     * 转换当前对象为Array数组
     * @param bool $style     命名风格 默认：true; 选项：false = Java风格 true = C风格
     * @param bool $objEscape 对象是否转为基本数据类型 默认：true;
     * @return array
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2021/7/19 10:45
     */
    public function toArray(bool $style = true, bool $objEscape = true): array
    {
        $ref   = new ReflectionClass($this);
        $array = [];
        foreach ($ref->getProperties() as $property) {
            $key = $style ? Utils::parseName($property->getName()) : $property->getName();
            try {
                $methodName = 'get' . Utils::parseName($property->getName(), 1);
                if ($ref->hasMethod($methodName)) {
                    $value = $ref->getmethod($methodName)->invoke($this);
                } else {
                    $value = $property->getValue($this);
                }
            } catch (Throwable) {
                unset($value);
            }
            if (isset($value)) {
                if ($objEscape) {
                    if ($value instanceof Struct) {
                        $array[$key] = $value->toArray($style, $objEscape);
                    } else if ($value instanceof Enum) {
                        $array[$key] = (string)$value;
                    } else {
                        $array[$key] = $value;
                    }
                } else {
                    $array[$key] = $value;
                }
            }
        }
        return $array;
    }

    /**
     * 转换当前对象为JSON字符串
     * @param int $options
     * @return string
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2021/5/10 11:26
     */
    public function toJson(int $options = JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * __toString
     * @return string
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2021/5/10 11:27
     */
    public function __toString(): string
    {
        $ref = new ReflectionClass($this);
        return $ref->getShortName() . ' (' . $this->toJson() . ')';
    }

    /**
     * 返回能被 json_encode() 序列化的数据， 这个值可以是除了 resource 外的任意类型。
     * @return array
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2021/5/10 11:30
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * 检查一个偏移位置是否存在
     * @param mixed $offset
     * @return bool
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2021/5/10 9:36
     */
    public function offsetExists(#[LanguageLevelTypeAware(['8.0' => 'mixed'], default: '')] $offset): bool
    {
        return property_exists($this, $offset);
    }

    /**
     * 获取一个偏移位置的值
     * @param mixed $offset
     * @return mixed
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2021/5/10 9:37
     */
    public function offsetGet(#[LanguageLevelTypeAware(['8.0' => 'mixed'], default: '')] $offset): mixed
    {
        return $this->$offset;
    }

    /**
     *  设置一个偏移位置的值
     * @param mixed $offset
     * @param mixed $value
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2021/5/10 9:37
     */
    public function offsetSet(#[LanguageLevelTypeAware(['8.0' => 'mixed'], default: '')] $offset, #[LanguageLevelTypeAware(['8.0' => 'mixed'], default: '')] $value): void
    {
        $this->$offset = $value;
    }

    /**
     * 复位一个偏移位置的值
     * @param mixed $offset
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2021/5/10 9:37
     */
    public function offsetUnset(#[LanguageLevelTypeAware(['8.0' => 'mixed'], default: '')] $offset): void
    {
        unset($this->$offset);
    }


    /**
     * __call
     * @param string $name
     * @param array  $arguments
     * @return mixed
     * @author TaoGe <liangtao.gz@foxmail.com>
     * @date   2022/6/20 10:00
     */
    public function __call(string $name, array $arguments): mixed
    {
        $prefix = substr($name, 0, 3);
        if (!in_array($prefix, ['get', 'set'])) {
            throw new Error('Call to undefined method ' . __CLASS__ . '::' . $name . '()');
        }
        $ref = new ReflectionClass($this);
        $key = lcfirst(substr($name, 3));
        if (!$ref->hasProperty($key)) {
            throw new Error('Call to undefined method ' . __CLASS__ . '::' . $name . '()');
        }
        $property = $ref->getProperty($key);
        if ($prefix === 'set') {
            $property->setValue($this, ...$arguments);
        }
        return $property->getValue($this);
    }

}
