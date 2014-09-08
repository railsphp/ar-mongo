<?php
namespace Rails\ActiveRecord\Mongo\Associations;

use MongoDBRef;
use Rails\ActiveRecord\Mongo\Base;
// use Rails\ActiveRecord\Associations\CollectionProxy;

class Setter
{
    public function set(Base $record, $name, $value)
    {
        if ($value !== null && !$value instanceof Base) {
            if (is_object($value)) {
                $message = sprintf(
                    "Must pass instance of Rails\ActiveRecord\Mongo\Base as value, instance of %s passed",
                    get_class($value)
                );
            } else {
                $message = sprintf(
                    "Must pass either null or instance of Rails\ActiveRecord\Mongo\Base as value, %s passed",
                    gettype($value)
                );
            }
            throw new Exception\InvalidArgumentException($message);
        }
        
        $options = $record->getAssociations()->get($name);
        
        switch ($options['type']) {
            case 'belongsTo':
                if ($value) {
                    $this->matchClass($value, $options['className']);
                    
                    $dbref = MongoDBRef::create(
                        $value->collectionName(),
                        $value->id(),
                        $value->connection()->databaseName()
                    );
                } else {
                    $dbref = null;
                }
                
                $record->setAttribute($options['reference'], $dbref);
                break;
            
            case 'hasOne':
                $refAttr = $options['reference'];
                
                if ($value) {
                    $this->matchClass($value, $options['className']);
                    
                    $dbref = MongoDBRef::create(
                        $record->collectionName(),
                        $record->id(),
                        $record->connection()->databaseName()
                    );
                    
                    $value->setAttribute($refAttr, $dbref);
                }
                
                if ($record->isNewRecord()) {
                    return;
                }
                
                $oldValue = $record->getAssociation($name);
                
                if (
                    $value && $oldValue &&
                    (string)$value->getAttribute($refAttr) == (string)$oldValue->getAttribute($refAttr)
                ) {
                    return;
                }
                
                if ($oldValue) {
                    $oldValue->setAttribute($refAttr, null);
                }
                
                if ($value && $oldValue) {
                    $saved = $value->isValid() && $oldValue->isValid();
                } else {
                    $saved = true;
                }
                
                if ($saved && $value) {
                    if (!$value->save()) {
                        $saved = false;
                    }
                }
                
                if ($saved && $oldValue) {
                    if (!$oldValue->save()) {
                        $saved = false;
                    }
                }
                
                if (!$saved) {
                    throw new RecordNotSavedException(
                        sprinf(
                            "Failed to save new associated %s",
                            strtolower(
                                $record::getService('inflector')->underscore($name)->humanize()
                            )
                        )
                    );
                }
                break;
        }
        
        return true;
    }
    
    protected function matchClass($object, $targetClass)
    {
        if (get_class($object) != $targetClass) {
            throw new TypeMissmatchException(
                sprintf(
                    "Expected instance of %s, got %s",
                    get_class($object)
                )
            );
        }
    }
}
