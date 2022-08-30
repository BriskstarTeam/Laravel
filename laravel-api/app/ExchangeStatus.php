<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ExchangeStatus extends Model
{
    public $timestamps = false;
    protected $table = 'ExchangeStatus';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
