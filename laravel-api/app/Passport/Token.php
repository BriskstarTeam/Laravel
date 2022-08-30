<?php

namespace App\Passport;

use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\Token as PassportToken;

/**
 * Class Token
 * @package App\Passport
 */
class Token extends PassportToken
{
    /**
     * @var bool
     */
    public $timestamps = true;

    /**
     * @var string
     */
    protected $table = 'OAuthAccessTokens';

    /**
     * @var array
     */
    protected $fillable = ['id', 'user_id', 'client_id', 'name', 'scopes', 'revoked', 'expires_at'];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

}
