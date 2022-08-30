<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserActivity extends Model
{
    public $timestamps = true;
    protected $table = 'user_activity';
    protected $fillable = ['user_id', 'ip_address','page_url', 'api_url', 'user_agent'];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';  
}
