<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class PropertyLeadOffer extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'PropertyLeadOffer';

    /**
     * @var array
     */

    protected $fillable = [
        "PurchasePrice",
        "Deposit",
        "AdditionalDeposit",
        "DiligencePeriod",
        "ClosingPeriod",
        "SubmittedDate",
        "UserId",
        "CreatedBy",
        "CreatedDate",
        "PropertyId",
        "BrokerCommission",
        "Document",
        "LastModifiedAt",
    ];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
