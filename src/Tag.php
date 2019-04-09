<?php

namespace Clevel\Tags;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as DbCollection;

class Tag extends \Jenssegers\Mongodb\Eloquent\Model
{
    use HasSlug;

    public $guarded = [];

    public function scopeWithType(Builder $query, string $type = null): Builder
    {
        if (is_null($type)) {
            return $query;
        }

        return $query->where('type', $type)->orderBy('order_column');
    }

    public function scopeContaining(Builder $query, string $name): Builder
    {
        return $query->where('name','like','%'.strtolower($name).'%');
    }

    /**
     * @param array|\ArrayAccess $values
     * @param string|null $type
     * @param string|null $locale
     *
     * @return \Clevel\Tags\Tag|static
     */
    public static function findOrCreate($values, string $type = null)
    {
        $tags = collect($values)->map(function ($value) use ($type) {
            if ($value instanceof Tag) {
                return $value;
            }

            return static::findOrCreateFromString($value, $type);
        });

        return is_string($values) ? $tags->first() : $tags;
    }

    public static function getWithType(string $type): DbCollection
    {
        return static::withType($type)->orderBy('order_column')->get();
    }

    public static function findFromString(string $name, string $type = null)
    {

        return static::query()
            ->where("name", $name)
            ->where('type', $type)
            ->first();
    }

    public static function findFromStringOfAnyType(string $name)
    {

        return static::query()
            ->where("name", $name)
            ->first();
    }

    protected static function findOrCreateFromString(string $name, string $type = null): self
    {

        $tag = static::findFromString($name, $type);

        if (! $tag) {
            $tag = static::create([
                'name' => $name,
                'type' => $type,
            ]);
        }

        return $tag;
    }

    public function setAttribute($key, $value)
    {
        return parent::setAttribute($key, $value);
    }
}
