<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /**
     * Get a JSON-decoded setting value with a default fallback.
     */
    public static function getValue(string $key, $default = null)
    {
        $row = static::find($key);
        if (! $row || $row->value === null) {
            return $default;
        }
        $decoded = json_decode($row->value, true);
        return $decoded === null ? $default : $decoded;
    }

    /**
     * Store a JSON-encoded setting value.
     */
    public static function setValue(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
    }
}
