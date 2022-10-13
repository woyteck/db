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
                if ($mockedArray[$key] !== $param) {
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
        foreach (self::$mock[$className] as $mockedArray) {
            foreach ($params as $key => $param) {
                if ($mockedArray[$key] !== $param) {
                    continue;
                }

                $array[] = $mockedArray;
            }
        }

        return $array;
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
            self::$mock[$className][$lastPrimaryKeyValue + 1] = $modelArray;
        }

        return $modelArray[$primaryKeyField];
    }
}
