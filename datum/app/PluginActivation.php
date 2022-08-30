<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PluginActivation extends Model
{
    public $timestamps = true;
    protected $table = 'pluginactivation';
    protected $fillable = ['site_url', 'activation_key','status', 'active_at', 'expired_at'];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function getSiteConfigurations() {
        return $this->hasMany(SiteConfigurations::class, 'plugin_activation_key');
    }
}
