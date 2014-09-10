<?php
namespace Rails\ActiveRecord\Mongo\Associations;

use Rails;
use Rails\ActiveRecord\Associations\Exception\TypeMissmatchException;
use Rails\ActiveRecord\Exception;
use Rails\ActiveRecord\Persistance\Exception\RecordNotSavedException;

trait AssociableModelTrait
{
    /**
     * An array where loaded associations will
     * be stored.
     */
    protected $loadedAssociations = [];
    
    /**
     * If the association isn't yet loaded, it is loaded and returned.
     * If the association doesn't exist, `null` is returned. Note that
     * unset one-to-one associations return `false`.
     *
     * return null|false|object
     */
    public function getAssociation($name, $autoLoad = true)
    {
        if (isset($this->loadedAssociations[$name])) {
            return $this->loadedAssociations[$name];
        } elseif ($this->getAssociations()->exists($name)) {
            if ($autoLoad) {
                $assocs = $this->getAssociations();
                $this->loadedAssociations[$name] =
                    $assocs->load($this, $name);
                return $this->loadedAssociations[$name];
            } else {
                return null;
            }
        }
        
        throw new \Exception(sprintf(
            "Association %s::%s doesn't exist",
            get_called_class(),
            $name
        ));
    }
    
    /**
     * Associates an object to a one-to-one association.
     * Other associations are done in CollectionProxy.
     */
    public function setAssociation($name, $value, $raw = false)
    {
        if ($raw) {
            $this->loadedAssociations[$name] = $value;
        } else {
            return (new Setter())->set($this, $name, $value);
        }
    }
    
    /**
     * Returns the Associations object that holds the associations
     * data for the called class.
     *
     * @return Associations
     */
    public function getAssociations()
    {
        return Associations::forClass(get_called_class());
    }
    
    /**
     * Defines associations.
     * This method may be overriden to define associations for a class.
     * Other methods whose name end in `Associations`, like `postAssociations`,
     * will also be considered when requiring all associations for a class.
     *
     * The name of the class for an association, if not specified, is
     * deduced out of the name of the association:
     *
     * <pre>
     * 'belongsTo' => [
     *     'ownerUser', // Class not specified; would deduce "OwnerUser".
     *     'owner' => [ 'class' => 'User' ] // Class specified.
     * ]
     * </pre>
     *
     *
     * @return array
     */
    protected function associations()
    {
        return [];
    }
}
