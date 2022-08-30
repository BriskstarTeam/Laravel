<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class PropertyCurrentStatus extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'propertycurrentstatus';

    /**
     * @var array
     */
    protected $fillable = ["PropertyID", "IsSalePrice", "IsSalesPricePSF", "IsCloseCAPRate", "IsBuyer", "IsSeller", "IsClosingDate", "IsSPvsAP"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'ID';
}
