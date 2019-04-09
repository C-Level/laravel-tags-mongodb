# Add tags and taggable behaviour to a Laravel app

Here are some code examples:

```php
//create a model with some tags
$newsItem = NewsItem::create([
   'name' => 'testModel',
   'tags' => ['tag', 'tag2'], //tags will be created if they don't exist
]);

//attaching tags
$newsItem->attachTag('tag3');
$newsItem->attachTags(['tag4', 'tag5']);

//detaching tags
$newsItem->detachTags('tag3');
$newsItem->detachTags(['tag4', 'tag5']);

//syncing tags
$newsItem->syncTags(['tag1', 'tag2']); // all other tags on this model will be detached

//syncing tags with a type
$newsItem->syncTagsWithType(['tag1', 'tag2'], 'typeA');
$newsItem->syncTagsWithType(['tag1', 'tag2'], 'typeB');

//retrieving tags with a type
$newsItem->tagsWithType('typeA');
$newsItem->tagsWithType('typeB');

//retrieving models that have any of the given tags
NewsItem::withAnyTags(['tag1', 'tag2'])->get();

//retrieve models that have all of the given tags
NewsItem::withAllTags(['tag1', 'tag2'])->get();

//translating a tag
$tag = Tag::findOrCreate('my tag');
$tag->setTranslation('name', 'fr', 'mon tag');
$tag->setTranslation('name', 'nl', 'mijn tag');
$tag->save();

//getting translations
$tag->translate('name'); //returns my name
$tag->translate('name', 'fr'); //returns mon tag (optional locale param)

//convenient translations through taggable models
$newsItem->tagsTranslated();// returns tags with slug_translated and name_translated properties
$newsItem->tagsTranslated('fr');// returns tags with slug_translated and name_translated properties set for specified locale

//using tag types
$tag = Tag::findOrCreate('tag 1', 'my type');

//tags have slugs
$tag = Tag::findOrCreate('yet another tag');
$tag->slug; //returns "yet-another-tag"

//tags are sortable
$tag = Tag::findOrCreate('my tag');
$tag->order_column; //returns 1
$tag2 = Tag::findOrCreate('another tag');
$tag2->order_column; //returns 2

//manipulating the order of tags
$tag->swapOrder($anotherTag);
```

## Requirements

This package requires Laravel 5.7 or higher, PHP 7.0 or higher and a database that supports `json` fields and functions such as MySQL 5.7 or higher.

## Installation

You can install the package via composer:

```bash
composer require clevel/laravel-tags
```

The package will automatically register itself.

```bash
php artisan vendor:publish --provider="Clevel\Tags\TagsServiceProvider" --tag="config"
```

This is the contents of the published config file:

```php
return [

    /*
     * The given function generates a URL friendly "slug" from the tag name property before saving it.
     */
    'slugger' => 'str_slug',
];
```
