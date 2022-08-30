<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class ReNewalOption extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'renewaloption';

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
