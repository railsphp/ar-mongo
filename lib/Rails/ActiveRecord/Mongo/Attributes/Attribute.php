<?php
namespace Rails\ActiveRecord\Mongo\Attributes;

use Rails\ActiveModel\Attributes\Attribute as BaseAttribute;

class Attribute extends BaseAttribute
{
    public function __construct($name, $type = 'string', $defaultValue = null, $serializable = false)
    {
        if ($type == 'array' && $defaultValue === null) {
            $defaultValue = [];
        }
        parent::__construct($name, $type, $defaultValue, $serializable);
    }
}
