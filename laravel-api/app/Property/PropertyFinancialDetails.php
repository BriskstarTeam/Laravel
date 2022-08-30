<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class PropertyFinancialDetails extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'propertyfinancialdetails';

    /**
     * @var array
     */
    protected $fillable = [
        "PropertyId",
        "PricePSF",
        "AskingPrice",
        "T12CapRate",
        "InvestmentPeriod",
        "LeveredIRR",
        "UnleveredIRR",
        "ReturnOnCost",
        "GRM",
        "PotentialGRM",
        "InPlaceCapRate",
        "InPlaceNOI",
        "Year1CapRate",
        "MarkToMarketCapRate",
        "InPlaceRents",
        "MarketRents",
        "AvgInPlaceRentsBelowMarket",
        "YrlRR10",
        "Yr10EquityMultiple",
        "CashOnCash",
        "Year1NOI",
        "T12NOI",
        "CapitalInvested",
        "LastModifiedAt",
        "Walt",
        "IsConfidential",
        "IsUnpriced",
        "Price",
        "AcresPrice",
    ];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
