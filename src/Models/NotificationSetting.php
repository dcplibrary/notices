<?php

namespace Dcplibrary\Notices\Models;

use Dcplibrary\Notices\Database\Factories\NotificationSettingFactory;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class NotificationSetting extends Model
{
    use HasFactory;

    protected $table = 'notification_settings';

    protected $fillable = [
        'scope',
        'scope_id',
        'group',
        'key',
        'value',
        'type',
        'description',
        'is_public',
        'is_editable',
        'is_sensitive',
        'validation_rules',
        'updated_by',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_editable' => 'boolean',
        'is_sensitive' => 'boolean',
        'validation_rules' => 'array',
    ];

    /**
     * Get the typed value of the setting.
     *
     * @return mixed
     */
    public function getTypedValue()
    {
        $value = $this->value;

        // Decrypt if sensitive
        if ($this->is_sensitive && $this->type === 'encrypted') {
            try {
                $value = Crypt::decryptString($value);
            } catch (Exception $e) {
                return null;
            }
        }

        // Cast to appropriate type
        return match($this->type) {
            'int', 'integer' => (int) $value,
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'float', 'decimal' => (float) $value,
            'json', 'array' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Set the value with automatic type handling.
     *
     * @param mixed $value
     * @return void
     */
    public function setTypedValue($value): void
    {
        // Handle encryption for sensitive values
        if ($this->is_sensitive && $this->type === 'encrypted') {
            $this->value = Crypt::encryptString($value);

            return;
        }

        // Handle JSON encoding
        if ($this->type === 'json' || $this->type === 'array') {
            $this->value = json_encode($value);

            return;
        }

        // Store as string
        $this->value = (string) $value;
    }

    /**
     * Scope to global settings.
     */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('scope');
    }

    /**
     * Scope to specific scope.
     */
    public function scopeForScope(Builder $query, string $scope, ?string $scopeId = null): Builder
    {
        $query->where('scope', $scope);

        if ($scopeId !== null) {
            $query->where('scope_id', $scopeId);
        }

        return $query;
    }

    /**
     * Scope to specific group.
     */
    public function scopeInGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    /**
     * Scope to editable settings.
     */
    public function scopeEditable(Builder $query): Builder
    {
        return $query->where('is_editable', true);
    }

    /**
     * Scope to public settings (can be exposed via API).
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * Get full setting key (group.key).
     */
    public function getFullKeyAttribute(): string
    {
        return "{$this->group}.{$this->key}";
    }

    /**
     * Check if setting can be edited by user.
     */
    public function canEdit(): bool
    {
        return $this->is_editable;
    }

    /**
     * Check if setting should be hidden in UI.
     */
    public function shouldHide(): bool
    {
        return $this->is_sensitive && $this->type === 'encrypted';
    }

    /**
     * Get masked value for display (for sensitive fields).
     */
    public function getMaskedValue(): string
    {
        if ($this->is_sensitive) {
            return '••••••••';
        }

        return $this->value;
    }

    /**
     * Validate value against rules.
     */
    public function validateValue($value): bool
    {
        if (empty($this->validation_rules)) {
            return true;
        }

        $validator = validator(
            ['value' => $value],
            ['value' => $this->validation_rules]
        );

        return !$validator->fails();
    }

    /**
     * Provide the model factory for package context.
     */
    protected static function newFactory()
    {
        return NotificationSettingFactory::new();
    }
}
