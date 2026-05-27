<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Represents a user in the system.
 *
 * @property int                             $id                PK
 * @property string                          $name              User's name
 * @property string                          $email             User's email address
 * @property timestamp                       $email_verified_at Email verification timestamp
 * @property string                          $password          Hashed password
 * @property string                          $role              User's role (e.g., admin, writer, viewer)
 * @property string|null                     $remember_token    Token for "remember me" sessions
 * @property timestamp                       $created_at        Creation timestamp
 * @property timestamp                       $updated_at        Last update timestamp
 */
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    const TABLE                 = 'users';
    const COL_ID                = 'id';
    const COL_NAME              = 'name';
    const COL_EMAIL             = 'email';
    const COL_PASSWORD          = 'password';
    const COL_ROLE              = 'role';
    const COL_EMAIL_VERIFIED_AT = 'email_verified_at';
    const COL_REMEMBER_TOKEN    = 'remember_token';

    protected $table = self::TABLE;

    protected $fillable = [
        self::COL_NAME, 
        self::COL_EMAIL, 
        self::COL_PASSWORD, 
        self::COL_ROLE
    ];

    protected function casts(): array
    {
        return [
            self::COL_EMAIL_VERIFIED_AT => 'datetime', 
            self::COL_PASSWORD          => 'hashed'
        ];
    }

    public function isAdmin(): bool  { return $this->{self::COL_ROLE} === 'admin'; }
    public function isWriter(): bool { return $this->{self::COL_ROLE} === 'writer'; }
    public function isViewer(): bool { return $this->{self::COL_ROLE} === 'viewer'; }

    public function canWrite(): bool { return in_array($this->{self::COL_ROLE}, ['admin', 'writer']); }
    public function canDelete(): bool { return $this->{self::COL_ROLE} === 'admin'; }

    protected static function newFactory(): Factory
    {
        return \Database\Factories\UserFactory::new();
    }
}