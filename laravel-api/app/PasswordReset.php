<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    public $timestamps = true;

    const CREATED_AT = 'CreatedDate';
    const UPDATED_AT = 'UpdatedDate';

    protected $table = 'PasswordResets';

    protected $fillable = ["UserId", "IsForgot", "OneTimePasswordExpiryDate", "Attempt", "OneTimePassword", "ResendAttempts"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
