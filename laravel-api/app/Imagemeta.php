<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Imagemeta extends Model
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    public $timestamps = false;
    protected $table = 'oepl_imagemeta';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function properties() {
        return $this->belongsTo(Properties::class);
    }
}
