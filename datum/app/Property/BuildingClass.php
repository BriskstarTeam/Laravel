<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class BuildingClass extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'BuildingClass';

    /**
     * @var array
     */
    protected $fillable = ["Description", "LastModifiedAt"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
