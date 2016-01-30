<?php
/**
 * Base
 *
 * @author Virgil-Adrian Teaca - virgil@giulianaeassociati.com
 * @version 3.0
 * @date January 14th, 2016
 */

namespace Nova\ORM;

use Nova\Database\Connection;
use Nova\Database\Manager as Database;

use Nova\ORM\Model;

use PDO;


class Builder
{
    protected $connection = 'default';

    protected $db = null;

    protected $query = null;

    /**
     * The model being queried.
     *
     * @var \Nova\ORM\Model
     */
    protected $model = null;

    protected $table;

    protected $primaryKey;

    /**
     * The Table Metadata.
     */
    protected $fields = array();

    /**
     * The cached information is stored there.
     */
    protected static $cache = array();

    /**
     * The methods that should be returned from Query Builder.
     *
     * @var array
     */
    protected $passthru = array(
        'insert',
        'insertIgnore',
        'replace',
        'update',
        'updateOrInsert',
        'delete',
        'count',
        'query',
        'addTablePrefix'
    );


    public function __construct(Model $model, $connection = null)
    {
        $this->table = $model->getTable();

        $this->primaryKey = $model->getKeyName();

        // Setup the parent Model.
        $this->model = $model;

        // Setup the Connection name.
        $this->connection = ($connection !== null) ? $connection : $model->getConnection();

        // Setup the Connection instance.
        $this->db = Database::getConnection($this->connection);

        // Setup the inner Query Builder instance.
        $this->query = $this->newBaseQuery();

        // Finally, we initialize the Builder instance.
        $this->initialize();
    }

    protected function initialize()
    {
        $fields = $this->model->getTableFields();

        if(! empty($fields)) {
            // The fields are specified by the programmer, directly into Model.
            $this->fields = $fields;

            return;
        }

        // Prepare the Cache token.
        $token = $this->connection .'_' .$this->table;

        if($this->hasCached($token)) {
            // The fields are already cached by a previous Builder instance.
            $this->fields = $this->getCache($token);

            return;
        }

        $table = $this->query->addTablePrefix($this->table, false);

        // Get the Table fields directly from Connection instance.
        $fields = $this->db->getTableFields($table);

        // We use only the keys of Table information array.
        $this->fields = array_keys($fields);

        // Cache the Table fields for the further use.
        $this->setCache($token, $this->fields);
    }

    public function getTable()
    {
        return $this->table;
    }

    public function table()
    {
        return $this->query->addTablePrefix($this->table);
    }

    public function getTableFields()
    {
        return $this->fields;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function getLink()
    {
        return $this->db;
    }

    public static function hasCached($token)
    {
        return isset(self::$cache[$token]);
    }

    public static function setCache($token, $value)
    {
        self::$cache[$token] = $value;
    }

    public static function getCache($token)
    {
        return isset(self::$cache[$token]) ? self::$cache[$token] : null;
    }

    //--------------------------------------------------------------------
    // Query Builder Methods
    //--------------------------------------------------------------------

    public function newBaseQuery()
    {
        $table = $this->getTable();

        $query = $this->db->getQueryBuilder();

        return $query->table($table);
    }

    public function getBaseQuery()
    {
        return $this->query;
    }

    //--------------------------------------------------------------------
    // CRUD Methods
    //--------------------------------------------------------------------

    public function find($id, $fieldName = null)
    {
        // We use a new Query to perform this operation.
        $query = $this->newBaseQuery();

        // Get the field name, using the primaryKey as default.
        $fieldName = ($fieldName !== null) ? $fieldName : $this->primaryKey;

        $result = $query->where($fieldName, $id)->asAssoc()->first();

        if($result !== false) {
            return $this->model->newFromBuilder($result);
        }

        return false;
    }

    public function findBy()
    {
        $params = func_get_args();

        if (empty($params)) {
            throw new \UnexpectedValueException(__d('system', 'Invalid parameters'));
        }

        $query = call_user_func_array(array($this->query, 'where'), $params);

        $result = $query->asAssoc()->first();

        if($result !== false) {
            return $this->model->newFromBuilder($result);
        }

        return false;
    }

    public function findMany($values)
    {
        $query = $this->newBaseQuery();

        $data = $query->asAssoc()->findMany($this->primaryKey, $values);

        if($data === false) {
            return false;
        }

        // Prepare and return an instances array.
        $result = array();

        foreach($data as $row) {
            $result[] = $this->model->newFromBuilder($row);
        }

        return $result;
    }

    public function findAll()
    {
        // Prepare the WHERE parameters.
        $params = func_get_args();

        if (! empty($params)) {
            $query = call_user_func_array(array($this->query, 'where'), $params);
        } else {
            $query = $this->query;
        }

        $data = $query->asAssoc()->get();

        if($data === false) {
            return false;
        }

        // Prepare and return an instances array.
        $result = array();

        foreach($data as $row) {
            $result[] = $this->model->newFromBuilder($row);
        }

        return $result;
    }

    public function first()
    {
        $data = $this->query->asAssoc()->first();

        if($data !== false) {
            return $this->model->newFromBuilder($data);
        }

        return false;
    }

    public function updateBy()
    {
        $params = func_get_args();

        $data = array_pop($params);

        if (empty($params) || empty($data)) {
            throw new \UnexpectedValueException(__d('system', 'Invalid parameters'));
        }

        $query = call_user_func_array(array($this->query, 'where'), $params);

        return $query->update($data);
    }

    public function deleteBy()
    {
        $params = func_get_args();

        if (empty($params)) {
            throw new \UnexpectedValueException(__d('system', 'Invalid parameters'));
        }

        $query = call_user_func_array(array($this->query, 'where'), $params);

        return $query->delete();
    }

    /**
     * Counts number of rows modified by an arbitrary WHERE call.
     * @return INT
     */
    public function countBy()
    {
        $params = func_get_args();

        if (empty($params)) {
            throw new \UnexpectedValueException(__d('system', 'Invalid parameters'));
        }

        // We use a new Query to perform this operation.
        $query = $this->newBaseQuery();

        call_user_func_array(array($query, 'where'), $params);

        return $query->count();
    }

    /**
     * Counts total number of records, disregarding any previous conditions.
     *
     * @return int
     */
    public function countAll()
    {
        // We use a new Query to perform this operation.
        $query = $this->newBaseQuery();

        return $query->count();
    }

    //--------------------------------------------------------------------
    // Utility Methods
    //--------------------------------------------------------------------

    /**
     * Checks whether a field/value pair exists within the table.
     *
     * @param string $field The field to search for.
     * @param string $value The value to match $field against.
     * @param string $ignore Optionally, the ignored primaryKey.
     *
     * @return bool TRUE/FALSE
     */
    public function isUnique($field, $value, $ignore = null)
    {
        // We use a new Query to perform this operation.
        $query = $this->newBaseQuery();

        $query->where($field, $value);

        if ($ignore !== null) {
            $query->where($this->primaryKey, $ignore);
        }

        $result = $query->count();

        if($result == 0) {
            return true;
        }

        return false;
    }

    //--------------------------------------------------------------------
    // Magic Methods
    //--------------------------------------------------------------------

    /**
     * Handle dynamic method calls into the method.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $result = call_user_func_array(array($this->query, $method), $parameters);

        return in_array($method, $this->passthru) ? $result : $this;
    }

}
