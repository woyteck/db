<?php

namespace Woyteck\Db;

use PDO;
use PDOStatement;

class ModelFactory
{
    private const OPERATOR_EQUALS = '=';
    private const OPERATOR_NOT_EQUALS = '!=';
    private const OPERATOR_IN = 'IN';
    private const OPERATOR_NOT_IN = 'NOT_IN';
    private const OPERATOR_GREATER_THAN = '>';
    private const OPERATOR_LOWER_THAN = '<';
    private const OPERATOR_IS_NULL = 'IS';
    private const OPERATOR_IS_NOT_NULL = 'IS_NOT_NULL';
    private const OPERATOR_LIKE = 'LIKE';
    private const OPERATOR_NOT_LIKE = 'NOT_LIKE';

    /** @var PDO */
    private $pdo;

    /** @var int */
    private $modelsCount;

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
    public function getOne(string $className, array $params = [], bool $forUpdate = false): ?ModelAbstract
    {
        $this->modelsCount = 0;

        if (Mock::$mock !== null) {
            $data = Mock::getOne($className, $params);
            if ($data !== null) {
                $this->modelsCount = 1;

                return $this->create($className, $data);
            }

            return null;
        } else {
            $statement = $this->query($className, $params, $forUpdate, 1);
            if ($statement->rowCount() > 0) {
                $this->modelsCount = $this->getFoundRows();
                $data = $statement->fetch();

                return $this->create($className, $data);
            }
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
     * @param string $sortOrder
     * @return ModelCollection
     * @throws Exception
     */
    public function getMany(string $className, array $params = [], bool $forUpdate = false, int $limit = null, int $offset = null, string $sortBy = null, string $sortOrder = 'ASC'): ModelCollection
    {
        $collection = new ModelCollection();

        if (Mock::$mock !== null) {
            $rows = Mock::getMany($className, $params);
            $this->modelsCount = count($rows);
        } else {
            $statement = $this->query($className, $params, $forUpdate, $limit, $offset, $sortBy, $sortOrder);
            $rows = $statement->fetchAll();
            $this->modelsCount = $this->getFoundRows();
        }

        foreach ($rows as $row) {
            $collection[] = $this->create($className, $row);
        }

        return $collection;
    }

    public function getManyUsingQuery(string $className, string $query, array $vars = []): ModelCollection
    {
        $modelsArray = new ModelCollection();

        if (Mock::$mock !== null) {
            if (isset(Mock::$mock[$className]) && is_array(Mock::$mock[$className])) {
                foreach (Mock::$mock[$className] as $item) {
                    $modelsArray[] = $this->create($className, $item);
                }
            }
        } else {
            $statement = $this->getAdapter()->prepare($query);
            $statement->execute($vars);
            $rows = $statement->fetchAll();
            foreach ($rows as $row) {
                $modelsArray[] = $this->create($className, $row);
            }
        }

        return $modelsArray;
    }

    public function delete(string $className, array $params = []): void
    {
        if (Mock::$mock !== null) {
            Mock::delete($className, $params);
        } else {
            $this->queryDelete($className, $params);
        }
    }

    public function getModelsCount(): ?int
    {
        return $this->modelsCount;
    }

    public function getAdapter(): PDO
    {
        return $this->pdo;
    }

    public function beginTransaction(): void
    {
        if (Mock::$mock !== null) {
            return;
        }

        $this->pdo->beginTransaction();
    }

    public function rollBack(): void
    {
        if (Mock::$mock !== null) {
            return;
        }

        $this->pdo->rollBack();
    }

    public function commit(): void
    {
        if (Mock::$mock !== null) {
            return;
        }

        $this->pdo->commit();
    }

    private function getFoundRows(): int
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
     * @param string $order
     * @return PDOStatement
     * @throws Exception
     */
    private function query(string $className, array $params = [], bool $forUpdate = false, int $limit = null, int $offset = null, string $orderBy = null, string $order = 'ASC'): PDOStatement
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
            if (stripos($field, 'is_not_null_') === 0) {
                $field = str_replace('is_not_null_', '', $field);
                $operator = self::OPERATOR_IS_NOT_NULL;
            } elseif (stripos($field, 'greater_') === 0) {
                $field = str_replace('greater_', '', $field);
                $operator = self::OPERATOR_GREATER_THAN;
            } elseif (stripos($field, 'lower_') === 0) {
                $field = str_replace('lower_', '', $field);
                $operator = self::OPERATOR_LOWER_THAN;
            } elseif (stripos($field, 'like_') === 0) {
                $field = str_replace('like_', '', $field);
                $operator = self::OPERATOR_LIKE;
            } elseif (stripos($field, 'not_like_') === 0) {
                $field = str_replace('not_like_', '', $field);
                $operator = self::OPERATOR_NOT_LIKE;
            } elseif (stripos($field, 'not_') === 0) {
                $field = str_replace('not_', '', $field);
                $operator = self::OPERATOR_NOT_EQUALS;
            } elseif ($value === null) {
                $operator = self::OPERATOR_IS_NULL;
            } elseif (is_array($value)) {
                $operator = self::OPERATOR_IN;
            } elseif (stripos($field, 'not_in_') === 0 && is_array($value)) {
                $field = str_replace('not_in_', '', $field);
                $operator = self::OPERATOR_NOT_IN;
            }

            if ($operator == self::OPERATOR_IS_NULL) {
                $where[] = $tableAlias . '.' . $field . ' IS NULL';
            } elseif ($operator == self::OPERATOR_IS_NOT_NULL) {
                $where[] = $tableAlias . '.' . $field . ' IS NOT NULL';
            } elseif ($operator == self::OPERATOR_EQUALS) {
                $where[] = $tableAlias . '.' . $field . '=:' . $field;
                $vars[$field] = $value;
            } elseif ($operator == self::OPERATOR_NOT_EQUALS) {
                $where[] = $tableAlias . '.' . $field . '!=:' . $field;
                $vars[$field] = $value;
            } elseif ($operator == self::OPERATOR_GREATER_THAN) {
                $where[] = $tableAlias . '.' . $field . '>:' . $field;
                $vars[$field] = $value;
            } elseif ($operator == self::OPERATOR_LOWER_THAN) {
                $where[] = $tableAlias . '.' . $field . '<:' . $field;
                $vars[$field] = $value;
            } elseif ($operator == self::OPERATOR_IN) {
                $where[] = $tableAlias . '.' . $field . " IN ('" . implode("','", $value) . "')";
            } elseif ($operator == self::OPERATOR_NOT_IN) {
                $where[] = $tableAlias . '.' . $field . " NOT IN ('" . implode("','", $value) . "')";
            } elseif ($operator == self::OPERATOR_LIKE) {
                $where[] = $tableAlias . '.' . $field . ' LIKE :' . $field;
                $vars[$field] = $value;
            } elseif ($operator == self::OPERATOR_NOT_LIKE) {
                $where[] = $tableAlias . '.' . $field . ' NOT LIKE :' . $field;
                $vars[$field] = $value;
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

    /**
     * @param string $className
     * @param array $params
     *
     * @throws Exception
     */
    private function queryDelete(string $className, array $params = []): void
    {
        /** @var ModelAbstract $className */
        $tableName = $className::$tableName;
        $where = [];
        $vars = [];

        $query = "DELETE FROM `{$tableName}` ";
        foreach ($params as $field => $value) {
            $operator = self::OPERATOR_EQUALS;
            if (stripos($field, 'is_not_null_') === 0) {
                $field = str_replace('is_not_null_', '', $field);
                $operator = self::OPERATOR_IS_NOT_NULL;
            } elseif (stripos($field, 'greater_') === 0) {
                $field = str_replace('greater_', '', $field);
                $operator = self::OPERATOR_GREATER_THAN;
            } elseif (stripos($field, 'lower_') === 0) {
                $field = str_replace('lower_', '', $field);
                $operator = self::OPERATOR_LOWER_THAN;
            } elseif (stripos($field, 'like_') === 0) {
                $field = str_replace('like_', '', $field);
                $operator = self::OPERATOR_LIKE;
            } elseif (stripos($field, 'not_like_') === 0) {
                $field = str_replace('not_like_', '', $field);
                $operator = self::OPERATOR_NOT_LIKE;
            } elseif (stripos($field, 'not_') === 0) {
                $field = str_replace('not_', '', $field);
                $operator = self::OPERATOR_NOT_EQUALS;
            } elseif ($value === null) {
                $operator = self::OPERATOR_IS_NULL;
            } elseif (is_array($value)) {
                $operator = self::OPERATOR_IN;
            } elseif (stripos($field, 'not_in_') === 0 && is_array($value)) {
                $field = str_replace('not_in_', '', $field);
                $operator = self::OPERATOR_NOT_IN;
            }

            if ($operator == self::OPERATOR_IS_NULL) {
                $where[] = $field . ' IS NULL';
            } elseif ($operator == self::OPERATOR_IS_NOT_NULL) {
                $where[] = $field . ' IS NOT NULL';
            } elseif ($operator == self::OPERATOR_EQUALS) {
                $where[] = $field . '=:' . $field;
                $vars[$field] = $value;
            } elseif ($operator == self::OPERATOR_NOT_EQUALS) {
                $where[] = $field . '!=:' . $field;
                $vars[$field] = $value;
            } elseif ($operator == self::OPERATOR_GREATER_THAN) {
                $where[] = $field . '>:' . $field;
                $vars[$field] = $value;
            } elseif ($operator == self::OPERATOR_LOWER_THAN) {
                $where[] = $field . '<:' . $field;
                $vars[$field] = $value;
            } elseif ($operator == self::OPERATOR_IN) {
                $where[] = $field . " IN ('" . implode("','", $value) . "')";
            } elseif ($operator == self::OPERATOR_NOT_IN) {
                $where[] = $field . " NOT IN ('" . implode("','", $value) . "')";
            } elseif ($operator == self::OPERATOR_LIKE) {
                $where[] = $field . ' LIKE :' . $field;
                $vars[$field] = $value;
            } elseif ($operator == self::OPERATOR_NOT_LIKE) {
                $where[] = $field . ' NOT LIKE :' . $field;
                $vars[$field] = $value;
            }
        }

        if (count($where) === 0) {
            throw new Exception('I will not allow you to delete anything without any WHERE params!');
        }

        if (count($where) > 0) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }

        $statement = $this->pdo->prepare($query);
        $statement->execute($vars);
    }
}
