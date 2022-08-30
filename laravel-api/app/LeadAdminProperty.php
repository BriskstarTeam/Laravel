<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LeadAdminProperty extends Model
{
    public $timestamps = false;
    protected $table = 'LeadAdminProperty';

    protected $fillable = ['PropertyId', 'AdminId','UserId', 'AssignAgentId', 'PriorityId', 'StatusId', 'TopProspect', 'LastModifiedAt', 'HostedConfigurationId'];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}