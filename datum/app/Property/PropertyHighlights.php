<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class PropertyHighlights extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'PropertyHighlights';

    /**
     * @var array
     */
    //protected $fillable = ["PropertyID", "Icon", "Field", "DisplayLabel", "SortingOrder", "EntityStatusId"];

    protected $fillable = ["PropertyId", "Icon", "Field", "DisplayLabel", "SortingOrder", "EntityStatusId", "LastModifiedAt"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
