<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class LeadNotesMentions extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'leadnotesmentions';

    /**
     * @var array
     */
    protected $fillable = ["LeadID", "NoteID", "MarkRead", "IsClear", "CreatedDate", "CreatedBy", "IsImportant", "AgentID"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'ID';
}
