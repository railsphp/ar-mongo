<?php
namespace Rails\ActiveRecord\Mongo\Attributes;

use Rails\ActiveModel\Attributes\AttributeSet as BaseSet;

class AttributeSet extends BaseSet
{
    public function timestamps()
    {
        $this->addAttribute(new Attribute(
            'createdAt',
            'datetime'
        ));
        $this->addAttribute(new Attribute(
            'updatedAt',
            'datetime'
        ));
    }
    
    public function recoverable()
    {
        $this->addAttribute(new Attribute(
            'deletedAt',
            'datetime'
        ));
    }
}
