<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class DocumentVaultomAccess extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'DocumentVaultOMAccess';


    /**
     * @var array
     */
    protected $fillable = ["DatumDirectoryId", 'IndustryRoleId', 'UserId', 'UserEmail', 'Access', 'UpdatedBy', 'UpdatedDate', 'HasDefaultAccess', 'ConfigurationId', 'HostedConfigurationId'];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
