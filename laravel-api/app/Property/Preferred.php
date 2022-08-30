<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class Preferred extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'preferredmodules';

    /**
     * @var array
     */
    protected $fillable = ["Name", "Status", "created_by", "create_date"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

}
