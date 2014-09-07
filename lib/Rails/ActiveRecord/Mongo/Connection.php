<?php
namespace Rails\ActiveRecord\Mongo;

use MongoClient;

class Connection
{
    /**
     * @var string
     */
    protected $databaseName;
    
    /**
     * @var MongoDB
     */
    protected $database;
    
    /**
     * @var string
     */
    protected $modelClass;
    
    /**
     * @var MongoClient
     */
    protected $resource;
    
    public function __construct(array $config = [])
    {
        if (!isset($config['server'])) {
            $config['server'] = null;
        }
        if (!isset($config['options'])) {
            $config['options'] = ['connect' => true];
        }
        $this->resource = new MongoClient($config['server'], $config['options']);
        
        if (isset($config['database'])) {
            $this->selectDb($config['database']);
        }
    }
    
    # Deprecated
    public function collection($collName)
    {
        if (!$this->database) {
            throw new Exception\LogicException(
                "Can't select collection as there's no database set"
            );
        }
        return $this->database->selectCollection($collName);
    }
    
    public function getCollection($collName)
    {
        if (!$this->database) {
            throw new Exception\LogicException(
                "Can't select collection as there's no database set"
            );
        }
        return $this->database->selectCollection($collName);
    }
    
    public function setModelClass($modelClass)
    {
        $this->modelClass = $modelClass;
        return $this;
    }
    
    public function selectDb($databaseName)
    {
        $this->database     = $this->resource->selectDb($databaseName);
        $this->databaseName = $databaseName;
    }
    
    public function database()
    {
        return $this->database;
    }
    
    public function databaseName()
    {
        return $this->databaseName;
    }

    public function execute($code)
    {
        return $this->database()->execute($code);
    }
    
    public function resource()
    {
        return $this->resource;
    }
}
