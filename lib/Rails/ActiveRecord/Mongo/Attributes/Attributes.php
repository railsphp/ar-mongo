<?php
namespace Rails\ActiveRecord\Mongo\Attributes;

use MongoDate;
use Rails\ActiveModel\Attributes\Attributes as AttributesBase;
use Rails\ActiveSupport\Carbon\Carbon;

class Attributes extends AttributesBase
{
    public function isAttribute($attrName)
    {
        if ($attrName == 'id') {
            return true;
        }
        return parent::isAttribute($attrName);
    }
    
    public function get($attrName)
    {
        if ($attrName == 'id') {
            $attrName = '_id';
        }
        return parent::get($attrName);
    }
    
    public function dirty()
    {
        if (!$this->dirty) {
            $this->dirty = new Dirty($this);
        }
        return $this->dirty;
    }
    
    #### Note: consider allowing only 'datetime' as time type.
    protected function typeCastForSet($attrName, $value)
    {
        switch ($this->getAttribute($attrName)->type()) {
            case 'datetime':
                if ($value instanceof MongoDate) {
                    list($u, $s) = explode(" ", (string)$value);
                    $u = (float)$u;
                    $s = (int)$s;
                    return Carbon::createFromFormat('U.u', sprintf('%.f', $s + $u));
                }
                break;
            
            case 'mongoId':
                return $value;
        }
        return parent::typeCastForSet($attrName, $value);
    }
    
    /**
     * Do not set null values as default.
     *
     * @return void
     */
    protected function setDefaultValues()
    {
        $attrSet = self::getAttributesFor($this->className);
        
        foreach ($attrSet->attributes() as $attribute) {
            if ($attribute->defaultValue() !== null) {
                $this->attributes[$attribute->name()] = $attribute->defaultValue();
            }
        }
    }
}
