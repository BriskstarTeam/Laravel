<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Database\DatabaseConnection;
class UserVerificationHistory extends Model
{
    public $timestamps = false;

    protected $table = 'UserVerificationHistory';

    protected $fillable = [
        "UserId",
        "VerifiedDatetime",
        "VerifiedBy",
        "IsVerified",
        "VerificationType",
        "ConfigurationId",
        "HostedConfigurationId",
    ];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
