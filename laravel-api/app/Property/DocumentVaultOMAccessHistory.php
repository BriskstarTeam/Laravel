<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class DocumentVaultOMAccessHistory extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'DocumentVaultOMAccessHistory';

    /**
     * @var array
     */
    protected $fillable = ["DocumentVaultOMAccessId", "IndustryRoleId", "UserId", "UserEmail", "Access", "AccessBefore", "AccessAfter", "CreatedBy", "CreatedDate", "UpdatedBy", "UpdatedDate", 'ConfigurationId', 'HostedConfigurationId'];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'ID';
}
