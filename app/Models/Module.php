<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    protected $table = 'modules';

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'icon',
        'slug',
        'base_url',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get users who have access to this module.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_module_access')
            ->withPivot('role', 'is_active', 'granted_by', 'granted_at')
            ->withTimestamps();
    }

    /**
     * Get access records for this module.
     */
    public function access(): HasMany
    {
        return $this->hasMany(UserModuleAccess::class);
    }

    /**
     * Scope: Only active modules.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Order by sort_order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
