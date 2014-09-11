<?php
namespace Rails\ActiveRecord\Mongo;

use MongoId;
use MongoDate;

class Persistence
{
    public function insert(Base $record)
    {
        $baseClass = get_class($record);
        $coll      = $baseClass::connection()->collection($baseClass::collectionName());
        $attrs     = $record->attributes();
        
        # Prepare array elements. Insert them as empty arrays.
        # TODO: prepare object elements for belongsTo associations.
        foreach ($record->getAssociations()->embedded() as $name => $type) {
            if ($type == 'hasMany') {
                if (!$record->getAssociation($name, false)) {
                    $attrs[$name] = [];
                } else {
                    unset($attrs[$name]);
                }
            } else {
                unset($attrs[$name]);
            }
        }
        
        if ($record->getAttributes()->isAttribute('createdAt')) {
            $attrs['createdAt'] = $this->createMongoDate();
        }
        
        $coll->insert($attrs);
        
        if (isset($attrs['_id'])) {
            return $attrs['_id'];
        }
        
        return true;
    }
    
    public function update(Base $record)
    {
        if (!$record->hasChanged()) {
            return true;
        }
        
        $values = [];
        foreach (array_keys($record->changedAttributes()) as $attrName) {
            $values[$attrName] = $record->getAttribute($attrName);
        }
        
        if ($record->getAttributes()->isAttribute('updatedAt')) {
            $dateAttr = 'updatedAt';
        } elseif ($record->getAttributes()->isAttribute('updatedOn')) {
            $dateAttr = 'updatedOn';
        } else {
            $dateAttr = false;
        }
        
        if ($dateAttr) {
            $mongoDate = $this->createMongoDate();
            $record->setAttribute($dateAttr, $mongoDate);
            $values[$dateAttr] = $mongoDate;
        }
        
        if ($this->updateRecord($record, $values)) {
            $record->getAttributes()->dirty()->changesApplied();
            return true;
        }
        return false;
    }
    
    public function updateColumns(Base $record, array $columnsValuesPairs)
    {
        if ($record->isNewRecord()) {
            throw new Exception\RuntimeException(
                "Can't update columns on a new record"
            );
        }
        
        return $this->updateRecord($record, $columnsValuesPairs);
    }
    
    public function delete(Base $record)
    {
        $baseClass = get_class($record);
        $coll = $baseClass::connection()->collection($baseClass::collectionName());
        return $coll->remove([$baseClass::primaryKey() => $record->id()]);
    }
    
    # TODO: Handle 'w' option if present.
    protected function updateRecord(Base $record, array $columnsValuesPairs)
    {
        $baseClass = get_class($record);
        $coll = $baseClass::connection()->collection($baseClass::collectionName());
        $id = $record->id();
        return $coll->update([$record::primaryKey() => $id], ['$set' => $columnsValuesPairs]);
    }
    
    protected function createMongoDate()
    {
        list($usec, $sec) = explode(" ", microtime());
        return new MongoDate((int)$sec, (float)$usec);
    }
}
