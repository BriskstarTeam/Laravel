<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BrokerType extends Model
{
    public $timestamps = false;
    protected $table = 'BrokerType';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
