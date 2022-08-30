<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class PressreleaseHistory extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'pressreleasehistory';


    /**
     * @var array
     */
    protected $fillable = ["PropertyId", 'UserId', 'FileName', 'IP', 'CreateDate', 'ConfigurationId'];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'ID';
}
