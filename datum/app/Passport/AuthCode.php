<?php

namespace App\Passport;

use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\AuthCode as PassportAuthCode;

/**
 * Class AuthCode
 * @package App\Passport
 */
class AuthCode extends PassportAuthCode
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'OAuthAuthCodes';

    /**
     * @var array
     */
    protected $fillable = ['user_id', 'client_id','scopes', 'revoked', 'expires_at'];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

}
