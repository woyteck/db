<?php
declare(strict_types=1);

namespace Woyteck\Db;

class Mock
{
    /** @var array */
    public static $mock;

    /** @var bool ModelAbstract */
    public static $throwExceptionOnSelect;

    /** @var bool ModelAbstract */
    public static $throwExceptionOnInsertUpdate;

    /** @var bool ModelAbstract */
    public static $throwExceptionOnDelete;

    /** @var array */
    private static $transaction;

    public static function reset() {
        self::$mock = [];
        self::$throwExceptionOnSelect = null;
        self::$throwExceptionOnInsertUpdate = null;
        self::$throwExceptionOnDelete = null;
        self::$transaction = null;
    }

    public static function getOne($className, array $params = []): ?array
    {
        self::throwExceptionOnSelectIfSet($className);

        if (!isset(self::$mock[$className]) || !is_array(self::$mock[$className])) {
            return null;
        }

        foreach (self::$mock[$className] as $mockedArray) {
            $isMatched = true;
            foreach ($params as $key => $param) {
                if (!self::isMatch($mockedArray, $key, $param)) {
                    $isMatched = false;
                }
            }
            if ($isMatched) {
                return $mockedArray;
            }
        }

        return null;
    }

    public static function getMany($className, array $params = []): array
    {
        self::throwExceptionOnSelectIfSet($className);

        if (!isset(self::$mock[$className]) || !is_array(self::$mock[$className])) {
            return [];
        }

        $array = [];
        $i = 0;
        foreach (self::$mock[$className] as $mockedArray) {
            $isMatched = true;
            foreach ($params as $key => $param) {
                if (!self::isMatch($mockedArray, $key, $param)) {
                    $isMatched = false;
                }
            }
            if ($isMatched === true) {
                $array[$i] = $mockedArray;
            }
            $i++;
        }

        return $array;
    }

    public static function delete($className, array $params = []): void
    {
        self::throwExceptionOnDeleteIfSet($className);

        if (!isset(self::$mock[$className]) || !is_array(self::$mock[$className])) {
            return;
        }

        foreach (self::$mock[$className] as $rowKey => $mockedArray) {
            $isMatched = true;
            foreach ($params as $key => $param) {
                if (!self::isMatch($mockedArray, $key, $param)) {
                    $isMatched = false;
                }
            }
            if ($isMatched) {
                unset(self::$mock[$className][$rowKey]);
            }
        }
    }

    private static function isMatch(array $array, string $key, $value): bool
    {
        $not = 'not_';
        $greater = 'greater_';
        $lower = 'lower_';
        $like = 'like_';
        $notLike = 'not_like_';
        $notIn = 'not_in_';
        $isNotNull = 'is_not_null_';

        if (strpos($key, $not) === 0) {
            $keyName = substr($key, strlen($not));
            if (isset($array[$keyName]) && $array[$keyName] === $value) {
                return false;
            }
        } elseif (strpos($key, $greater) === 0) {
            $keyName = substr($key, strlen($greater));
            if ($array[$keyName] <= $value) {
                return false;
            }
        } elseif (strpos($key, $lower) === 0) {
            $keyName = substr($key, strlen($lower));
            if ($array[$keyName] >= $value) {
                return false;
            }
        } elseif (strpos($key, $like) === 0) {
            $keyName = substr($key, strlen($like));
            if (!str_contains($value, $array[$keyName])) {
                return false;
            }
        } elseif (strpos($key, $notLike) === 0) {
            $keyName = substr($key, strlen($notLike));
            if (str_contains($value, $array[$keyName])) {
                return false;
            }
        } elseif (strpos($key, $isNotNull) === 0) {
            $keyName = substr($key, strlen($isNotNull));
            if (!isset($array[$keyName])) {
                return false;
            }
        } elseif (strpos($key, $notIn) === 0 && is_array($value)) {
            $keyName = substr($key, strlen($notIn));
            if (in_array($array[$keyName], $value)) {
                return false;
            }
        } elseif ($value === null) {
            return !isset($array[$key]);
        } elseif (!isset($array[$key])) {
            return false;
        } elseif (is_array($value)) {
            return in_array($array[$key], $value);
        } elseif ($array[$key] !== $value) {
            return false;
        }

        return true;
    }

    public static function save(ModelAbstract $model): int
    {
        self::throwExceptionOnInsertUpdateIfSet($model::class);

        $modelArray = $model->toArray();

        $className = get_class($model);
        $primaryKeyField = $className::$primaryKey;

        $found = null;
        if (isset(self::$mock[$className]) && is_array(self::$mock[$className])) {
            foreach (self::$mock[$className] as $key => $mockedArray) {
                if (isset($mockedArray[$primaryKeyField])
                    && isset($modelArray[$primaryKeyField])
                    && $mockedArray[$primaryKeyField] === $modelArray[$primaryKeyField]
                ) {
                    self::$mock[$className][$key] = $modelArray;
                    $found = $key;
                }
            }
        }
        if ($found !== null) {
            self::$mock[$className][$found] = $modelArray;
        } else {
            $newPrimaryKeyValue = isset(self::$mock[$className]) ? (max(array_keys(self::$mock[$className])) + 1) : 1;
            $modelArray[$primaryKeyField] = $newPrimaryKeyValue;
            self::$mock[$className][$newPrimaryKeyValue] = $modelArray;
        }

        return $modelArray[$primaryKeyField];
    }

    public static function beginTransaction()
    {
        if (self::$transaction !== null) {
            throw new Exception('Transaction already begun');
        }

        self::$transaction = self::$mock;
    }

    public static function commit()
    {
        if (self::$transaction === null) {
            throw new Exception('No transaction to commit');
        }

        self::$transaction = null;
    }

    public static function rollback()
    {
        if (self::$transaction === null) {
            throw new Exception('No transaction to commit');
        }

        self::$mock = self::$transaction;
        self::$transaction = null;
    }

    private static function throwExceptionOnSelectIfSet(string $modelClass)
    {
        if (self::$throwExceptionOnSelect === $modelClass) {
            self::$throwExceptionOnSelect = null;
            throw new Exception('Mock exception on SELECT');
        }
    }

    private static function throwExceptionOnInsertUpdateIfSet(string $modelClass)
    {
        if (self::$throwExceptionOnInsertUpdate === $modelClass) {
            self::$throwExceptionOnInsertUpdate = null;
            throw new Exception('Mock exception on INSERT/UPDATE');
        }
    }

    private static function throwExceptionOnDeleteIfSet(string $modelClass)
    {
        if (self::$throwExceptionOnDelete === $modelClass) {
            self::$throwExceptionOnDelete = null;
            throw new Exception('Mock exception on DELETE');
        }
    }
}
