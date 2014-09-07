<?php
namespace Rails\ActiveRecord\Mongo\Attributes;

use Rails\ActiveModel\Attributes\Dirty as DirtyBase;

class Dirty extends DirtyBase
{
    public function registerAttributeChange($attrName, $newValue)
    {
        $isArray = $this->attributes->getAttribute($attrName)->type() == 'array';
        
        if (!$this->attributeChanged($attrName)) {
            $oldValue = $this->attributes->get($attrName);
            
            if (!$isArray) {
                $newValue = (string)$newValue;
                $oldValue = (string)$oldValue;
            }
            
            if ($newValue != $oldValue) {
                $this->changedAttributes[$attrName] = $oldValue;
            }
        } else {
            $changedAttr = $this->changedAttributes[$attrName];
        
            if (!$isArray) {
                $newValue    = (string)$newValue;
                $changedAttr = (string)$changedAttr;
            }
            
            if ($newValue == $changedAttr) {
                unset($this->changedAttributes[$attrName]);
            }
        }
    }
}
