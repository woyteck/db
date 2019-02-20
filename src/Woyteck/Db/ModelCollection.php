<?php

namespace Woyteck\Db;

use ArrayIterator;
use Traversable;
use Woyteck\ArrayInterface;

class ModelCollection implements \Iterator, ArrayInterface
{
    /**
     * @var array
     */
    private $array = [];

    /**
     * @var int
     */
    private $pointer = 0;

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->array[$offset]);
    }

    /**
     * @param mixed $offset
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        return isset($this->array[$offset]) ? $this->array[$offset] : null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        if ($offset === null) {
            $this->array[] = $value;
        } else {
            $this->array[$offset] = $value;
        }
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->array[$offset]);
    }

//    /**
//     * @return ArrayIterator|Traversable
//     */
//    public function getIterator()
//    {
//        return new ArrayIterator($this);
//    }

    /**
     * @return mixed
     */
    public function current()
    {
        return $this->array[$this->pointer];
    }

    public function next()
    {
        ++$this->pointer;
    }

    /**
     * @return int
     */
    public function key()
    {
        return $this->pointer;
    }

    /**
     * @return bool
     */
    public function valid()
    {
        return isset($this->array[$this->pointer]);
    }

    public function rewind()
    {
        $this->pointer = 0;
    }

    public function count()
    {
        return count($this->array);
    }
}
