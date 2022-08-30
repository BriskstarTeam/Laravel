<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class ListingStatus extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'propertystatus';


    /**
     * @var array
     */
    protected $fillable = ["Description"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'ID';
}
