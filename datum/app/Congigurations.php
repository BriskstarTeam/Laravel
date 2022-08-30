<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Congigurations extends Model
{
    public $timestamps = false;
    protected $table = 'Configurations';
    protected $fillable = [
        "ConfigurationId",
        "SiteName",
        "SiteUrl",
        "ActivationKey",
        "HostName",
        "DbUserName",
        "DbPassword",
        "DatabaseName",
        "Status",
        "ActiveDate",
        "ExpiredDate",
        "CreatedDate",
        "CreatedBy",
        "UpdatedDate",
        "UpdatedBy",
        "DeletedDate",
        "DeletedBy",
        "ConnectionString",
        "ListingPageUrl",
        "ClosedListingPageUrl",
        "AgentPageUrl",
        "Port",
    ];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'ConfigurationId';
}
