<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectWizardDraft extends Model
{
    use HasFactory;

    protected $attributes = [
        'step' => 1,
        'data' => '[]',
    ];

    protected $fillable = [
        'user_id',
        'step',
        'data',
    ];

    public static function findOrCreateForUser(int $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            ['step' => 1, 'data' => []],
        );
    }

    protected static function booted(): void
    {
        static::creating(function (self $draft): void {
            if ($draft->data === null) {
                $draft->data = [];
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
