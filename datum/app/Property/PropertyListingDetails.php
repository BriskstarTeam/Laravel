<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class PropertyListingDetails extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'PropertyListingDetails';

    /**
     * @var array
     */
    protected $fillable = [
        "PropertyId",
        "SalesPrice",
        "ListingExpiration",
        "SalesPricePSF",
        "ClosingCapRate",
        "DaysOnMarket",
        "NoOfOffers",
        "CloseDate",
        "EstimatedCommission",
        "SPvsAP",
        "InternalSalesNotes",
        "LastTransfer",
        "LastTransferPrice",
        "PricingExpectation",
        "SellerMotivation",
        "LastModifiedAt",
    ];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
