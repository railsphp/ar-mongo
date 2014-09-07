<?php
namespace Rails\ActiveRecord\Mongo\Embedded;

use MongoId;
use Rails\ActiveModel\Collection as BaseCollection;
use Rails\ActiveRecord\Mongo\Base as ParentDocument;
use Rails\ActiveRecord\Mongo\Exception;

class Collection extends BaseCollection
{
    protected $parent;
    
    /**
     * Name of the collection, which is the name of the parent
     * document's field under which this collection is stored.
     *
     * @var string
     */
    protected $name;
    
    protected $membersClass;
    
    protected $changes = [
        'deletions'  => [],
        'insertions' => []
    ];
    
    public function __construct(array $members = [], $name, $membersClass, ParentDocument $parent)
    {
        $this->parent = $parent;
        $this->name = $name;
        $this->membersClass = $membersClass;
        
        $this->resetMembers($members);
        
        parent::__construct($members);
    }
    
    public function parent()
    {
        return $this->parent;
    }
    
    public function membersClass()
    {
        return $this->membersClass;
    }
    
    # TODO: after insertions we need to set each model its current index.
    # we could fetch the records from the db and re-set the models.
    public function save()
    {
        if (!$this->parent->isPersisted()) {
            throw new Exception\RuntimeException(
                "Can't save collection because parent isn't persisted"
            );
        }
        
        $update = $this->generatePersistenceScript();
        
        if (!$update) {
            return true;
        }
        
        $id = $this->parent->id();
        if ($id instanceof MongoId) {
            $id = 'ObjectId("' . (string)$id . '")';
        }
        
        $script = sprintf(
            "var _id  = %s;\n" .
            "var coll = \"%s\";\n" .
            "db[coll].update({%s: _id}, %s);\n" .
            "var records = [];\n" .
            "var cursor = db[coll].find({%s: _id}, {%s: 1});\n" .
            "cursor.forEach(function(r) { records.push(r); });\n" .
            "return records;",
            $id,
            $this->parent->collectionName(),
            $this->parent->primaryKey(),
            json_encode($update),
            
            $this->parent->primaryKey(),
            $this->name
        );
        
        $resp = $this->parent->connection()->execute($script);
        
        $this->changes = [
            'deletions'  => [],
            'insertions' => []
        ];
        
        if (isset($resp['retval'][0][$this->name])) {
            $this->resetMembers($resp['retval'][0][$this->name], true);
        }
        
        return $resp;
    }
    
    public function updateMember($member)
    {
        $this->validateMember($member);
    }
    
    public function generatePersistenceScript()
    {
        if ($this->parent->isPersisted()) {
            $set   = $this->generateUpdateScript();
            $unset = $this->generateUnsetScript();
            $push  = $this->generatePushScript();
            
            $update = [];
            if ($set) {
                $update['$set']   = $set;
            }
            if ($unset) {
                $update['$unset'] = $unset;
            }
            if ($push) {
                $update['$push']  = $push;
            }
            
            return $update;
        } else {
            $this->generateInsertScript();
        } 
    }
    
    public function addMembers(array $members)
    {
        $membersClass = $this->membersClass;
        foreach ($members as $member) {
            if (is_array($member)) {
                $member = new $membersClass($member, $this->parent);
            } else {
                $this->validateMember($member);
            }
            $member->setCollection($this);
            $this->members[] = $member;
            $this->changes['insertions'][] = $member;
        }
        return $this;
    }
    
    public function addMember($member)
    {
        return $this->addMembers([$member]);
    }
    
    public function markForDestroy($member)
    {
        $this->changes['deletions'][] = $member->memberIndex();
    }
    
    public function offsetSet($offset, $value)
    {
        $this->addMember($value);
    }
    
    protected function generateUpdateScript()
    {
        $set = [];
        
        $parentClass = get_class($this->parent);
        
        foreach ($this->members as $key => $member) {
            if ($member === null) {
                continue;
            }
            
            $this->validateMember($member);
            
            foreach ($member->changes() as $attrName => $change) {
                $set[$this->name . '.' . $key . '.' . $attrName] = $change[1];
            }
        }
        
        return $set;
    }
    
    protected function generatePushScript()
    {
        $each = [];
        $name = $this->name;
        
        foreach ($this->changes['insertions'] as $model) {
            $each[] = $model->attributes();
        }
        
        return $each ? [$this->name => ['$each' => $each]] : [];
    }
    
    protected function generateUnsetScript()
    {
        $unset  = [];
        $name = $this->name;
        
        foreach ($this->changes['deletions'] as $key) {
            $unset[$name . '.' . $key] = "";
        }
        
        return $unset;
    }
    
    protected function validateMembers(array $members, $asArrays = false)
    {
        foreach ($members as $member) {
            if ($asArrays && is_array($member)) {
                continue;
            } elseif ($member !== null && !$member instanceof $this->membersClass) {
                throw new Exception\RuntimeException(sprintf(
                    "A member of the collection is not an instance of %s",
                    $this->membersClass
                ));
            }
        }
    }
    
    protected function validateMember($member)
    {
        $this->validateMembers([$member]);
    }
    
    protected function resetMembers(array $members, $asArrays = false)
    {
        $this->members = [];
        $this->validateMembers($members, $asArrays);
        $membersClass = $this->membersClass;
        
        foreach ($members as $index => $member) {
            if ($member !== null) {
                if ($asArrays && is_array($member)) {
                    $member = new $membersClass($member, $this->parent);
                }
                $member->setCollection($this);
                $member->setMemberIndex($index);
            }
        }
        
        $this->members = $members;
    }
}
