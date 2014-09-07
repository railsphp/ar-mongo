<?php
namespace Rails\ActiveRecord\Mongo;

use MongoCursor;
use MongoId;
use Rails\ActiveRecord\Mongo\Attributes\Attributes;
use Rails\ActiveRecord\Exception\RecordNotFoundException;
use Rails\ActiveRecord\Relation\AbstractRelation;
use Rails\ActiveRecord\Collection;
use Rails\ActiveRecord\Relation\RelationInterface;

class Relation implements \IteratorAggregate, \Countable, RelationInterface
{
    protected $modelClass;
    
    protected $select = [];
    
    protected $from;
    
    protected $where = [];
    
    protected $order = [];
    
    /**
     * @var int
     */
    protected $limit;
    
    /**
     * @var int
     */
    protected $offset;
    
    /**
     * @var Collection
     */
    protected $records;
    
    protected $loaded = false;
    
    protected $deleted = false;
    
    public function __construct($modelClass)
    {
        $this->modelClass = $modelClass;
        $this->from = $modelClass::collectionName();
    }
    
    public function __call($method, $params)
    {
        $this->load();
        return call_user_func_array([$this->records, $method], $params);
    }
    
    public function getIterator()
    {
        $this->load();
        return $this->records;
    }
    
    /**
     * ```php
     * // Select name field.
     * $rel->select('name');
     * $rel->select(['name' => true]);
     * // Discard name.
     * $rel->select(['name' => false]);
     * // Select name and discard address. Note that 0 is passed.
     * $rel->select(['name', 'address' => 0]);
     * // Same as above but explicit.
     * $rel->select(['name' => 1, 'address' => 0]);
     * ```
     */
    public function select($params)
    {
        if (!is_array($params)) {
            $params = func_get_args();
        }
        $rel = $this->currentOrClone();
        $rel->select = array_merge($rel->select, $this->normalizeSelect($params));
        return $rel;
    }
    
    public function from($collection)
    {
        $rel = $this->currentOrClone();
        $rel->from = $collection;
        return $rel;
    }
    
    public function where(array $criteria)
    {
        $rel = $this->currentOrClone();
        $rel->where = array_merge($rel->where, $criteria);
        return $rel;
    }
    
    public function order($field, $direction = 1)
    {
        if (is_array($field)) {
            $order = $field;
        } elseif (is_string($field)) {
            $order = [$field => $direction];
        } else {
            throw new Exception\InvalidArgumentException(sprintf(
                "First argument must be array or string, %s passed",
                gettype($field)
            ));
        }
        
        $rel = $this->currentOrClone();
        $rel->order = array_merge($rel->order, $order);
        return $rel;
    }
    
    public function limit($limit)
    {
        $rel = $this->currentOrClone();
        $rel->limit = $limit;
        return $rel;
    }
    
    public function offset($offset)
    {
        $rel = $this->currentOrClone();
        $rel->offset = $offset;
        return $rel;
    }
    
    public function deleted($value = true)
    {
        $rel = $this->currentOrClone();
        $rel->deleted = $value;
        return $rel;
    }
    
    public function merge(self $other)
    {
        
    }
    
    public function records()
    {
        $this->load();
        return $this->records;
    }
    
    public function load()
    {
        if (!$this->loaded) {
            $this->records = $this->buildCollection($this->loadRecords());
            $this->loaded  = true;
        }
        return $this;
    }
    
    public function getCursor()
    {
        return $this->createCursor();
    }
    
    public function first($limit = 1)
    {
        $rel = $this->currentOrClone();
        $rel->limit($limit);
        $rel->orderByIdIfUnordered();
        
        $records = $rel->loadRecords();
        
        if (count($records)) {
            if ($limit == 1) {
                return $this->buildSingleModel(current($records));
            } else {
                return $this->buildCollection($records);
            }
        }
        return null;
    }
    
    public function take($limit = 1)
    {
        $rel = $this->currentOrClone();
        $rel->limit($limit);
        $records = $rel->loadRecords();
        
        if ($records) {
            if ($limit == 1) {
                return $this->buildSingleModel(current($records));
            } else {
                return $this->buildCollection($records);
            }
        }
        
        return null;
    }
    
    public function pluck($columnName/*...$columnNames*/)
    {
        if (is_array($columnName)) {
            $columnNames = $columnName;
        } else {
            $columnNames = func_get_args();
        }
        
        $rel = $this->currentOrClone();
        $rel->select = $columnNames;
        $records = $rel->loadRecords();
        
        if (count($columnNames) == 1) {
            return array_map(function($x) {
                return current($x);
            }, $records);
        } else {
            return $records;
        }
    }
    
    public function count()
    {
        return $this->createCursor()->count();
    }
    
