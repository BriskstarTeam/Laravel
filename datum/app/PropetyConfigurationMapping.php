<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PropetyConfigurationMapping extends Model
{
    public $timestamps = false;
    
    protected $table = 'PropertyConfigurationMapping';

    protected $fillable = [
        "PropertyId",
        "ConfigurationId",
        "HostedConfigurationId",
        "UserId",
        "CreatedBy",
        "CreatedDate",
        "DocumentRole"
    ];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}