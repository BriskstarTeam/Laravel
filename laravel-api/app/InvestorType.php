<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InvestorType extends Model
{
    public $timestamps = false;
    protected $table = 'InvestorType';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
