<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class PropertyOffers extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'propertyoffers';

    /**
     * @var array
     */
    protected $fillable = ["PropertyID", "CreatedDate", "Price", "BrokerID", "BuyerID", "EarnestDeposit", "DDPeriod", "ClosingPeriod", "OfferContingent", "OfferHighlights", "LOIDocument"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'ID';
}
