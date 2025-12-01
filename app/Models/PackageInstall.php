<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageInstall extends Model
{
    protected $table = 'section_package_installs';

    protected $fillable = [
        'package_id',
        'section_id',
        'installed_by',
        'installed_at',
        'version',
        'status',
        'config_data',
    ];

    protected $casts = [
        'config_data' => 'array',
        'installed_at' => 'datetime',
    ];

    /**
     * Get the package for this installation.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id', 'package_id');
    }

    /**
     * Get the user who installed this package.
     */
    public function installer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installed_by');
    }

    /**
     * Get the section (work area) created by this install.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
