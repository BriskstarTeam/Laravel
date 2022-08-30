<?php

namespace App\Passport;

use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\PersonalAccessClient as PassportPersonalAccessClient;

/**
 * Class PersonalAccessClient
 * @package App\Passport
 */
class PersonalAccessClient extends PassportPersonalAccessClient
{
    /**
     * @var bool
     */
    public $timestamps = true;

    /**
     * @var string
     */
    protected $table = 'OAuthPersonalAccessClients';

    /**
     * @var array
     */
    protected $fillable = ['client_id'];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

}
