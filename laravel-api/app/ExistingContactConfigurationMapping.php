<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ExistingContactConfigurationMapping extends Model
{
    public $timestamps = false;
    protected $table = 'ExistingContactConfigurationMapping';
    protected $fillable = ['UserId', 'ConfigurationId','CreatedDate', 'CreatedBy'];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}