<?php
namespace Rails\ActiveRecord\Mongo;

use MongoDBRef;

abstract class Base extends Document\Document
{
    const PRIMARY_KEY = '_id';
    
    const DELETED_AT_ATTRIBUTE = 'deletedAt';
    
    const COLLECTION_NAME = '';
    
    const ADD_ID_ATTRIBUTE = true;
    
    protected static $persistence;
    
    protected static $literalAttributeNames = true;
    
    protected static $connectionManager;
    
    public static function dbCollection()
    {
        return static::connection()->getCollection(static::collectionName());
    }
    
    public static function withId($id)
    {
        return self::all()->withId($id);
    }
    
    protected static function getRelation()
    {
        return new Relation(get_called_class());
    }
    
    protected static function persistence()
    {
        if (!self::$persistence) {
            self::$persistence = new Persistence();
        }
        return self::$persistence;
    }
    
    public function dbRef()
    {
        if ($this->isPersisted()) {
            return [
                '$ref' => (string)$this->collectionName(),
                '$id'  => (string)$this->id(),
                '$db'  => (string)$this->connection()->databaseName()
            ];
        }
    }
    
    public function directUpdates(array $attrsValuesPairs)
    {
        return $this->persistence()->updateColumns($this, $attrsValuesPairs);
    }
    
    /**
     * Saves embedded associations.
     * Note that the success or failure is ignored unless an Exception
     * is thrown.
     *
     */
    public function save(array $options = [])
    {
        if (parent::save($options)) {
            foreach ($this->getAssociations()->embedded() as $name => $type) {
                if ($type == 'hasMany') {
                    $this->$name()->save();
                }
            }
            return true;
        }
        return false;
    }
    
    public function reload()
    {
        $this->loadedAssociations = [];
        return parent::reload();
    }
    
    public function hasChanged()
    {
        return $this->attributes->dirty()->hasChanged() || $this->embeddedAssocsChanged();
    }
    
    public function embeddedAssocsChanged()
    {
        $changed = false;
        foreach ($this->getAssociations()->embedded() as $name => $type) {
            $object = $this->getAssociation($name);
            
            if ($type == 'hasMany') {
                foreach ($object as $model) {
                    if ($model->hasChanged()) {
                        $changed = true;
                        break 2;
                    }
                }
            } else {
                if ($object->hasChanged()) {
                    $changed = true;
                    break;
                }
            }
        }
        return $changed;
    }
}
