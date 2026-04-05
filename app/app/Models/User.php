<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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