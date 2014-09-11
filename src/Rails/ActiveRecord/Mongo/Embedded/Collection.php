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
        'deletions'    => [],
        'insertions'   => [],
        'replacements' => []
    ];
    
    protected $allMembers = [];
    
    public function __construct(array $members = [], $name, $membersClass, ParentDocument $parent)
    {
        $this->parent = $parent;
        $this->name = $name;
        $this->membersClass = $membersClass;
        $this->resetMembers($members);
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
        
        $primaryKey = $this->parent->primaryKey();
        $unset = $set = $push = '';
        
        if ($update['unset']) {
            $unset = sprintf(
                'db[coll].update({%s: _id}, %s);',
                $primaryKey,
                json_encode($update['unset'])
            );
        }
        if ($update['set']) {
            $set = sprintf(
                'db[coll].update({%s: _id}, %s);',
                $primaryKey,
                json_encode($update['set'])
            );
        }
        if ($update['push']) {
            $push = sprintf(
                'db[coll].update({%s: _id}, %s);',
                $primaryKey,
                json_encode($update['push'])
            );
        }
        
        $script = sprintf(
            "var _id  = %s;\n" .
            "var coll = \"%s\";\n" .
            "%s\n" .
            "%s\n" .
            "%s\n" .
            "var records = [];\n" .
            "var cursor = db[coll].find({%s: _id}, {%s: 1});\n" .
            "cursor.forEach(function(r) { records.push(r); });\n" .
            "return records;",
            $id,
            $this->parent->collectionName(),
            
            $unset,
            $set,
            $push,
            
            $primaryKey,
            $this->name
        );
        
        $resp = $this->parent->connection()->execute($script);
        $this->discardChanges();
        
        if (isset($resp['retval'][0][$this->name])) {
            $this->resetMembers($resp['retval'][0][$this->name]);
        }
        
        return $resp;
    }
    
    public function generatePersistenceScript()
    {
        if ($this->parent->isPersisted()) {
            $set   = $this->generateUpdateScript();
            $repl  = $this->generateReplacementScript();
            $unset = $this->generateUnsetScript();
            $push  = $this->generatePushScript();
            
            $update = [
                'unset' => [],
                'set'   => [],
                'push'  => [],
                'repl'  => [],
            ];
            
            if (!$unset && !$set && !$push && !$repl) {
                return [];
            }
            
            if ($unset) {
                $update['unset']['$unset'] = $unset;
            }
            if ($set || $repl) {
                $update['set']['$set'] = array_merge($set, $repl);
            }
            if ($push) {
                $update['push']['$push']   = $push;
            }
            
            return $update;
        }
    }
    
    public function addMembers(array $members)
    {
        $this->validateMembers($members, false);
        foreach ($members as $member) {
            $this->addNewMember($member);
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
    
    public function reset(array $members)
    {
        $this->discardChanges();
        foreach ($this->members as $member) {
            $this->markForDestroy($member);
        }
        
        $this->members = [];
        $this->allMembers = [];
        $this->addMembers($members);
        foreach ($this->members as $member) {
            $member->getAttributes()->dirty()->changesApplied();
        }
        
        return $this;
    }
    
    public function offsetSet($offset, $member)
    {
        $members = [$member];
        $this->validateMembers($members, false);
        $member = $members[0];
        $this->setOrReplace($offset, $member);
    }
    
    /**
     * $member must be validated.
     */
    protected function addNewMember($member)
    {
        $member->setCollection($this);
        $this->members[]    = $member;
        $this->allMembers[] = $member;
        $this->changes['insertions'][] = $member;
    }
    
    public $foo;
    /**
     * $members is array of documents
     */
    public function set(array $members)
    {
        foreach ($members as $index => $member) {
            $this->setOrReplace($index, $member);
        }
        return $this;
    }
    
    /**
     * $member must be validated.
     */
    protected function setOrReplace($offset, $member)
    {
        if ($this->offsetExists($offset)) {
            if ($this->members[$offset] === null) {
                $this->changes['replacements'][$offset] = $member;
            } else {
                if (is_array($member)) {
                    foreach ($member as $attrName => $value) {
                        $this->members[$offset]->$attrName = $value;
                    }
                } elseif (is_object($member)) {
                    $this->validateMember($member);
                    $member->setMemberIndex($offset);
                    $this->changes['replacements'][$offset] = $member;
                } else {
                    throw new Exception\InvalidArgumentException(sprintf(
                        "Expected array or instance of %s, passed %s",
                        $this->membersClass,
                        $this->varTypeForMsg($member)
                    ));
                }
            }
        } else {
            $members = [$member];
            $this->validateMembers($members);
            $this->addNewMember($members[0]);
        }
    }
    
    protected function generateUpdateScript()
    {
        $set = [];
        $parentClass = get_class($this->parent);
        
        foreach ($this->allMembers as $key => $member) {
            if (
                $member === null ||
                isset($this->changes['replacements'][$key]) ||
                in_array($member, $this->changes['insertions'])
            ) {
                continue;
            }
            $this->validateMember($member);
            
            foreach ($member->changes() as $attrName => $change) {
                $set[$this->name . '.' . $key . '.' . $attrName] = $change[1];
            }
        }
        
        return $set;
    }
    
    protected function generateReplacementScript()
    {
        $set = [];
        $parentClass = get_class($this->parent);
        
        foreach ($this->changes['replacements'] as $key => $member) {
            if ($member === null) {
                continue;
            }
            $this->validateMember($member);
            
            foreach ($member->attributes() as $attrName => $value) {
                $set[$this->name . '.' . $key . '.' . $attrName] = $value;
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
        $unset = [];
        $name  = $this->name;
        
        foreach ($this->changes['deletions'] as $key) {
            $unset[$name . '.' . $key] = "";
        }
        
        return $unset;
    }
    
    protected function validateMembers(array &$members, $allowNulls = true)
    {
        $membersClass = $this->membersClass;
        
        foreach ($members as $index => $member) {
            if (is_array($member)) {
                $members[$index] = $member = new $membersClass($member, $this->parent);
                $member->setCollection($this);
                $member->setMemberIndex($index);
            } elseif ($allowNulls && $member === null) {
                continue;
            } elseif ($member !== null && !$member instanceof $this->membersClass) {
                throw new Exception\RuntimeException(sprintf(
                    "A member of the collection is not an instance of %s, received %s",
                    $this->membersClass,
                    $this->varTypeForMsg($member)
                ));
            }
        }
    }
    
    protected function validateMember($member, $allowNulls = true)
    {
        $members = [$member];
        $this->validateMembers($members, $allowNulls);
    }
    
    protected function resetMembers(array $members)
    {
        $this->members    = [];
        $this->allMembers = [];
        $this->validateMembers($members);
        
        foreach ($members as $index => $member) {
            if ($member !== null) {
                $member->setCollection($this);
                $member->setMemberIndex($index);
                $this->members[] = $member;
            }
            $this->allMembers[] = $member;
        }
    }
    
    protected function discardChanges()
    {
        $this->changes = [
            'deletions'    => [],
            'insertions'   => [],
            'replacements' => []
        ];
    }
    
    protected function varTypeForMsg($var)
    {
        if (is_object($var)) {
            $type = 'instance of ' . get_class($var);
        } else {
            $type = gettype($var);
        }
        return $type;
    }
}
