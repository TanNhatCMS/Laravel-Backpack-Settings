<?php

namespace Backpack\Settings\app\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;
use TanNhatCMS\CachingModel\Contracts\Cacheable;
use TanNhatCMS\CachingModel\HasCache;

class Setting extends Model implements Cacheable
{
    use CrudTrait, HasCache;

    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->table = config('backpack.settings.table_name');
    }

    public static function primaryCacheKey(): string
    {
        return 'key';
    }

    /**
     * Grab a setting value from the database.
     *
     * @param string $key The setting key, as defined in the key db column
     * @param null $default
     * @return string|null The setting value.
     */
    public static function get(string $key, $default = null): ?string
    {
        $entry = static::fromCache()->find($key);

        if (!$entry) {
            return $default;
        }

        return $entry->value;
    }

    /**
     * Update a setting's value.
     *
     * @param string $key The setting key, as defined in the key db column
     * @param string|null $value The new value.
     */
    public static function set(string $key, ?string $value = null): bool
    {
        $database_prefix = config('backpack.settings.database_prefix');

        $prefixed_key = !empty($database_prefix) ? $database_prefix . '.' . $key : $key;
        $setting = new self();
        $entry = $setting->where('key', $key)->firstOrFail();

        // update the value in the database
        $entry->value = $value;
        $entry->saveOrFail();

        // update the value in the session
        Config::set($prefixed_key, $value);

        if (Config::get($prefixed_key) == $value) {
            return true;
        }

        return false;
    }
}
