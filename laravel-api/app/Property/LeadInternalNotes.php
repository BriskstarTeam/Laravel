<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class LeadInternalNotes extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'leadinternalnotes';

    /**
     * @var array
     */
    protected $fillable = ["LeadID", "Note", "CreatedDate", "CreatedBy"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'ID';
}
