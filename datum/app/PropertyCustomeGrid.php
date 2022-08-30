<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PropertyCustomeGrid extends Model
{
    public $timestamps = false;
    protected $table = 'property_custome_grid';
    protected $fillable = ['icon', 'field', 'display_label'];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';
}
