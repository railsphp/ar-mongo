<?php
namespace Rails\ActiveRecord\Mongo\Associations;

use ReflectionClass;
use Rails\ActiveSupport\ParentMethods;
use Rails\ServiceManager\ServiceLocatorAwareTrait;

class Extractor
{
    use ServiceLocatorAwareTrait;
    
    /**
     * @param string $class The model's class that extends AR\Base
     */
    public function getAssociations($class)
    {
        $data = [
            'embedded' => []
        ];
        
        if (!isset($this->associations[$class])) {
            $assocsByType = $this->getAssociationsData($class);
        }
        
        foreach ($assocsByType as $type => $associations) {
            if ($type == 'parents') {
                $data[$type] = $associations;
                continue;
            }
            
            foreach ($associations as $assocName => $options) {
                if (is_int($assocName)) {
                    $assocName = $options;
                    $options   = [];
                }
                
                $data[$assocName] = $this->normalizeAssociationOptions($type, $assocName, $options, $class);
                
                if (!empty($options['embedded'])) {
                    $data['embedded'][$assocName] = $type;
                }
            }
        }
        
        return $data;
    }
    
    protected function getAssociationsData($class)
    {
        $parentMethods = new ParentMethods();
        
        $regex = '/.+Associations$/';
        
        $closures = $parentMethods->getClosures(
            $class,
            function($method, $currentClass) use ($regex) {
                return $method->getDeclaringClass() == $currentClass->getName() &&
                        (bool)preg_match($regex, $method->getName());
            },
            'Rails\ActiveRecord\Mongo\Base'
        );
        
        $refl   = new ReflectionClass($class);
        $instance = $refl->newInstanceWithoutConstructor();
        $method = $refl->getMethod('associations');
        $method->setAccessible(true);
        
        $associations = $method->invoke($instance);
        
        foreach ($closures as $closure) {
            $associations = array_merge_recursive($associations, $closure->invoke($instance));
        }
        
        return $associations;
    }
    
    

    protected function normalizeAssociationOptions($type, $name, array $options, $class)
    {
        $options['type']    = $type;
        $inflector          = $this->getService('inflector');
        
        if (!isset($options['className'])) {
            switch ($type) {
                case 'hasMany':
                case 'hasAndBelongsToMany':
                    $options['className'] = ucfirst($inflector->singularize($name));
                    break;
                
                case 'belongsTo':
                    if (!empty($options['polymorphic'])) {
                        break;
                    }
                
                default:
                    $options['className'] = ucfirst($name);
                    break;
            }
        }
        
        switch ($type) {
            case 'belongsTo':
                if (!isset($options['reference'])) {
                    $options['reference'] = lcfirst($name);
                }
                break;
            
            case 'hasOne':
                if (!isset($options['reference'])) {
                    $options['reference'] = $inflector->singularize(
                        $class::collectionName()
                    );
                }
                break;
            
            case 'hasMany':
                if (!isset($options['reference'])) {
                    $options['reference'] = $inflector->singularize(
                        $class::collectionName()
                    );
                }
                break;
            
            case 'hasAndBelongsToMany':
                # TODO
                // if (empty($options['joinTable'])) {
                    // $prefix = null;
                    // $tableNames = [ $class::collectionName(), $options['className']::collectionName() ];
                    
                    // sort($tableNames);
                    
                    // $joinTable = implode('_', $tableNames);
                    
                    // if ($prefix) {
                        // $joinTable = $prefix . $joinTable;
                    // }
                    
                    // $options['joinTable'] = $joinTable;
                // }
                
                // if (empty($options['foreignKey'])) {
                    // $options['foreignKey'] = 
                        // str_replace('\\', '', lcfirst($class)) . 'Id';
                // }
                // if (empty($options['associationForeignKey'])) {
                    // $options['associationForeignKey'] =
                        // str_replace('\\', '', lcfirst($options['className'])) . 'Id';
                // }
                break;
        }
        
        return $options;
    }
}
