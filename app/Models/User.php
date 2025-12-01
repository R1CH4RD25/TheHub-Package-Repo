<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Model
{
    protected $table = 'users';

    protected $fillable = [
        'google_id',
        'email',
        'username',
        'password',
        'name',
        'role',
        'is_active',
        'last_login',
        'invited_by',
        'invited_at',
        'approved_by',
        'approved_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login' => 'datetime',
        'invited_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Get section access for this user.
     */
    public function sectionAccess(): HasMany
    {
        return $this->hasMany(SectionAccess::class);
    }

    /**
     * Get sections this user has access to.
     */
    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'section_access')
            ->withPivot('role', 'granted_by', 'granted_at')
            ->withTimestamps();
    }

    /**
     * Get module access for this user.
     */
    public function moduleAccess(): HasMany
    {
        return $this->hasMany(UserModuleAccess::class);
    }

    /**
     * Get modules this user has access to.
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'user_module_access')
            ->withPivot('role', 'is_active', 'granted_by', 'granted_at')
            ->withTimestamps();
    }

    /**
     * Get packages uploaded by this user.
     */
    public function uploadedPackages(): HasMany
    {
        return $this->hasMany(Package::class, 'uploaded_by');
    }

    /**
     * Get package installs created by this user.
     */
    public function packageInstalls(): HasMany
    {
        return $this->hasMany(PackageInstall::class, 'installed_by');
    }

    /**
     * Get audit logs created by this user.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }

    /**
     * Check if user has specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user has any of the specified roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    /**
     * Check if user is admin or super_admin.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    /**
     * Check if user is super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Get display name (alias for compatibility).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name;
    }
}
