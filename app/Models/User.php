<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'usuarios';

    protected $fillable = [
        'nombre_completo', 'cedula', 'email', 'direccion', 'password', 'rol',
        'numero_telefono', 'activo',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'activo' => 'boolean'];
    }

    public function nombres(): HasMany
    {
        return $this->hasMany(UserName::class, 'usuario_id');
    }

    public function direcciones(): HasMany
    {
        return $this->hasMany(UserAddress::class, 'usuario_id');
    }

    public function reportesCreados(): BelongsToMany
    {
        return $this->belongsToMany(Report::class, 'usuario_reporte', 'usuario_id', 'reporte_id');
    }

    public function getNombreAttribute(): string
    {
        $name = $this->relationLoaded('nombres') ? $this->nombres->first() : $this->nombres()->first();

        return $name ? trim("{$name->nombre} {$name->apellido}") : "Usuario {$this->id}";
    }

    public function roleName(): string
    {
        if ($this->rol === 'Administrador') {
            return 'administrador';
        }
        if (TechnicalChief::whereKey($this->id)->exists()) {
            return 'tecnico_lider';
        }
        if (Technician::whereKey($this->id)->exists()) {
            return 'tecnico';
        }

        return 'empleado';
    }

    public function getHierarchyLevel(): int
    {
        return match ($this->roleName()) {
            'administrador' => 1,
            'tecnico_lider' => 2,
            'tecnico' => 3,
            default => 4,
        };
    }

    public function hasHierarchyAtLeast(int $level): bool
    {
        return $this->getHierarchyLevel() <= $level;
    }

    public function isAdmin(): bool
    {
        return $this->roleName() === 'administrador';
    }

    public function isLeader(): bool
    {
        return $this->roleName() === 'tecnico_lider';
    }

    public function isTechnician(): bool
    {
        return in_array($this->roleName(), ['administrador', 'tecnico_lider', 'tecnico'], true);
    }

    public function isEmployee(): bool
    {
        return $this->roleName() === 'empleado';
    }

    public function getEspecialidadAttribute(): ?string
    {
        return Technician::find($this->id)?->tipo_tecnico;
    }

    public function getRoleTitleAttribute(): string
    {
        return match ($this->roleName()) {
            'administrador' => 'Administrador',
            'tecnico_lider' => 'Jefe Técnico',
            'tecnico' => 'Técnico '.$this->especialidad,
            default => 'Empleado',
        };
    }

    public function canAccessArea(string $area): bool
    {
        return match ($area) {
            'dashboard', 'reportes' => true,
            'activos', 'mapa', 'calendario', 'mantenimientos', 'consumibles' => $this->isTechnician(),
            'estadisticas', 'infraestructura' => $this->hasHierarchyAtLeast(2),
            'usuarios' => $this->isAdmin(),
            default => false,
        };
    }

    public function sessionProfile(): array
    {
        $name = $this->nombre;
        return [
            'user' => [
                'id' => $this->id, 'name' => $name, 'email' => $this->email,
                'role' => $this->roleName(), 'role_title' => $this->role_title,
                'is_leader' => $this->isLeader(), 'specialty' => $this->especialidad,
                'avatar' => mb_strtoupper(collect(explode(' ', $name))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('')),
            ],
            'role' => $this->roleName(), 'hierarchy' => $this->getHierarchyLevel(),
            'allowed_areas' => array_values(array_filter(['dashboard', 'activos', 'mapa', 'reportes', 'calendario', 'estadisticas', 'mantenimientos', 'consumibles', 'usuarios', 'infraestructura'], fn ($area) => $this->canAccessArea($area))),
            'permissions' => [
                'can_manage_assets' => $this->isAdmin(),
                'can_view_users' => $this->isAdmin(),
                'can_manage_reports' => $this->isLeader(),
                'can_view_notifications' => $this->isLeader(),
                'can_view_all_stats' => $this->hasHierarchyAtLeast(2),
                'can_view_budget' => $this->hasHierarchyAtLeast(2),
                'can_view_audit' => $this->hasHierarchyAtLeast(2),
                'can_manage_calendar' => $this->hasHierarchyAtLeast(2),
                'can_maintain_assets' => $this->isTechnician(),
                'can_manage_infrastructure' => $this->hasHierarchyAtLeast(2),
            ],
        ];
    }

    public function canManageAssetSpecialty(?Asset $asset): bool
    {
        if (! $asset || $this->isAdmin() || $this->isLeader()) {
            return (bool) $asset;
        }
        if (! $this->isTechnician() || ! $this->especialidad) {
            return false;
        }

        // El Hito 1 define tipos técnicos, pero no asigna categorías de activos a cada tipo.
        // No se infiere una restricción inexistente en el nuevo modelo.
        return true;
    }
}