    protected function orderByIdIfUnordered()
    {
        if (!$this->order) {
            $modelClass = $this->modelClass;
            $this->order([$modelClass::primaryKey() => 1]);
        }
    }
    
    public function find($id)
    {
        $rel = $this->currentOrClone();
        $modelClass = $this->modelClass;
        
        if (Attributes::getAttributesFor($modelClass)->getAttribute($modelClass::primaryKey())->type() == 'mongoId') {
            if (!$id instanceof MongoId) {
                $id = new MongoId($id);
            }
        }
        
        $first = $rel->where([$modelClass::primaryKey() => $id])->first();
        
        if (!$first) {
            throw new RecordNotFoundException(sprintf(
                "Couldn't find %s with %s=%s",
                $this->modelClass,
                $modelClass::primaryKey(),
                $id
            ));
        }
        return $first;
    }
    
    /**
     * Helps when we want to search by _id. Pass a string and it will
     * converted to MongoId automatically.
     *
     * @var string|MongoId $id
     */
    public function withId($id)
    {
        if (is_string($id)) {
            $id = new MongoId($id);
        } elseif (!$id instanceof MongoId) {
            throw new Exception\InvalidArgumentException(sprintf(
                "Argument must be either string or instance of MongoId, %s passed",
                gettype($id)
            ));
        }
        
        $rel = $this->currentOrClone();
        $rel->where(['_id' => $id]);
        return $rel;
    }
    
    public function paginate($page, $perPage = null)
    {
        $page = (int)$page;
        if ($page < 1) {
            $page = 1;
        }
        
        if (!$perPage) {
            $perPage = $this->limit;
        }
        
        $skip = ($page - 1) * $perPage;
        
        $where = $this->selectDeletedRecords($this->where);
        
        $cursor = $this->createCursor($where);
        $cursor->skip($skip);
        $cursor->limit($perPage);
        $records = $this->loadRecords($cursor);
        $collection = $this->buildCollection($records);
        
        $collection->setTotalRows($cursor->count());
        $collection->setPerPage($perPage);
        $collection->setPage($page);
        
        return $collection;
    }
    
    protected function createCursor(array $where = [])
    {
        if (!$where) {
            $where = $this->where;
        }
        $modelClass = $this->modelClass;
        $mongoColl  = $modelClass::connection()->collection($this->from);
        
        if ($where) {
            $cursor = $mongoColl->find($where);
        } else {
            $cursor = $mongoColl->find();
        }
        
        if ($this->select) {
            $cursor->fields($this->select);
        }
        if ($this->offset) {
            $cursor->skip($this->offset);
        }
        if ($this->limit) {
            $cursor->limit($this->limit);
        }
        return $cursor;
    }
    
    protected function loadRecords(MongoCursor $cursor = null)
    {
        if (!$cursor) {
            $where = $this->selectDeletedRecords($this->where);
            $cursor = $this->createCursor($where);
        }
        
        $records = [];
        foreach ($cursor as $record) {
            $records[] = $record;
        }
        return $records;
    }
    
    protected function buildCollection(array $records)
    {
        $members    = [];
        $modelClass = $this->modelClass;
        $ownerPk    = $modelClass::primaryKey();
        $rowsPks    = array_map(function($record) use ($ownerPk) { return $record[$ownerPk]; }, $records);
        
        foreach ($records as $record) {
            $members[(string)$record[$ownerPk]] = new $modelClass($record, false);
        }
        
        // if ($this->includes) {
            // foreach ($this->includes as $assocName => $data) {
                // $this->buildInclude($assocName, $data, $members, $rowsPks);
            // }
            // $this->includes = [];
        // }
        
        return $modelClass::collection(array_values($members));
    }
    
    protected function buildSingleModel(array $record)
    {
        $modelClass = $this->modelClass;
        $model      = new $modelClass($record, false);
        return $model;
    }
    
    protected function currentOrClone()
    {
        if ($this->loaded) {
            return clone $this;
        }
        return $this;
    }
    
    protected function normalizeSelect(array $params)
    {
        $normalized = [];
        foreach ($params as $key => $value) {
            if (is_int($key)) {
                $normalized[$value] = true;
            } else {
                $normalized[$key] = (bool)$value;
            }
        }
        
        return $normalized;
    }
    
    protected function selectDeletedRecords(array $where)
    {
        $modelClass = $this->modelClass;
        if ($modelClass::isRecoverable()) {
            if (!$this->deleted) {
                $where[$modelClass::deletedAtAttribute()] = $modelClass::deletedAtEmptyValue();
            } elseif ($this->deleted === 'only') {
                $where[$modelClass::deletedAtAttribute()] = ['$exists' => true];
            }
        }
        return $where;
    }
}
