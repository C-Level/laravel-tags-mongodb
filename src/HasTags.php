<?php

namespace Clevel\Tags;

use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasTags
{
    protected $queuedTags = [];
    protected static $model;


    public static function getTagClassName(): string
    {
        return Tag::class;
    }

    public static function bootHasTags()
    {
        static::$model = get_class(static::getModel());
        static::created(function (Model $taggableModel) {
            if (count($taggableModel->queuedTags) > 0) {
                $taggableModel->attachTags($taggableModel->queuedTags);

                $taggableModel->queuedTags = [];
            }
        });

        static::deleted(function (Model $deletedModel) {
            $tags = $deletedModel->tags()->get();

            $deletedModel->detachTags($tags);
        });
    }

    public function tags(): MorphToMany
    {
        return $this
            ->morphToMany(self::getTagClassName(), 'taggable');
    }

    public function scopeWithRelatedTags(Builder $query, $type = null)
    {
        $ids = \DB::collection('taggables')->where('taggable_type', static::$model)->where('taggable_id', $this->id)->pluck('tag_id');
        $tags = Tag::query()->whereIn('_id', $ids);
        if ($type !== null) {
            $tags->where('type', $type);
        }
        return $tags->get();
    }

    public function taggableIds($type = null)
    {
        $ids = \DB::collection('taggables')->where('taggable_type', static::$model)->where('taggable_id', $this->id)->pluck('tag_id');
        $tags = Tag::query()->whereIn('_id', $ids);
        if ($type !== null) {
            $tags->where('type', $type);
        }
        return $tags->pluck('_id');
    }

    /**
     * @param string|array|\ArrayAccess|\Clevel\Tags\Tag $tags
     */
    public function setTagsAttribute($tags)
    {
        if (!$this->exists) {
            $this->queuedTags = $tags;

            return;
        }

        $this->attachTags($tags);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array|\ArrayAccess|\Clevel\Tags\Tag $tags
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithAllTags(Builder $query, $tags, string $type = null): Builder
    {
        $tags = static::convertToTags($tags, $type);

        collect($tags)->each(function ($tag) use ($query) {
            $query->whereIn("{$this->getTable()}.{$this->getKeyName()}", function ($query) use ($tag) {
                $query->from('taggables')
                    ->select('taggables.taggable_id')
                    ->where('taggables.tag_id', $tag ? $tag->id : 0);
            });
        });

        return $query;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array|\ArrayAccess|\Clevel\Tags\Tag $tags
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithAnyTags(Builder $query, $tags, string $type = null): Builder
    {
        $tags = static::convertToTags($tags, $type);
        $tagIds = $tags->filter(function ($var) {
            return $var instanceof Tag;
        })->pluck('id');
        $ids = \DB::collection('taggables')->where('taggable_type', get_class($this->getModel()))->whereIn('tag_id', $tagIds)->pluck('taggable_id');

        $query->whereIn('id', $ids);
        return $query;
    }

    public function scopeWithAllTagsOfAnyType(Builder $query, $tags): Builder
    {
        $tags = static::convertToTagsOfAnyType($tags);

        collect($tags)->each(function ($tag) use ($query) {
            $query->whereIn("{$this->getTable()}.{$this->getKeyName()}", function ($query) use ($tag) {
                $query->from('taggables')
                    ->select('taggables.taggable_id')
                    ->where('taggables.tag_id', $tag ? $tag->id : 0);
            });
        });

        return $query;
    }

    public function scopeWithAnyTagsOfAnyType(Builder $query, $tags): Builder
    {
        $tags = static::convertToTagsOfAnyType($tags);

        return $query->whereHas('tags', function (Builder $query) use ($tags) {
            $tagIds = collect($tags)->pluck('id');

            $query->whereIn('tags.id', $tagIds);
        });
    }

    public function tagsWithType(string $type = null): Collection
    {
        return $this->tags->filter(function (Tag $tag) use ($type) {
            return $tag->type === $type;
        });
    }

    /**
     * @param array|\ArrayAccess|\Clevel\Tags\Tag $tags
     *
     * @return $this
     */
    public function attachTags($tags)
    {
        $className = static::getTagClassName();

        $tags = collect($className::findOrCreate($tags));

        $this->tags()->syncWithoutDetaching($tags->pluck('id')->toArray());

        return $this;
    }

    /**
     * @param array|\ArrayAccess|\Clevel\Tags\Tag $tags
     * @param string|null $type
     * @return $this
     */
    public function attachTagsWithType($tags, string $type = null)
    {
        $className = static::getTagClassName();

        $tags = collect($className::findOrCreate($tags, $type));

        $this->tags()->syncWithoutDetaching($tags->pluck('id')->toArray());

        return $this;
    }

    /**
     * @param string|\Clevel\Tags\Tag $tag
     *
     * @return $this
     */
    public function attachTag($tag)
    {
        return $this->attachTags([$tag]);
    }

    /**
     * @param string|\Clevel\Tags\Tag $tag
     * @param string|null $type
     * @return $this
     */
    public function attachTagWithType($tag, string $type = null)
    {
        return $this->attachTagsWithType([$tag], $type);
    }

    /**
     * @param array|\ArrayAccess $tags
     * @param string|null $type
     * @return $this
     */
    public function detachTags($tags, string $type = null)
    {
        $tags = static::convertToTags($tags, $type);

        collect($tags)
            ->filter()
            ->each(function (Tag $tag) {
                $this->tags()->detach($tag);
            });

        return $this;
    }

    /**
     * @param string|\Clevel\Tags\Tag $tag
     * @param string|null $type
     * @return $this
     */
    public function detachTag($tag, string $type = null)
    {
        return $this->detachTags([$tag], $type);
    }

    /**
     * @param array|\ArrayAccess $tags
     *
     * @return $this
     */
    public function syncTags($tags)
    {
        $className = static::getTagClassName();

        $tags = collect($className::findOrCreate($tags));

        $this->tags()->sync($tags->pluck('id')->toArray());

        return $this;
    }

    /**
     * @param array|\ArrayAccess $tags
     * @param string|null $type
     *
     * @return $this
     */
    public function syncTagsWithType($tags, string $type = null)
    {
        $className = static::getTagClassName();

        $tags = collect($className::findOrCreate($tags, $type));

        $this->syncTagIds($tags->pluck('_id')->toArray(), $type);

        return $this;
    }

    protected static function convertToTags($values, $type = null)
    {
        return collect($values)->map(function ($value) use ($type) {
            if ($value instanceof Tag) {
                if (isset($type) && $value->type != $type) {
                    throw new InvalidArgumentException("Type was set to {$type} but tag is of type {$value->type}");
                }

                return $value;
            }

            $className = static::getTagClassName();

            return $className::findFromString($value, $type);
        });
    }

    protected static function convertToTagsOfAnyType($values)
    {
        return collect($values)->map(function ($value) {
            if ($value instanceof Tag) {
                return $value;
            }

            $className = static::getTagClassName();

            return $className::findFromStringOfAnyType($value);
        });
    }

    /**
     * Use in place of eloquent's sync() method so that the tag type may be optionally specified.
     *
     * @param $ids
     * @param string|null $type
     * @param bool $detaching
     */
    protected function syncTagIds($ids, string $type = null, $detaching = true)
    {
        $isUpdated = false;

        // Get a list of tag_ids for all current tags
        $current = $this->taggableIds()
            ->all();
        // Compare to the list of ids given to find the tags to remove
        $detach = array_diff($current, $ids);
        if ($detaching && count($detach) > 0) {
            $this->tags()->detach($detach);
            $isUpdated = true;
        }

        // Attach any new ids
        $attach = array_diff($ids, $current);
        if (count($attach) > 0) {
            collect($attach)->each(function ($id) {
                $this->tags()->attach($id, []);
            });
            $isUpdated = true;
        }

        // Once we have finished attaching or detaching the records, we will see if we
        // have done any attaching or detaching, and if we have we will touch these
        // relationships if they are configured to touch on any database updates.
        if ($isUpdated) {
            $this->tags()->touchIfTouching();
        }
    }
}
