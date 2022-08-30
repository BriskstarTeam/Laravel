<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OeplPropertyTracker extends Model
{
    public $timestamps = false;
    protected $table = 'OEPLPropertyTracker';
    protected $fillable = ['PropertyId', 'UserId','CreatedDateTime', 'SessionId', 'LoggedIn', 'UserIp', 'BrowseFromMobile', 'LastModifiedAt', 'ConfigurationId', 'HostedConfigurationId'];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';
}
