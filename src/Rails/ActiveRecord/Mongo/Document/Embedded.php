<?php
namespace Rails\ActiveRecord\Mongo\Document;

use Rails\ActiveModel\Base as ActiveModel;

abstract class Embedded extends ActiveModel
{
    use DocumentTrait;
}
