<?php

namespace Woyteck\Db;

use PDO;

class ModelAbstract
{
    public const JOIN_TYPE_LEFT = 'LEFT';
    public const JOIN_TYPE_RIGHT = 'RIGHT';
    public const JOIN_TYPE_INNER = 'INNER';

    public static $joins = [];

    /** @var string */
    public static $tableName;

    public static $tableAlias = 't';

    /** @var string */
    public static $primaryKey;

    public $columns = [];

    private $data = [];

    /** @var PDO */
    protected $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    /**
     * @param string $name
     * @return string|int|float|null
     */
    public function __get(string $name)
    {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }

    /**
     * @param string $name
     * @param string|int|float $value
     * @return $this
     */
    public function __set(string $name, $value)
    {
        $this->data[$name] = $value;

        return $this;
    }

    public function save(): int
    {
        $tableName = static::$tableName;

        $primaryKeyField = static::$primaryKey;

        $insert = true;
        if ($primaryKeyField !== null && isset($this->data[$primaryKeyField]) && is_numeric($this->data[$primaryKeyField])) {
            $insert = false;
        }

        if (isset(Mock::$mock[get_class($this)][Mock::MOCK_ONE])) {
            Mock::$mock[get_class($this)][Mock::MOCK_ONE] = $this->data;
        } else {
            if ($insert === true) {
                $wheres = [];
                foreach ($this->data as $fieldName => $fieldValue) {
                    $wheres[] = ":" . $fieldName;
                }
                $query = "INSERT INTO `{$tableName}` (" . implode(', ', array_keys($this->data)) . ")"
                    . " VALUES (" . implode(', ', $wheres) . ")";

                $statement = $this->db->prepare($query);
                $statement->execute($this->data);

                if ($primaryKeyField !== null) {
                    $this->{$primaryKeyField} = (int) $this->db->lastInsertId();
                }
            } else {
                $wheres = [];
                $vars = [];
                foreach ($this->data as $fieldName => $fieldValue) {
                    if ($fieldName == $primaryKeyField) {
                        continue;
                    }
                    if (in_array($fieldName, $this->getJoinedFieldNames())) {
                        continue;
                    }

                    $wheres[] = $fieldName . "=:" . $fieldName;
                    $vars[$fieldName] = $fieldValue;
                }
                $query = "UPDATE {$tableName} SET " . implode(',', $wheres) . " WHERE {$primaryKeyField}=:primary_key";

                $statement = $this->db->prepare($query);
                $vars['primary_key'] = $this->data[$primaryKeyField];
                $statement->execute($vars);
            }
        }

        return (int) $this->{$primaryKeyField};
    }

    public function toArray()
    {
        return $this->data;
    }

    /**
     * @param array|string[]|null $fields
     * @return string
     */
    public function getHash(array $fields = null): string
    {
        if ($fields === null) {
            return md5(serialize($this->data));
        }

        $array = [];
        foreach ($fields as $field) {
            if (isset($this->data[$field])) {
                if (is_float($this->data[$field])) {
                    $array[$field] = (string) $this->data[$field];
                } else {
                    $array[$field] = $this->data[$field];
                }
            }
        }

        return md5(serialize($array));
    }

    public function toArrayCamelCased(): array
    {
        $camels = [];

        foreach ($this->data as $key => $value) {
            $type = 'string';
            if (is_int($value)) {
                $type = 'int';
            }
            if (is_float($value)) {
                $type = 'float';
            }

            $separator = '_';
            $camel = str_replace($separator, '', ucwords($key, $separator));
            $camel = lcfirst($camel);
            if ($type === 'int') {
                $camels[$camel] = (int) $value;
            } elseif ($type === 'float') {
                $camels[$camel] = (float) $value;
            } else {
                $camels[$camel] = $value;
            }
        }

        return $camels;
    }

    private function getJoinedFieldNames(): array
    {
        $fieldNames = [];

        foreach (static::$joins as $join) {
            if (!isset($join['columns'])) {
                continue;
            }

            foreach ($join['columns'] as $column) {
                if (!isset($column)) {
                    continue;
                }

                $fieldNames[] = $column;
            }
        }

        return $fieldNames;
    }
}
