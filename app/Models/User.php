<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Profils disponibles : valeur en base => libellé affiché
     */
    public const ROLES = [
        'admin' => 'Administrateur',
        'dba' => 'DBA',
        'auditeur' => 'Auditeur',
        'lecteur' => 'Lecteur',
    ];

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'locked_until' => 'datetime',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Audits lancés par cet utilisateur.
     */
    public function auditRuns(): HasMany
    {
        return $this->hasMany(AuditRun::class);
    }

    /**
     * Exceptions proposées par cet utilisateur (DBA).
     */
    public function requestedExceptions(): HasMany
    {
        return $this->hasMany(FindingException::class, 'requested_by');
    }

    /**
     * Exceptions validées ou refusées par cet utilisateur (auditeur).
     */
    public function reviewedExceptions(): HasMany
    {
        return $this->hasMany(FindingException::class, 'reviewed_by');
    }

    public function reportExports(): HasMany
    {
        return $this->hasMany(ReportExport::class);
    }

    /**
     * Le compte est-il verrouillé après trop d'échecs de connexion ?
     */
    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
