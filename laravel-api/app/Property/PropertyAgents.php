<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;
use App\UserLicense;

class PropertyAgents extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'PropertyAgents';

    /**
     * @var array
     */
    //protected $fillable = ["PropertyID", "AgentID", "Delete", "CreatedBy", "CreatedDateTime"];

    protected $fillable = ["PropertyId", "AgentId", "CreatedBy", "CreatedDateTime", "LastModifiedAt", "EntityStatusId", "SortOrder", "IsAdditionalAgent", "IsNotificationEnabled"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function getLicense() {
        return $this->hasMany(UserLicense::class, 'UserId')->select('UserId', 'State', 'Text');
    }
}
