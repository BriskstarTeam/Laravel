<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Countries extends Model
{
    public $timestamps = false;
    protected $table = 'Countries';
    protected $fillable = ['Code', 'CountryName'];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';


}
