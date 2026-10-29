<?php

namespace App\Models;

use App\Enums\IpBanSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IpBan extends Model
{
    use HasFactory;

    protected $table = 'ip_ban';

    protected $fillable = [
        'ip',
        'source',
        'banned_method',
        'banned_url',
        'banned_body',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'source' => 'honeypot',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => IpBanSource::class,
        ];
    }
}
