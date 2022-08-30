<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;
use App\UserLicense;

class LaborType extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'LaborType';

    /**
     * @var array
     */
    //protected $fillable = ["PropertyID", "AgentID", "Delete", "CreatedBy", "CreatedDateTime"];

    protected $fillable = ["Name"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */

}
