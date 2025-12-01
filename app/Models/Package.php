<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $table = 'section_packages';

    protected $fillable = [
        'package_id',
        'name',
        'display_name',
        'description',
        'author',
        'author_email',
        'author_organization',
        'license',
        'version',
        'package_data',
        'uploaded_by',
        'uploaded_at',
        'file_path',
        'file_size',
        'file_hash',
        'validation_status',
        'can_install',
        'category',
        'tags',
        'download_count',
        'rating_avg',
        'rating_count',
        'is_public',
        'is_featured',
        'requires_approval',
        'hub_version_min',
        'hub_version_max',
        'php_version_min',
        'mysql_version_min',
        'dependencies',
        'conflicts',
        'tested_up_to',
        'is_deprecated',
        'deprecation_reason',
        'changelog',
        'screenshots',
        'repository_url',
        'demo_url',
        'support_url',
    ];

    protected $casts = [
        'package_data' => 'array',
        'tags' => 'array',
        'dependencies' => 'array',
        'conflicts' => 'array',
        'screenshots' => 'array',
        'can_install' => 'boolean',
        'is_public' => 'boolean',
        'is_featured' => 'boolean',
        'requires_approval' => 'boolean',
        'is_deprecated' => 'boolean',
        'file_size' => 'integer',
        'download_count' => 'integer',
        'rating_count' => 'integer',
        'rating_avg' => 'decimal:2',
        'uploaded_at' => 'datetime',
    ];

    /**
     * Get installations of this package.
     */
    public function installs(): HasMany
    {
        return $this->hasMany(PackageInstall::class, 'package_id', 'package_id');
    }

    /**
     * Get reviews for this package.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(PackageReview::class, 'package_id', 'package_id');
    }

    /**
     * Get the user who uploaded this package.
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Scope: Only installable packages.
     */
    public function scopeInstallable($query)
    {
        return $query->where('can_install', true);
    }

    /**
     * Scope: Only public packages.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope: Only featured packages.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope: Not deprecated.
     */
    public function scopeActive($query)
    {
        return $query->where('is_deprecated', false);
    }

    /**
     * Scope: By category.
     */
    public function scopeOfCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
