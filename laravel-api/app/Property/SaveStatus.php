<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class SaveStatus extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'SaveStatus';

    /**
     * @var array
     */
    protected $fillable = ["Description"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
