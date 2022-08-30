<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class PropertyMarketingTimeLine extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'propertymarketingtimeline';

    /**
     * @var array
     */
    protected $fillable = ["PropertyID", "Date", "Description", "SortOrder"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'ID';
}
