<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FavoriteProperty extends Model
{
    public $timestamps = false;
    protected $table = 'FavoriteProperty';
    protected $fillable = ['PropertyId', 'AdminId','Favorite', 'ConfigurationId'];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}