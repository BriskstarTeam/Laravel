<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class PropertyPhysicalDetails extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'propertyphysicaldetails';

    /**
     * @var array
     */
    protected $fillable = [
        "PropertyId",
        "Building",
        "SqFeet",
        "YearBuilt",
        "LotSize",
        "Stories",
        "ParkingRatio",
        "ZoningTypeId",
        "YearRenovated",
        "BuildingClassId",
        "LastModifiedAt",
        "ZoningType",
    ];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
