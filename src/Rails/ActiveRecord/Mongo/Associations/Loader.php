<?php
namespace Rails\ActiveRecord\Mongo\Associations;

use Rails\ActiveRecord\Associations\Loader as BaseLoader;
use Rails\ActiveRecord\Mongo\Embedded\Collection as EmbeddedArray;

class Loader extends BaseLoader
{
    public function load($record, $name, array $options)
    {
        switch ($options['type']) {
            case 'parentDoc':
                # TODO
                break;
        }
        
        return parent::load($record, $name, $options);
    }
    
    protected function loadHasOne($record, $name, array $options)
    {
        if (!empty($options['embedded'])) {
            $className  = $options['className'];
            $attributes = $record->getAttribute($name) ?: [];
            $relation   = new $className($attributes, $record);
            $record->getAttributes()->setRaw($name, $relation);
            return $relation;
        }
        
        $query = $this->buildQuery($options);
        $query->where([
            $options['reference'] . '.$id' => $record->id()
        ]);
        $first = $query->first();
        
        if ($first) {
            return $first;
        }
        
        return false;
    }
    
    protected function loadBelongsTo($record, $name, array $options)
    {
        if (empty($options['className'])) {
            if (empty($options['polymorphic'])) {
                $options['className'] = ucfirst($name);
            } else {
                $assocType = $name . 'Type';
                $options['className'] = $record->$assocType();
            }
        }
        
        $query = $this->buildQuery($options);
        $dbref = $record->getAttribute($options['reference']);
        
        if ($dbref) {
            return $query->withId($dbref['$id'])->first() ?: false;
        }
        
        return false;
    }
    
    # TODO
    protected function loadHasAndBelongsToMany($record, $name, array $options)
    {
        $query = $this->buildQuery($options, 'hasAndBelongsToMany', $record);
        // $query->where([$options['joinTable'] . '.' . $options['foreignKey']  => $record->id()]);
        // $query->join(
            // $options['joinTable'],
            // $options['className']::tableName() . '.' . $options['className']::primaryKey() . ' = ' . $options['joinTable'] . '.' . $options['associationForeignKey']
        // );
        
        return $query;
    }
    
    protected function loadHasMany($record, $name, $options)
    {
        if (!empty($options['embedded'])) {
            $members   = [];
            $objects   = $record->getAttribute($name);
            $className = $options['className'];
            
            foreach ($objects as $object) {
                if ($object === null) {
                    $members[] = null;
                } else {
                    $members[] = new $className($object, $record);
                }
            }
            
            $array = new EmbeddedArray($members, $name, $className, $record);
            $record->getAttributes()->setRaw($name, $array);
            return $array;
        }
        
        return parent::loadHasMany($record, $name, $options);
    }
    
    protected function buildQuery(array $options, $proxyKind = null, $record = null)
    {
        if ($proxyKind) {
            $query = new CollectionProxy($options['className'], $proxyKind, $record, $options['foreignKey']);
        } else {
            $query = $options['className']::all();
        }
        
        # options[0], if present, it's an anonymous function to customize the relation.
        # The relation object is passed to that function.
        if (isset($options[0])) {
            $lambda = array_shift($options);
            $lambda($query);
        }
        
        return $query;
    }
}
