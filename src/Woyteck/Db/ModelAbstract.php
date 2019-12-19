<?php

namespace Woyteck\Db;

use PDO;

class ModelAbstract
{
    const JOIN_TYPE_LEFT = 'LEFT';
    const JOIN_TYPE_RIGHT = 'RIGHT';
    const JOIN_TYPE_INNER = 'INNER';

    public static $joins = [];

    /**
     * @var string
     */
    public static $tableName;

    /**
     * @var string
     */
    public static $tableAlias = 't';

    /**
     * @var string
     */
    public static $primaryKey;

    /**
     * @var array
     */
    public $columns = [];

    /**
     * @var PDO
     */
    private $db;

    /**
     * @var array
     */
    private $data = [];

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    /**
     * @param string $name
     * @return string|int|float|null
     */
    public function __get($name)
    {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }

    /**
     * @param string $name
     * @param string|int|float $value
     * @return $this
     */
    public function __set($name, $value)
    {
        $this->data[$name] = $value;

        return $this;
    }

    /**
     * @return int
     */
    public function save(): int
    {
        $tableName = static::$tableName;

        $primaryKeyField = static::$primaryKey;

        $insert = true;
        if ($primaryKeyField !== null && isset($this->data[$primaryKeyField]) && is_numeric($this->data[$primaryKeyField])) {
            $insert = false;
        }

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
                $vars[$fieldName] = $fieldValue === null ? 'null' : $fieldValue;
            }
            $query = "UPDATE {$tableName} SET " . implode(',', $wheres) . " WHERE {$primaryKeyField}=:primary_key";

            $statement = $this->db->prepare($query);
            $vars['primary_key'] = $this->data[$primaryKeyField];
            $statement->execute($vars);
        }

        return (int) $this->{$primaryKeyField};
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->data;
    }

    /**
     * @return array
     */
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

    /**
     * @return array
     */
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
