<?php
namespace Rails\ActiveRecord\Mongo\Document;

use Rails\ActiveRecord\Persistence\PersistedModel\PersistedModel;

abstract class Document extends PersistedModel implements DocumentInterface
{
    use DocumentTrait;
}
