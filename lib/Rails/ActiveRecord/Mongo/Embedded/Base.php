<?php
namespace Rails\ActiveRecord\Mongo\Embedded;

use Rails\ActiveRecord\Mongo\Document\Embedded;
use Rails\ActiveRecord\Mongo\Base as ParentDocument;

abstract class Base extends Embedded
{
    const PRIMARY_KEY = '_id';
    
    const DELETED_AT_ATTRIBUTE = 'deletedAt';
    
    const COLLECTION_NAME = '';
    
    const ADD_ID_ATTRIBUTE = false;
    
    protected $parent;
    
    protected $collection;
    
    protected $memberIndex;
    
    public function __construct(array $attributes = [], ParentDocument $parent)
    {
        parent::__construct($attributes);
        $this->parent = $parent;
    }
    
    public function parent()
    {
        return $this->parent;
    }
    
    public function setMemberIndex($memberIndex)
    {
        $this->memberIndex = $memberIndex;
    }
    
    public function memberIndex()
    {
        return $this->memberIndex;
    }
    
    public function setCollection($collection)
    {
        $this->collection = $collection;
        return true;
    }
    
    public function setAttributesContainer(array $container)
    {
    }
    
    public function setParentObject(Base $parent)
    {
    }
    
    public function destroy()
    {
        $this->collection->markForDestroy($this);
        return true;
    }
    
    /**
     * Save this document.
     */
    public function save()
    {
    }
}
