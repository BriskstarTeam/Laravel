<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PropertyAgent extends Model
{
    public $timestamps = false;
    protected $table = 'PropertyAgents';
    //protected $fillable = ['property_id', 'agent_id'];

    protected $fillable = ['PropertyId', 'AgentId', 'CreatedBy', 'CreatedDateTime', 'LastModifiedAt', 'EntityStatusId', 'SortOrder', 'IsAdditionalAgent', 'IsNotificationEnabled'];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
}
