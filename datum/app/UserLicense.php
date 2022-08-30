<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserLicense extends Model
{
    public $timestamps = true;
    protected $table = 'UserLicense';
    protected $fillable = ['UserId', 'State', 'Text', 'CreatedBy', 'CreatedDate'];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
