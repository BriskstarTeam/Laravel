<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class States extends Model
{
    public $timestamps = false;
    protected $table = 'States';
    protected $fillable = ['Code', 'StateName'];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
