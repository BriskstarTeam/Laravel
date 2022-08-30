<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;
use App\UserLicense;

class OwnershipInterestType extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'OwnershipInterestType';

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
