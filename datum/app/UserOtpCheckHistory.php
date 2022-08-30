<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserOtpCheckHistory extends Model
{
    public $timestamps = true;

    const CREATED_AT = 'CreatedDate';
    const UPDATED_AT = 'UpdatedDate';

    protected $table = 'UserOtpCheckHistory';

    protected $fillable = ["UserId", "Ip", "Otp", "Status"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
