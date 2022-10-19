<?php
declare(strict_types=1);

namespace Woyteck\Db;

class Mock
{
    /** @var array */
    public static $mock;

    public static function getOne($className, array $params = []): ?array
    {
        if (!isset(self::$mock[$className]) || !is_array(self::$mock[$className])) {
            return null;
        }

        foreach (self::$mock[$className] as $mockedArray) {
            foreach ($params as $key => $param) {
                if (!self::isMatch($mockedArray, $key, $param)) {
                    continue;
                }

                return $mockedArray;
            }
        }

        return null;
    }

    public static function getMany($className, array $params = []): array
    {
        if (!isset(self::$mock[$className]) || !is_array(self::$mock[$className])) {
            return [];
        }

        $array = [];
        $i = 0;
        foreach (self::$mock[$className] as $mockedArray) {
            foreach ($params as $key => $param) {
                if (!self::isMatch($mockedArray, $key, $param)) {
                    continue;
                }

                $array[$i] = $mockedArray;
            }

            $i++;
        }

        return $array;
    }

    private static function isMatch(array $array, string $key, $value): bool
    {
        $not = 'not_';
        $greater = 'greater_';
        $lower = 'lower_';
        $like = 'like_';
        $notLike = 'not_like_';
        $notIn = 'not_in_';

        if (strpos($key, $not) === 0) {
            $keyName = substr($key, strlen($not));
            if (!isset($array[$keyName]) || $array[$keyName] === $value) {
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
        } elseif (strpos($key, $notIn) === 0 && is_array($value)) {
            $keyName = substr($key, strlen($notIn));
            if (in_array($array[$keyName], $value)) {
                return false;
            }
        } elseif (!isset($array[$key])) {
            return false;
        } elseif ($array[$key] !== $value) {
            return false;
        }

        return true;
    }

    public static function save(ModelAbstract $model): int
    {
        $modelArray = $model->toArray();

        $className = get_class($model);
        $primaryKeyField = $className::$primaryKey;

        $found = null;
        $lastPrimaryKeyValue = null;
        if (isset(self::$mock[$className]) && is_array(self::$mock[$className])) {
            foreach (self::$mock[$className] as $key => $mockedArray) {
                if (isset($mockedArray[$primaryKeyField]) && isset($modelArray[$primaryKeyField]) && $mockedArray[$primaryKeyField] === $modelArray[$primaryKeyField]) {
                    if ($lastPrimaryKeyValue === null || $mockedArray[$primaryKeyField] > $lastPrimaryKeyValue) {
                        $lastPrimaryKeyValue = $mockedArray[$primaryKeyField];
                    }
                    self::$mock[$className][$key] = $modelArray;
                    $found = $key;
                }
            }
        }
        if ($found !== null) {
            self::$mock[$className][$found] = $modelArray;
        } else {
            $newPrimaryKeyValue = $lastPrimaryKeyValue + 1;
            $modelArray[$primaryKeyField] = $newPrimaryKeyValue;
            self::$mock[$className][$newPrimaryKeyValue] = $modelArray;
        }

        return $modelArray[$primaryKeyField];
    }
}
