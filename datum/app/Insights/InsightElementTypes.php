<?php

namespace App\Insights;

use Illuminate\Database\Eloquent\Model;

class InsightElementTypes extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'InsightElementTypes';

    /**
     * @var array
     */
    protected $fillable = ["Name", "LastModifiedAt"];


    
    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
