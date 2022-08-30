<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class CaContent extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'cacontent';

    /**
     * @var array
     */
    protected $fillable = ["PropertyID", "CAContent", "CADocument"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'ID';
}
