<?php

namespace Modules\Settings\Entities;

use Illuminate\Database\Eloquent\Model;

class TenantSetting extends Model
{
    protected $connection = 'tenant';

    protected $table = 'tenant_settings';

    protected $fillable = ['key', 'value', 'type', 'category', 'description'];

    protected function casts(): array
    {
        return [];
    }

    public function getTypedValueAttribute(): mixed
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'boolean' => (bool) $this->value,
            'json' => json_decode($this->value ?? 'null', true),
            default => $this->value,
        };
    }
}
