<?php
namespace Rails\ActiveRecord\Mongo\Associations;

use MongoDBRef;
use Rails\ActiveRecord\Mongo\Base;
use Rails\ActiveRecord\Mongo\Exception;
use Rails\ActiveRecord\Mongo\Document\DocumentInterface;

class Setter
{
    public function set(DocumentInterface $record, $name, $value)
    {
        if ($value !== null && !is_array($value) && !$value instanceof DocumentInterface) {
            if (is_object($value)) {
                $message = sprintf(
                    "Must pass instance of Rails\ActiveRecord\Mongo\Base as value, instance of %s passed",
                    get_class($value)
                );
            } else {
                $message = sprintf(
                    "Must pass either null, array or instance of Rails\ActiveRecord\Mongo\Base as value, %s passed",
                    gettype($value)
                );
            }
            throw new Exception\InvalidArgumentException($message);
        }
        
        $options = $record->getAssociations()->get($name);
        
        switch ($options['type']) {
            case 'belongsTo':
                if ($value) {
                    if (is_object($value)) {
                        $this->matchClass($value, $options['className']);
                        
                        $dbref = MongoDBRef::create(
                            $value->collectionName(),
                            $value->id(),
                            $value->connection()->databaseName()
                        );
                    } elseif (is_array($value)) {
                        if (!MongoDBRef::isRef($value)) {
                            throw new Exception\InvalidArgumentException(sprintf(
                                "Array passed to %s::%s association is not a reference",
                                get_class($record),
                                $name
                            ));
                        }
                        $dbref = $value;
                    } else {
                        throw new Exception\InvalidArgumentException(sprintf(
                            "Value passed to %s::%s association must be either object or array, %s passed",
                            gettype($value)
                        ));
                    }
                    
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
            
            case 'hasMany':
                if ($value instanceof Collection) {
                    $value = $value->toArray();
                } elseif (!is_array($value)) {
                    throw new Exception\InvalidArgumentException(sprintf(
                        "HasMany association accepts either array or instance of Mongo\Collection, %s passed",
                        gettype($value)
                    ));
                }
                
                if (!empty($options['embedded'])) {
                    $record->$name()->set($value);
                    break;
                }
                break;
            
            default:
                throw new Exception\InvalidArgumentException(sprintf(
                    "Can't set unsupported association type %s",
                    $options['type']
                ));
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
