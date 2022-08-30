<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class LeadOfferNotes extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'leadoffernotes';

    /**
     * @var array
     */
    protected $fillable = ["LeadID", "OfferID", "PropertyID", "Note", "CreatedBy", "CreatedDate"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'ID';
}
