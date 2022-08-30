<?php

namespace App\Passport;

use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\Client as PassportClient;

/**
 * Class Client
 * @package App\Passport
 */
class Client extends PassportClient
{
    /**
     * @var bool
     */
    public $timestamps = true;

    /**
     * @var string
     */
    protected $table = 'OAuthClients';

    /**
     * @var array
     */
    protected $fillable = ['user_id', 'name','secret', 'provider', 'redirect', 'personal_access_client', 'password_client', 'revoked'];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

}
