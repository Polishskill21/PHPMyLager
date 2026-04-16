<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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

    protected $fillable = ['name', 'email', 'password', 'role'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }

    public function isAdmin() : bool  { return $this->role === 'admin'; }
    public function isWriter(): bool  { return $this->role === 'writer'; }
    public function isViewer(): bool  { return $this->role === 'viewer'; }

    public function canWrite() : bool  { return in_array($this->role, ['admin', 'writer']); }
    public function canDelete(): bool  { return $this->role === 'admin'; }
}