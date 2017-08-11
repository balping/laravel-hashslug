# Laravel HashSlug (Hashids)

This package is useful to hide real model ids in urls using [Hashids](https://github.com/ivanakimov/hashids.php). A hashid (slug) is deterministically generated given an application, a model class and an id. Also, given a hashid (slug), the real id can be decoded. Thus no extra field needs to be stored in the database, ids are decoded on each request.

Generates urls on the fly
```
database -> id (1) -> hashslug (K4Nkd) -> url (http://localhost/posts/K4Nkd)
```

Decodes hashids and finds models on the fly
```
url (http://localhost/posts/K4Nkd) -> hashslug (K4Nkd) -> id (1) -> database -> model
```

Hashslugs have the following properties:

1. It is guaranteed that hashslugs are unique per id
2. It is guaranteed that for different models, different series of hashslugs are generated (a post of id 1 will have a different hashslug as a comment with id 1)
3. It is guaranteed that for different installations, different series of hashslugs are generated (depending on app key in the `.env` file)

It is important to note that hashids are __not random, nor unpredictable__. Do not use this package if that's a concern. Quoting from [hashids.org](http://hashids.org/#decoding): 

> Do you have a question or comment that involves "security" and "hashids" in the same sentence? Don't use Hashids.

However, although hashslug encoding depends on the app key, it cannot be exposed by an attacker, since it's [sha256 hashed](https://github.com/balping/laravel-hashslug/blob/master/src/HasHashSlug.php#L56) before passing it to Hashids. Your app key is safe.

## Installation

```bash
composer require balping/laravel-hashslug
```

## Usage

Include trait on a model that you wish to have hashid slugs to hide numeric incremental ids.

```php

use Illuminate\Database\Eloquent\Model;
use Balping\HashSlug\HasHashSlug;

class Post extends Model {
  use HasHashSlug;
}
```

After this, functions `slug()`, `findBySlug($slug)` and `findBySlugOrFail($slug)` are added to your model.

Every time you generate a url using Laravel's helpers, instead of numeric ids, hashids are used (with the default length of 5 characters):


```php
// routes/web.php
Route::resource('/posts', 'PostController');

// somewhere else
$post = Post::first();
echo action('PostController@show', $post);
// prints http://localhost/posts/K4Nkd
```

Then you can resolve the model by the slug.


```php
// app/Http/Controllers/PostController.php

public function show($slug){
  $post = Post:findBySlugOrFail($slug);
  
  return view('post.show', compact('post'));
}
```

You can use [explicit model binding](https://laravel.com/docs/master/routing#explicit-binding) too.

Just add this code to `RouteServiceProvider@boot`

```php
Route::bind('post', function ($slug) {
  return Post::findBySlugOrFail($slug);
});
```

After that typehinted models are automatically resolved:

```php
// app/Http/Controllers/PostController.php

public function show(Post $post){
  return view('post.show', compact('post'));
}
```

## Customisation

### Padding

Change the minimum length of a slug (default: 5)

```php
class Post extends Model {
	use HasHashSlug;

	protected static $minSlugLength = 10;
}
```

### Salts

The uniqueness of hashslug series per model and app installation depends on having unique salts.

By default, the salt passed to Hashids depends on the app key defined in `.env` and the class name of the model.

#### Application salt

To change the 'application salt', create file `config/hashslug.php` then add the following code:

```php
<?php

return [
	'appsalt' => 'your-application-salt'
];
```

Keep in mind that you don't have to configure this, but unless you do and  your app key is changed, every url having hashslugs in it will change. This might be a problem for example if a user bookmarked such a url.

#### Model salt

To use a custom model salt instead of the classname:

```php
class Post extends Model {
	use HasHashSlug;

	protected static $modelSalt = "posts";
}
```

This might be a good idea to do, if you have several extended classes of the same model and you need hashslugs to be consistent.


#### Alphabet

The default alphabet is `abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890`

This can be changed:

```php
class Post extends Model {
	use HasHashSlug;

	protected static $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
}
```

## Similar packages and how is this one different

#### [Laravel Hashids](https://github.com/vinkla/laravel-hashids)

Provides a facade, but no built-in routing. Allows multiple salts through "connections". Unnecessary overhead if you need hashids only for slugging models.

#### [Laravel-Hashid](https://github.com/KissParadigm/Laravel-Hashid)

Provides a facade, similar to the above one PLUS a trait similar to this package. No no built-in routing. Untested. Unnecessary overhead if you need hashids only for slugging models.

#### [Hashids for Laravel 5](https://github.com/Torann/laravel-hashids)

Facade only. Not as good as the first one, since it allows you to have only one salt.

#### [Optimus](https://github.com/jenssegers/optimus)

Uses different obfuscation method. Facade (and class) only. Nothing related to routing or model traits. It is said to be faster than hashids.




## License

This package (the trait and the test file) is licensed under GPLv3.
