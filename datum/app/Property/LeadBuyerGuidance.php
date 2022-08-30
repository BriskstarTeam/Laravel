<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class LeadBuyerGuidance extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'leadbuyerguidance';

    /**
     * @var array
     */
    protected $fillable = ["LeadID", "PropertyID", "BuyerGuidance", "CreatedDate"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'ID';
}
