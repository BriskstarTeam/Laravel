<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserLoginHistory extends Model
{
    public $timestamps = false;

    protected $table = 'UserLoginHistory';

    protected $fillable = [
        "SessionToken",
        "UserId",
        "TimeLogin",
        "TimeLastSeen",
        "IpAddress",
        "Device",
        "IsLogout",
        "ConfigurationId",
    ];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
