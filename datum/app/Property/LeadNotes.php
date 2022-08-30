<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class LeadNotes extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'leadnotes';

    /**
     * @var array
     */
    protected $fillable = ["LeadID", "Note", "CreatedDate", "CreatedBy", "IsImportant", "AdminID"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'ID';
}
