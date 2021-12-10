<?php

namespace Woyteck\Db;

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
    public function offsetExists($offset): bool
    {
        return isset($this->array[$offset]);
    }

    /**
     * @param mixed $offset
     * @return mixed|null
     */
    public function offsetGet($offset): mixed
    {
        return isset($this->array[$offset]) ? $this->array[$offset] : null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
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
    public function offsetUnset($offset): void
    {
        unset($this->array[$offset]);
    }

    /**
     * @return mixed
     */
    public function current(): mixed
    {
        return $this->array[$this->pointer];
    }

    public function next(): void
    {
        ++$this->pointer;
    }

    /**
     * @return int
     */
    public function key(): mixed
    {
        return $this->pointer;
    }

    /**
     * @return bool
     */
    public function valid(): bool
    {
        return isset($this->array[$this->pointer]);
    }

    public function rewind(): void
    {
        $this->pointer = 0;
    }

    public function count(): int
    {
        return count($this->array);
    }
}
