<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserOtpCheck extends Model
{
    /**
     * @var bool
     */
    public $timestamps = true;

    const CREATED_AT = 'CreatedDate';
    const UPDATED_AT = 'UpdatedDate';
    /**
     * @var string
     */
    protected $table = 'UserOtpCheck';

    /**
     * @var array
     */
    protected $fillable = ["UserId", "Ip", "Otp", "OtpTime", "OtpLastSent", "OtpTries", "Status", "VerificationAttempts"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
