<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Section extends Model
{
    protected $table = 'sections';

    protected $fillable = [
        'name',
        'slug',
        'display_name',
        'description',
        'icon',
        'base_url',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the package install that created this section.
     */
    public function packageInstall(): HasMany
    {
        return $this->hasMany(PackageInstall::class);
    }

    /**
     * Get users who have access to this section.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'section_access')
            ->withPivot('role', 'granted_by', 'granted_at')
            ->withTimestamps();
    }

    /**
     * Get access records for this section.
     */
    public function access(): HasMany
    {
        return $this->hasMany(SectionAccess::class);
    }

    /**
     * Scope: Only active sections.
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
