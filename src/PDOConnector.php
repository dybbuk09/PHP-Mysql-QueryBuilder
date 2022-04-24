<?php

namespace Hraw\DBQB;

use Exception;
use PDO;

class PDOConnector
{
    private static $instance = null;

    private function __construct()
    {
        
    }

    /**
     * Create singleton instance of the class
     * @return PDOConnector|null
     */
    public function make()
    {
        if (is_null(self::$instance)) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     * Set database connection if not exists in connections stack
     * @param array $connection
     * @return mixed
     */
    public function setConnection(array $connection)
    {
        if (!isset(self::$instance->connections[$connection['name']])) {
            self::$instance->connections[$connection['name']] = new PDO(
                "{$connection['driver']}:dbname={$connection['database']};host={$connection['host']}",
                $connection['username'],
                $connection['password']
            );
            self::$instance->connections[$connection['name']]->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
        }
        return self::$instance->connections[$connection['name']];
    }

    /**
     * Retrieve database connection from connections stack
     * @return mixed
     * @throws Exception
     */
    public function getConnection(string $name)
    {
        if (isset(self::$instance->connections[$name])) {
            return self::$instance->connections[$name];
        }
        throw new Exception("Database connection {$name} not found");
    }

}