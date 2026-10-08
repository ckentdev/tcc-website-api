<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\CmsModule;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'is_admin', 'cms_modules', 'job_title', 'position', 'bio', 'profile_links', 'profile_photo_url', 'cover_photo_url'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'cms_modules' => 'array',
            'profile_links' => 'array',
        ];
    }

    /**
     * @return list<string>
     */
    public function resolvedCmsModules(): array
    {
        if ($this->is_admin) {
            return CmsModule::ALL;
        }

        return CmsModule::normalize($this->cms_modules);
    }

    public function hasCmsModule(string $module): bool
    {
        return in_array($module, $this->resolvedCmsModules(), true);
    }

    public function canAccessCms(): bool
    {
        return $this->is_admin || $this->resolvedCmsModules() !== [];
    }

    public function mustChangePassword(): bool
    {
        $default = (string) config('cms.default_author_password', '12345678');

        if ($default === '') {
            return false;
        }

        return Hash::check($default, $this->password);
    }

    /**
     * @return HasMany<Article, $this>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    /**
     * @return HasMany<ResearchPublication, $this>
     */
    public function researchPublications(): HasMany
    {
        return $this->hasMany(ResearchPublication::class);
    }

    /**
     * @return HasMany<ProgramPage, $this>
     */
    public function programPages(): HasMany
    {
        return $this->hasMany(ProgramPage::class);
    }
}
