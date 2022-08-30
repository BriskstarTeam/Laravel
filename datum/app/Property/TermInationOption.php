<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class TermInationOption extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'terminationoption';

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
