<?php

namespace Hraw\DBQB;

use Hraw\DBQB\PDOConnector;
use Exception;
use PDO;

class QueryBuilder
{
    /**
     * Holds the instance of the database
     */
    private $connection = null;

    /**
     * Holds parts of the query
     */
    protected $queryBuilder = [];

    /**
     * Allowed operators for where query
     */
    protected $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
        'like', 'like binary', 'not like', 'ilike',
        '&', '|', '^', '<<', '>>', '&~',
        'rlike', 'not rlike', 'regexp', 'not regexp',
        '~', '~*', '!~', '!~*', 'similar to',
        'not similar to', 'not ilike', '~~*', '!~~*'
    ];

    /**
     * Holds the type of query
     */
    protected $queryType = 'select';

    /**
     * Holds the base part of sql query
     */
    protected $baseQuery;

    /**
     * Set database connection
     * @return void
     * @throws Exception
     */
    public function connect(array $connection)
    {
        try {
            PDOConnector::make()->setConnection($connection);
        } catch (Exception $e) {
            throw new Exception($e);
        }
    }

    /**
     * Get database connection
     * @throws Exception
     */
    public function connection($name): QueryBuilder
    {
        try {
            $this->connection = PDOConnector::make()->getConnection($name);
            return $this;
        } catch (Exception $e) {
            throw new Exception($e);
        }
    }

    /**
     * @throws Exception
     */
    public function beginTransaction()
    {
        if (is_null($this->connection)) {
            $this->connection('default');
        }
        $this->connection->beginTransaction();
    }

    /**
     * @throws Exception
     */
    public function commit()
    {
        if (is_null($this->connection)) {
            $this->connection('default');
        }
        $this->connection->commit();
    }

    /**
     * @throws Exception
     */
    public function rollBack()
    {
        if (is_null($this->connection)) {
            $this->connection('default');
        }
        $this->connection->rollBack();
    }

    /**
     * Builds select query
     * @param string $columns
     * @return object
     */
    public function select(string $columns="*")
    {
        if($columns !== '*') {
            $columns = implode(',', func_get_args($columns));
        }
        if (
            !isset($this->queryBuilder['select']) &&
            !isset($this->queryBuilder['wheres'])
        ) {
            $this->queryBuilder['select'] = "SELECT {$columns} FROM";
        } else {
            $this->queryBuilder['wheres'][] = "SELECT {$columns} FROM";
        }
        return $this;
    }

    /**
     * Set a default select if not exists
     * @return void
     */
    private function getSelectStatement()
    {
        if(!isset($this->queryBuilder['select'])) {
            $this->queryBuilder['select'] = 'SELECT * FROM';
        }
        return $this->queryBuilder['select'];
    }

    /**
     * Set table name in query builder
     * @param $tableName
     * @return object
     * @throws Exception
     */
    public function table($tableName)
    {
        if (is_null($this->connection)) {
            $this->connection('default');
        }
        if (!isset($this->queryBuilder['table'])) {
            $this->queryBuilder['table'] = $tableName;
        } else {
            $this->queryBuilder['wheres'][] = $tableName;
        }
        
        return $this;
    }

    /**
     * Another function for table
     * @param $tableName
     * @return object
     * @throws Exception
     */
    public function from($tableName)
    {
        return $this->table($tableName);
    }

    /**
     * Handle callback for nested query
     */
    public function handleCallback($callback, $type, $clause='WHERE')
    {
        $clauseType = $clause === 'WHERE' ? 'wheres' : 'havings';
        $append = $clause;
        if (isset($this->queryBuilder[$clauseType])) {
            $append = $type;
        }
        $this->queryBuilder[$clauseType][] = $append;
        $this->queryBuilder[$clauseType][] = '(';
        $callback($this);
        $this->queryBuilder[$clauseType][] = ')';
    }

    /**
     * Add where condition in query
     * @param string|function $columnName
     * @param mixed $operator
     * @param $value
     * @return object
     */
    public function where($columnName, $operator='=', $value=null)
    {
        if (is_callable($columnName)) {
            $this->handleCallback($columnName, 'AND');
        } else {
            if (is_array($columnName)) {
                foreach ($columnName as $key => $value) {
                    $this->buildWhere($key, '=', $value, 'AND');
                }
            } else {
                $this->buildWhere($columnName, $operator, $value, 'AND');
            }
        }
        return $this;
    }

    /**
     * Add or where condition in query
     * @param string|function $columnName
     * @param mixed $operator
     * @param $value
     * @return object
     */
    public function orWhere($columnName, $operator='=', $value=null)
    {
        if (is_callable($columnName)) {
            $this->handleCallback($columnName, 'OR');
        } else {
            $this->buildWhere($columnName, $operator, $value, 'OR');
        }
        return $this;
    }

    /**
     * Add where in condition in query
     * @param string $columnName
     * @param array $values
     * @return object
     */
    public function whereIn(string $columnName, array $values)
    {
        $this->buildWhereIn($columnName, $values, 'AND');
        return $this;
    }

    /**
     * Add or where in condition in query
     * @param string $columnName
     * @param array $values
     * @return object
     */
    public function orWhereIn(string $columnName, array $values)
    {
        $this->buildWhereIn($columnName, $values, 'OR');
        return $this;
    }

    /**
     * Add where not in condition in query
     * @param string $columnName
     * @param array $values
     * @return object
     */
    public function whereNotIn(string $columnName, array $values)
    {
        $this->buildWhereIn($columnName, $values, 'AND', 'NOT IN');
        return $this;
    }

    /**
     * Add or where not in condition in query
     * @param string $columnName
     * @param array $values
     * @return object
     */
    public function orWhereNotIn(string $columnName, array $values)
    {
        $this->buildWhereIn($columnName, $values, 'OR', 'NOT IN');
        return $this;
    }

    /**
     * Add WHERE BETWEEN condition in sql query.
     * @param $columnName
     * @param array $values
     * @return object
     */
    public function whereBetween($columnName, array $values)
    {
        $this->buildWhereBetween($columnName, $values, 'AND');
        return $this;
    }

    /**
     * Add WHERE NOT BETWEEN condition to sql query.
     * @param $columnName
     * @param array $values
     * @return object
     */
    public function whereNotBetween($columnName, array $values)
    {
        $this->buildWhereBetween($columnName, $values, 'AND', 'NOT BETWEEN');
        return $this;
    }

    /**
     * Add OR WHERE BETWEEN condition to sql query.
     * @param $columnName
     * @param array $values
     * @return object
     */
    public function orWhereBetween($columnName, array $values)
    {
        $this->buildWhereBetween($columnName, $values, 'OR');
        return $this;
    }

    /**
     * Add OR WHERE NOT BETWEEN condition to sql query.
     * @param $columnName
     * @param array $values
     * @return object
     */
    public function orWhereNotBetween($columnName, array $values)
    {
        $this->buildWhereBetween($columnName, $values, 'OR', 'NOT BETWEEN');
        return $this;
    }

    /**
     * Add WHERE NULL condition to sql query
     * @param $columnName
     * @return object
     */
    public function whereNull($columnName)
    {
        $this->buildWhereNull($columnName, 'AND');
        return $this;
    }

    /**
     * Add OR WHERE NULL condition to sql query
     * @param $columnName
     * @return object
     */
    public function orWhereNull($columnName)
    {
        $this->buildWhereNull($columnName, 'OR');
        return $this;
    }

    /**
     * Add WHERE NOT NULL condition to sql query
     * @param $columnName
     * @return object
     */
    public function whereNotNull($columnName)
    {
        $this->buildWhereNull($columnName, 'AND', 'NOT NULL');
        return $this;
    }

    /**
     * Add OR WHERE NOT NULL condition to sql query
     * @param $columnName
     * @return object
     */
    public function orWhereNotNull($columnName)
    {
        $this->buildWhereNull($columnName, 'OR', 'NOT NULL');
        return $this;
    }

    private function buildWhere(string $columnName, $operator, $value, $type)
    {
        if (is_string($operator)) {
            if (!in_array(strtolower($operator), $this->operators)) {
                $value = $operator;
                $operator = '=';
            }
        } else {
            $value = $operator;
            $operator = '=';
        }
        if (isset($this->queryBuilder['wheres']) && end($this->queryBuilder['wheres']) === '(') {
            $this->queryBuilder['wheres'][] = "{$columnName} {$operator} ?";
        } else {
            $this->queryBuilder['wheres'][] = $this->condition($type)." {$columnName} {$operator} ?";
        }

        $this->queryBuilder['placeholdersValue'][] = $value;
    }

    private function buildWhereIn(string $columnName, array $values, $type, $operator='IN')
    {
        $this->queryBuilder['wheres'][] = $this->condition($type)." {$columnName} {$operator} (".$this->placeholders($values).")";
        foreach ($values as $key => $value) {
            $this->queryBuilder['placeholdersValue'][] = $value;
        }
    }

    private function buildWhereBetween($columnName, array $values, $type, $operator='BETWEEN')
    {
        $this->queryBuilder['wheres'][] = $this->condition($type)." {$columnName} {$operator} ? AND ?";
        foreach ($values as $key => $value) {
            $this->queryBuilder['placeholdersValue'][] = $value;
        }
    }

    private function buildWhereNull($columnName, $type, $operator='NULL')
    {
        $this->queryBuilder['wheres'][] = $this->condition($type)." {$columnName} IS {$operator}";
    }

    /**
     * Set order by clause to query
     * @param string $columnName
     * @param string $orderType
     * @return object
     */
    public function orderBy(string $columnName, string $orderType='ASC')
    {
        if(!isset($this->queryBuilder['orderBy'])) {
            $this->queryBuilder['orderBy'][] = " ORDER BY {$columnName} {$orderType}";
        } else {
            $this->queryBuilder['orderBy'][] = ", {$columnName} {$orderType}";
        }
        return $this;
    }

    /**
     * Add offset in Sql Query.
     * @param $value
     * @return object
     */
    public function offset($value)
    {
        $this->queryBuilder['offset'] = $value;
        return $this;
    }

    /**
     * Set limit in Sql Query.
     * @param $value
     * @return object
     */
    public function limit($value)
    {
        $offset = $this->queryBuilder['offset'] ?? 0;
        $this->queryBuilder['limit'] = " LIMIT ";
        if($offset) {
            $this->queryBuilder['limit'] .= "{$offset},";
        }
        $this->queryBuilder['limit'] .= $value;
        return $this;
    }

    /**
     * ADD INNER JOIN clause to sql query.
     * @param $table
     * @param $first
     * @param $operator
     * @param $second
     * @param string $type
     * @return object
     */
    public function join($table, $first, $operator, $second, $type='INNER')
    {
        if(!isset($this->queryBuilder['joins'])) {
            $this->queryBuilder['joins'] = [];
        }
        $this->queryBuilder['joins'][] = "{$type} JOIN {$table} ON {$first} {$operator} {$second}";
        return $this;
    }

    /**
     * Add LEFT JOIN clause to sql query.
     * @param $table
     * @param $first
     * @param $operator
     * @param $second
     * @return object
     */
    public function leftJoin($table, $first, $operator, $second)
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /**
     * Add RIGHT JOIN clause to sql query.
     * @param $table
     * @param $first
     * @param $operator
     * @param $second
     * @return object
     */
    public function rightJoin($table, $first, $operator, $second)
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    /**
     * Add OUTER JOIN clause to sql query.
     * @param $table
     * @param $first
     * @param $operator
     * @param $second
     * @return object
     */
    public function outerJoin($table, $first, $operator, $second)
    {
        return $this->join($table, $first, $operator, $second, 'OUTER');
    }

    /**
     * Execute a raw query
     * @param string $query
     * @param array $values
     * @return mixed
     * @throws Exception
     */
    public function raw(string $query, array $values = [])
    {
        if (is_null($this->connection)) {
            $this->connection('default');
        }
        if(empty($values)) {
            $stmt = $this->connection->query($query);
        } else {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($values);
        }

        //Check if query type is select or not
        if (strtolower(substr($query, 0, 6)) === 'select') {
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        }
        return $stmt->rowCount();
    }

    /**
     * Fetch all records from the table
     * @return array
     * @throws Exception
     */
    public function all($tableName)
    {
        if (is_null($this->connection)) {
            $this->connection('default');
        }
        $stmt = $this->connection->query("SELECT * FROM $tableName");
        $this->destroyQueryBuilder();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Fetch array of objects from database
     * @return array
     * @throws Exception
     */
    public function get()
    {
        return $this->prepareQuery()->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Fetch the first result
     * @return mixed
     * @throws Exception
     */
    public function first()
    {
        $this->queryBuilder['limit'] = " LIMIT 1";
        return $this->prepareQuery()->fetchObject();
    }

    /**
     * Aggregate count function
     * @param string $column
     * @return mixed
     * @throws Exception
     */
    public function count(string $column='*')
    {
        return $this->aggregateHelper("COUNT({$column})");
    }

    /**
     * Aggregate min function
     * @param string $column
     * @return mixed
     * @throws Exception
     */
    public function min(string $column)
    {
        return $this->aggregateHelper("MIN({$column})");
    }

    /**
     * Aggregate max function
     * @param string $column
     * @return mixed
     * @throws Exception
     */
    public function max(string $column)
    {
        return $this->aggregateHelper("MAX({$column})");
    }

    /**
     * Aggregate average function
     * @param string $column
     * @return mixed
     * @throws Exception
     */
    public function avg(string $column)
    {
        return $this->aggregateHelper("AVG({$column})");
    }

    /**
     * Aggregate sum function
     * @param string column
     * @throws Exception
     */
    public function sum(string $column)
    {
        return $this->aggregateHelper("SUM({$column})");
    }

    /**
     * Build a subquery within the current query
     */
    public function subQuery($callable)
    {
        return $this;
    }

    /**
     * Builds the full query before execution
     * @return string
     * @throws Exception
     */
    public function toSql()
    {
        if(!isset($this->queryBuilder['table'])) throw new Exception('Table not found');

        $baseQuery = $this->getBaseQuery();

        if(isset($this->queryBuilder['joins']) && ($this->queryType === 'select')) {
            foreach ($this->queryBuilder['joins'] as $key => $join) {
                $baseQuery .= " {$join}";
            }
        }
        
        if(
            isset($this->queryBuilder['wheres']) &&
            in_array($this->queryType,['select', 'update', 'delete'])
        ) {
            foreach ($this->queryBuilder['wheres'] as $key => $condition) {
                $baseQuery .= ' '.rtrim($condition);
            }
        }

        if(isset($this->queryBuilder['groupBy']) && ($this->queryType === 'select')) {
            foreach ($this->queryBuilder['groupBy'] as $key => $groupBy) {
                $baseQuery .= $groupBy;
            }

            if(isset($this->queryBuilder['havings'])) {
                foreach ($this->queryBuilder['havings'] as $key => $condition) {
                    $baseQuery .= ' '.rtrim($condition);
                }
            }
        }

        if(
            isset($this->queryBuilder['orderBy']) &&
            in_array($this->queryType,['select', 'update', 'delete'])
        ) {
            foreach ($this->queryBuilder['orderBy'] as $key => $orderBy) {
                $baseQuery .= $orderBy;
            }
        }

        if(
            isset($this->queryBuilder['limit']) &&
            in_array($this->queryType,['select', 'update', 'delete'])
        ) {
            $baseQuery .= $this->queryBuilder['limit'];
        }

        if(isset($this->queryBuilder['unions'])) {
            foreach ($this->queryBuilder['unions'] as $key => $value) {
                $baseQuery .= ' UNION '.$value;
            }
            
        }

        $this->destroyQueryBuilder();
        return $baseQuery;
    }

    /**
     * Generate and return base query
     * @return string
     */
    private function getBaseQuery()
    {
        switch ($this->queryType) {
            case 'select':
                $this->baseQuery = $this->getSelectStatement() . ' ' . $this->queryBuilder['table'];
                break;
            case 'update':
                $this->baseQuery = "UPDATE ".$this->queryBuilder['table']." SET ".$this->queryBuilder['update'];
                break;
            case 'delete':
                $this->baseQuery = "DELETE FROM ".$this->queryBuilder['table'];
                break;
            default:
                break;
        }
        return $this->baseQuery;
    }

    /**
     * Set placeholders instead of direct value in sql query
     * @param array $values
     * @return string
     */
    private function placeholders(array $values)
    {
        return str_repeat('?, ', count($values)-1).'?';
    }

    private function condition($type, $clauseType='WHERE')
    {
        if(isset($this->queryBuilder[$clauseType === 'WHERE' ? 'wheres' : 'havings'])) {
            $clauseType = $type;
        }
        return $clauseType;
    }

    /**
     * Generate query result from a aggregate function
     * @param string $replacement
     * @return mixed
     * @throws Exception
     */
    private function aggregateHelper(string $replacement)
    {
        if(isset($this->queryBuilder['placeholdersValue'])) {
            $values = $this->queryBuilder['placeholdersValue'];
            $query = str_replace('*', $replacement, $this->toSql());
            $stmt = $this->connection->prepare($query);
            $stmt->execute($values);
        } else {
            $query = str_replace('*', $replacement, $this->toSql());
            $stmt = $this->connection->query($query);
        }
        if(!$stmt) {
            throw new Exception('Invalid query executed');
        }

        return $stmt->fetchObject()->$replacement;
    }

    /**
     * Prepare and execute the given query
     * @return object
     * @throws Exception
     */
    private function prepareQuery()
    {
        if(isset($this->queryBuilder['placeholdersValue'])) {
            $values = $this->queryBuilder['placeholdersValue'];
            $stmt = $this->connection->prepare($this->toSql());
            $stmt->execute($values);
        } else {
            $stmt = $this->connection->query($this->toSql());
        }
        return $stmt;
    }

    /**
     * Reset the query builder array
     * @return void
     */
    private function destroyQueryBuilder()
    {
        $this->queryBuilder = [];
    }

    /**
     * Insert a single record in table
     * @param array $data
     * @return object
     * @throws Exception
     */
    public function insert(array $data)
    {
        $columns = '';
        $values = [];
        $schema = new \stdClass;
        foreach ($data as $column => $value) {
            $columns = ($columns === '') ? "{$column}": "{$columns}, {$column}";
            $values[] = $value;
            $schema->$column = $value;
        }
        $result = $this->insertSafely($columns, $values, $this->insertableValues($values, 'VALUES'));
        if(!$result) {
            return $schema;
        }
        $schema->id = $this->connection->lastInsertId();
        return $schema;
    }

    /**
     * Batch insert records in table
     * @param array $data
     * @return bool
     * @throws Exception
     */
    public function batchInsert(array $data)
    {
        $columns = '';
        $values = [];
        $insertString = '';
        foreach ($data as $key => $value) {
            if($key === 0) {
                $columns = implode(',', array_keys($value));
                $insertString .= $this->insertableValues($value, 'VALUES');
            } else {
                $insertString .= ", ".$this->insertableValues($value);
            }
            $values = array_merge($values, array_values($value));
        }
        return $this->insertSafely($columns, $values, $insertString, 'bulk');
    }

    /**
     * Insert Data with prepared statements
     * @param string $columns
     * @param array $values
     * @param string $insertString
     * @param string $insertionType
     * @return bool
     */
    private function insertSafely(string $columns, array $values, string $insertString, string $insertionType='single')
    {
        $query = "INSERT INTO ".$this->queryBuilder['table']." ($columns) {$insertString}";
        $stmt = $this->connection->prepare($query);
        $stmt->execute($values);
        return true;
    }

    /**
     * Build insertable data format
     * @param array $values
     * @param string $query
     * @return string
     */
    private function insertableValues(array $values, string $query='')
    {
        return "{$query}(".str_repeat('?,', (count($values)-1))."?)";
    }

    /**
     * Delete record from table
     * @return int
     * @throws Exception
     */
    public function delete()
    {
        $this->queryType = 'delete';
        return $this->prepareQuery()->rowCount();
    }

    /**
     * Update record in database
     * @param array $data
     * @return int
     * @throws Exception
     */
    public function update(array $data)
    {
        $this->queryType = 'update';
        $this->queryBuilder['update'] = '';
        $values = [];
        foreach ($data as $key => $value) {
            $this->queryBuilder['update'] .= "{$key}=?, ";
            $values[] = $value;
        }

        if (isset($this->queryBuilder['placeholdersValue'])) {
            foreach ($this->queryBuilder['placeholdersValue'] as $value) {
                $values[] = $value;
            }
        }
        $this->queryBuilder['placeholdersValue'] = $values;
        unset($values);
        $this->queryBuilder['update'] = rtrim($this->queryBuilder['update'], ', ');
        return $this->prepareQuery()->rowCount();
    }

    /**
     * Add group by clause to query
     */
    public function groupBy($columns)
    {
        $columns = implode(',', func_get_args($columns));
        if(!isset($this->queryBuilder['groupBy'])) {
            $this->queryBuilder['groupBy'][] = " GROUP BY $columns";
        } else {
            $this->queryBuilder['groupBy'][] = ",$columns";
        }
        return $this;
    }

    /**
     * Add having clause in query
     * @param string|function $columnName
     * @param mixed $operator
     * @param $value
     * @return object
     */
    public function having($columnName, $operator='=', $value=null)
    {
        if (is_callable($columnName)) {
            $this->handleCallback($columnName, 'AND', 'HAVING');
        } else {
            if (is_array($columnName)) {
                foreach ($columnName as $key => $value) {
                    $this->buildHaving($key, '=', $value, 'AND');
                }
            } else {
                $this->buildHaving($columnName, $operator, $value, 'AND');
            }
        }
        return $this;
    }

    /**
     * Add OR in having clause of query
     * @param string|function $columnName
     * @param mixed $operator
     * @param $value
     * @return object
     */
    public function orHaving($columnName, $operator='=', $value=null)
    {
        if (is_callable($columnName)) {
            $this->handleCallback($columnName, 'OR', 'HAVING');
        } else {
            $this->buildHaving($columnName, $operator, $value, 'OR');
        }
        return $this;
    }

    /**
     * Add IN in having clause in query
     * @param string $columnName
     * @param array $values
     * @return object
     */
    public function havingIn(string $columnName, array $values)
    {
        $this->buildHavingIn($columnName, $values, 'AND');
        return $this;
    }

    /**
     * Add OR IN in having clause in query
     * @param string $columnName
     * @param array $values
     * @return object
     */
    public function orHavingIn(string $columnName, array $values)
    {
        $this->buildHavingIn($columnName, $values, 'OR');
        return $this;
    }

    /**
     * Add NOT IN with having clause
     * @param string $columnName
     * @param array $values
     * @return object
     */
    public function havingNotIn(string $columnName, array $values)
    {
        $this->buildHavingIn($columnName, $values, 'AND', 'NOT IN');
        return $this;
    }

    /**
     * Add OR NOT IN with having clause
     * @param string $columnName
     * @param array $values
     * @return object
     */
    public function orHavingNotIn(string $columnName, array $values)
    {
        $this->buildHavingIn($columnName, $values, 'OR', 'NOT IN');
        return $this;
    }

    /**
     * Add BETWEEN with having clause.
     * @param $columnName
     * @param array $values
     * @return object
     */
    public function havingBetween($columnName, array $values)
    {
        $this->buildHavingBetween($columnName, $values, 'AND');
        return $this;
    }

    /**
     * Add NOT BETWEEN with having clause.
     * @param $columnName
     * @param array $values
     * @return object
     */
    public function havingNotBetween($columnName, array $values)
    {
        $this->buildHavingBetween($columnName, $values, 'AND', 'NOT BETWEEN');
        return $this;
    }

    /**
     * Add OR BETWEEN with having.
     * @param $columnName
     * @param array $values
     * @return object
     */
    public function orHavingBetween($columnName, array $values)
    {
        $this->buildHavingBetween($columnName, $values, 'OR');
        return $this;
    }

    /**
     * Add OR NOT BETWEEN with having clause.
     * @param $columnName
     * @param array $values
     * @return object
     */
    public function orHavingNotBetween($columnName, array $values)
    {
        $this->buildHavingBetween($columnName, $values, 'OR', 'NOT BETWEEN');
        return $this;
    }

    /**
     * Add NULL with having clause
     * @param $columnName
     * @return object
     */
    public function havingNull($columnName)
    {
        $this->buildHavingNull($columnName, 'AND');
        return $this;
    }

    /**
     * Add OR NULL with having condition
     * @param $columnName
     * @return object
     */
    public function orHavingNull($columnName)
    {
        $this->buildHavingNull($columnName, 'OR');
        return $this;
    }

    /**
     * Add NOT NULL with having clause
     * @param $columnName
     * @return object
     */
    public function havingNotNull($columnName)
    {
        $this->buildHavingNull($columnName, 'AND', 'NOT NULL');
        return $this;
    }

    /**
     * Add OR NOT NULL with having clause
     * @param $columnName
     * @return object
     */
    public function orHavingNotNull($columnName)
    {
        $this->buildHavingNull($columnName, 'OR', 'NOT NULL');
        return $this;
    }

    private function buildHaving(string $columnName, $operator, $value, $type)
    {
        if (is_string($operator)) {
            if (!in_array($operator, $this->operators)) {
                $value = $operator;
                $operator = '=';
            }
        } else {
            $value = $operator;
            $operator = '=';
        }
        if (isset($this->queryBuilder['havings']) && end($this->queryBuilder['havings']) === '(') {
            $this->queryBuilder['havings'][] = "{$columnName} {$operator} ?";
        } else {
            $this->queryBuilder['havings'][] = $this->condition($type, 'HAVING')." {$columnName} {$operator} ?";
        }

        $this->queryBuilder['placeholdersValue'][] = $value;
    }

    private function buildHavingIn(string $columnName, array $values, $type, $operator='IN')
    {
        $this->queryBuilder['havings'][] = $this->condition($type, 'HAVING')." {$columnName} {$operator} (".$this->placeholders($values).")";
        foreach ($values as $key => $value) {
            $this->queryBuilder['placeholdersValue'][] = $value;
        }
    }

    private function buildHavingBetween($columnName, array $values, $type, $operator='BETWEEN')
    {
        $this->queryBuilder['havings'][] = $this->condition($type, 'HAVING')." {$columnName} {$operator} ? AND ?";
        foreach ($values as $key => $value) {
            $this->queryBuilder['placeholdersValue'][] = $value;
        }
    }

    private function buildHavingNull($columnName, $type, $operator='NULL')
    {
        $this->queryBuilder['havings'][] = $this->condition($type, 'HAVING')." {$columnName} IS {$operator}";
    }

    /**
     * Add union clause to query
     * @param Hraw\DBQB\QueryBuilder $queryInstance
     * @return object
     */
    public function union($queryInstance)
    {
        if (isset($queryInstance->queryBuilder['placeholdersValue'])) {
            $this->queryBuilder['placeholdersValue'] = array_merge(
                $this->queryBuilder['placeholdersValue'], 
                $queryInstance->queryBuilder['placeholdersValue']
            );
        }
        
        $this->queryBuilder['unions'][] = $queryInstance->toSql();
        return $this;
    }

    /**
     * Add union all clause to query
     * @param Hraw\DBQB\QueryBuilder $queryInstance
     * @return object
     */
    public function unionAll($queryInstance)
    {
        if (isset($queryInstance->queryBuilder['placeholdersValue'])) {
            $this->queryBuilder['placeholdersValue'] = array_merge(
                $this->queryBuilder['placeholdersValue'], 
                $queryInstance->queryBuilder['placeholdersValue']
            );
        }
        
        $this->queryBuilder['unions'][] = 'All '.$queryInstance->toSql();
        return $this;
    }

}