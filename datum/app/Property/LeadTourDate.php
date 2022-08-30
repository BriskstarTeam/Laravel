<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class LeadTourDate extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'leadtourdate';

    /**
     * @var array
     */
    protected $fillable = ["LeadID", "PropertyID", "TourDate"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'ID';
}
