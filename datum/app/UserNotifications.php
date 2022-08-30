<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Database\DatabaseConnection;
use Illuminate\Support\Facades\DB;

class UserNotifications extends Model
{
    public $timestamps = false;

    protected $table = 'UserNotifications';

    protected $fillable = [
        "Id",
        "UserId",
        "IsRead",
        "EntityStatusId",
        "TypeId",
        "PropertyId",
        "RelatedUserId",
        "ConfigurationId",
        "AdditionalData",
        "LastModifiedAt",
        "HostedConfigurationId",
        "CreatedDate",
        "InsightId",
    ];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

    /**
     * @param string $userId
     * @param array $data
     * @param string $Id
     */
}