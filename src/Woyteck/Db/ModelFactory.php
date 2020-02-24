<?php

namespace Woyteck\Db;

use PDO;
use PDOStatement;

class ModelFactory
{
    const OPERATOR_EQUALS = '=';
    const OPERATOR_IN = 'IN';
    const OPERATOR_GREATER_THAN = '>';
    const OPERATOR_LOWER_THAN = '<';
    const OPERATOR_IS_NULL = 'IS';

    /**
     * @var PDO
     */
    private $pdo;

    /**
     * @var int
     */
    private $modelsCount;

    /**
     * ModelFactory constructor.
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param string $className
     * @param array|null $data
     * @return ModelAbstract
     */
    public function create(string $className, array $data = null)
    {
        $model = new $className($this->pdo);
        if ($data !== null) {
            foreach ($data as $fieldName => $fieldValue) {
                $model->{$fieldName} = $fieldValue;
            }
        }

        return $model;
    }

    /**
     * @param string $className
     * @param array $params
     * @param bool $forUpdate
     * @return ModelAbstract|null
     * @throws Exception
     */
    public function getOne(string $className, array $params = [], $forUpdate = false): ?ModelAbstract
    {
        $this->modelsCount = 0;

        $statement = $this->query($className, $params, $forUpdate, 1);
        if ($statement->rowCount() > 0) {
            $this->modelsCount = $this->getFoundRows();

            return $this->create($className, $statement->fetch());
        }

        return null;
    }

    /**
     * @param string $className
     * @param array $params
     * @param bool $forUpdate
     * @param int|null $limit
     * @param int|null $offset
     * @param string|null $sortBy
     * @param string|null $sortOrder
     * @return ModelCollection
     * @throws Exception
     */
    public function getMany(string $className, array $params = [], $forUpdate = false, int $limit = null, int $offset = null, string $sortBy = null, string $sortOrder = 'ASC'): ModelCollection
    {
        $statement = $this->query($className, $params, $forUpdate, $limit, $offset, $sortBy, $sortOrder);
        $rows = $statement->fetchAll();
        $this->modelsCount = $this->getFoundRows();

        $collection = new ModelCollection();
        foreach ($rows as $row) {
            $collection[] = $this->create($className, $row);
        }

        return $collection;
    }

    /**
     * @return int
     */
    public function getModelsCount()
    {
        return $this->modelsCount;
    }

    /**
     * @return PDO
     */
    public function getAdapter()
    {
        return $this->pdo;
    }

    /**
     * @return int
     */
    private function getFoundRows()
    {
        $statement = $this->pdo->prepare('SELECT FOUND_ROWS() as found_rows');
        $statement->execute();
        $row = $statement->fetch();

        return (int) $row['found_rows'];
    }

    /**
     * @param string $className
     * @param array $params
     * @param bool $forUpdate
     * @param int|null $limit
     * @param int|null $offset
     * @param string|null $orderBy
     * @param string|null $order
     * @return PDOStatement
     * @throws Exception
     */
    private function query(string $className, array $params = [], $forUpdate = false, int $limit = null, int $offset = null, string $orderBy = null, string $order = 'ASC')
    {
        /** @var ModelAbstract $className */
        $tableName = $className::$tableName;
        $tableAlias = $className::$tableAlias;
        $tablePrimaryKey = $className::$primaryKey;
        $where = [];
        $vars = [];
        $columns = [
            "{$tableAlias}.*"
        ];

        if (isset($className::$joins) && is_array($className::$joins)) {
            $joinsCounter = 0;
            foreach ($className::$joins as $joinConfig) {
                if (!isset($joinConfig['columns']) || !isset($joinConfig['type']) || !isset($joinConfig['model']) || !isset($joinConfig['on'])) {
                    throw new Exception('Invalid join configuration: ' . json_encode($joinConfig));
                }

                $joinedTableAlias = $joinConfig['model']::$tableAlias . $joinsCounter;
                foreach ($joinConfig['columns'] as $column => $alias) {
                    $columns[] = $joinedTableAlias . '.' . $column . ' AS ' . $alias;
                }
                $joinsCounter++;
            }
        }

        $implodedColumns = implode(', ', $columns);
        $query = "SELECT SQL_CALC_FOUND_ROWS {$implodedColumns} FROM `{$tableName}` {$tableAlias}";
        foreach ($params as $field => $value) {
            $operator = self::OPERATOR_EQUALS;
            if ($value === null) {
                $operator = self::OPERATOR_IS_NULL;
            } elseif (stripos($field, 'greater_') === 0) {
                $field = str_replace('greater_', '', $field);
                $operator = self::OPERATOR_GREATER_THAN;
            } elseif (stripos($field, 'lower_') === 0) {
                $field = str_replace('lower_', '', $field);
                $operator = self::OPERATOR_LOWER_THAN;
            } elseif (is_array($value)) {
                $operator = self::OPERATOR_IN;
            }

            if ($operator == self::OPERATOR_IS_NULL) {
                $where[] = $tableAlias . '.' . $field . ' IS NULL';
            } elseif ($operator == self::OPERATOR_EQUALS) {
                $where[] = $tableAlias . '.' . $field . '=:' . $field;
                $vars[$field] = $value;
            } elseif ($operator == self::OPERATOR_GREATER_THAN) {
                $where[] = $tableAlias . '.' . $field . '>:' . $field;
                $vars[$field] = $value;
            } elseif ($operator == self::OPERATOR_LOWER_THAN) {
                $where[] = $tableAlias . '.' . $field . '<:' . $field;
                $vars[$field] = $value;
            } elseif ($operator == self::OPERATOR_IN) {
                $where[] = $tableAlias . '.' . $field . " IN ('" . implode("','", $value) . "')";
            }
        }

        if (isset($className::$joins) && is_array($className::$joins)) {
            $joinsCounter = 0;
            foreach ($className::$joins as $joinConfig) {
                $joinType = $joinConfig['type'];
                $joinedTableName = $joinConfig['model']::$tableName;
                $joinedTableAlias = $joinConfig['model']::$tableAlias . $joinsCounter;
                $on = str_replace('{alias}', $joinedTableAlias, $joinConfig['on']);
                $query .= " {$joinType} JOIN ({$joinedTableName} {$joinedTableAlias}) ON ({$on})";
                $joinsCounter++;
            }
        }

        if (count($where) > 0) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }

        if ($orderBy !== null) {
            $orderBy = preg_replace('/[^\da-zA-Z\-_]/i', '', $orderBy);
            $order = strtoupper($order);
            $query .= " ORDER BY `{$tableAlias}`.`{$orderBy}` {$order}";
        }

        if ($limit !== null) {
            $query .= ' LIMIT ' . $limit;
        }
        if ($offset !== null) {
            $query .= ' OFFSET ' . $offset;
        }

        if ($forUpdate === true) {
            $query .= ' FOR UPDATE';
        }

        $statement = $this->pdo->prepare($query);
        $statement->execute($vars);

        return $statement;
    }
}
