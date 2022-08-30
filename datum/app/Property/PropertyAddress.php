<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class PropertyAddress extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'PropertyAddress';

    /**
     * @var array
     */
    protected $fillable = ["PropertyId", "Latitude", "Longitude", "Address1", "Address2", "City", "State", "Country", "ZipCode", "LastModifiedAt"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
