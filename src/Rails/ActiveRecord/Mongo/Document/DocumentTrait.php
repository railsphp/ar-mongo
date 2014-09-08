<?php
namespace Rails\ActiveRecord\Mongo\Document;

use ReflectionClass;
use Rails\ActiveModel\Attributes\Attributes;
use Rails\ActiveModel\Attributes\AccessibleProperties;
use Rails\ActiveRecord\Mongo\Attributes\Attribute;
use Rails\ActiveRecord\Mongo\Attributes\AttributeSet;
use Rails\ActiveRecord\Mongo\Associations\Associations;
use Rails\ActiveRecord\Mongo\Associations\AssociableModelTrait;

trait DocumentTrait
{
    use AssociableModelTrait;
    
    # Classes using this trait will have to declare the following constants:
    # const PRIMARY_KEY = '_id';
    # const DELETED_AT_ATTRIBUTE = 'deletedAt';
    # const COLLECTION_NAME = '';
    
    public static function collectionName()
    {
        if (static::COLLECTION_NAME) {
            return static::COLLECTION_NAME;
        } else {
            $cn  = str_replace('\\', '', get_called_class());
            $inf = self::services()->get('inflector');
            return $inf->pluralize($cn);
        };
    }
    
    public static function connectionName()
    {
        return self::connectionManager()->defaultConnection();
    }
    
    public static function connection()
    {
        return self::connectionManager()->getAdapter(
            static::connectionName()
        );
    }
    
    /**
     * In order to define attributes for a model, either this method can
     * be extended, or preferably, the `defineAttributes()` method may be used.
     * The `_id` attribute and the corresponding attributes for associations
     * are created automatically.
     *
     * @return Attributes\AttributeSet
     * @see defineAttributes()
     */
    protected static function attributeSet()
    {
        $set = new AttributeSet();
        if (static::ADD_ID_ATTRIBUTE) {
            $set->addAttribute(
                new Attribute('_id', 'mongoId')
            );
        }
        $refl = new ReflectionClass('Rails\ActiveRecord\Mongo\Attributes\Attribute');
        
        foreach (static::defineAttributes() as $attrOptions) {
            if (is_string($attrOptions)) {
                if ($attrOptions == 'timestamps') {
                    $set->timestamps();
                    continue;
                } elseif ($attrOptions == 'recoverable') {
                    $set->recoverable();
                    continue;
                } else {
                    $attrOptions = [$attrOptions];
                }
            }
            $set->addAttribute($refl->newInstanceArgs($attrOptions));
        }
        
        $associations = Associations::forClass(get_called_class());
        
        // $assocs =->embedded();
        // vpe($assocs);
        // if ($assocs['embedded']) {
            // foreach ($assocs['embedded'] as $name => $type) {
        foreach ($associations->embedded() as $name => $type) {
            $set->addAttribute(new Attribute($name, 'array'));
        }
        
        foreach ($associations->associations() as $name => $options) {
            if ($options['type'] == 'belongsTo') {
                $set->addAttribute(new Attribute($name, 'array'));
            }
        }
        
        return $set;
    }
    
    /**
     * Set of arrays whose elements will be used to instantiate Attribute objects.
     * Examples:
     *
     * ```
     * return [
     *     // Define a "name" attribute of type 'string' and default value "John".
     *     ['name', 'string', 'John'],
     *
     *     // Define a "lastName" attribute. Since 'string' is the default type of
     *     // attributes, we can ommit it.
     *     ['lastName'],
     *
     *     // Another string attribute. We can also pass just a string.
     *     'address',
     *
     *     // To create createdAt and updatedAt datetime attributes, pass "timestamps"
     *     'timestamps',
     *
     *     // And pass "recoverable" to create the deletedAt attribute.
     *     'recoverable'
     * ];
     * ```
     *
     * @return array
     * @see attributeSet()
     */
    protected static function defineAttributes()
    {
        return [];
    }
    
    protected static function initAttributeSet()
    {
        $className = get_called_class();
        
        if (!Attributes::attributesSetFor($className)) {
            Attributes::setClassAttributes(
                $className,
                static::attributeSet()
            );
        }
    }
    
    public function __set($prop, $value)
    {
        if ($this->getAssociations()->exists($prop)) {
            $this->setAssociation($prop, $value);
            return;
        }
        return parent::__set($prop, $value);
    }
    
    public function __call($methodName, $params)
    {
        $assocs = $this->getAssociations();
        if ($assocs->exists($methodName)) {
            return $this->getAssociation($methodName);
        }
        return parent::__call($methodName, $params);
    }
    
    protected function getAttributesClass()
    {
        return 'Rails\ActiveRecord\Mongo\Attributes\Attributes'; 
    }
    
    protected function setAndFilterProperties(array &$attributes)
    {
        $accProps = AccessibleProperties::getProperties(get_called_class());
        $this->filterProperties($attributes, $accProps);
    }
}
