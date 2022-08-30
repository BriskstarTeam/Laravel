<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class IndustryRole extends Model
{
    public $timestamps = false;
    protected $table = 'IndustryRole';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
