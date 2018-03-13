<?php

/*

Laravel HashSlug: Package providing a trait to use Hashids on a model
Copyright (C) 2017-2018  Balázs Dura-Kovács

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/

namespace Balping\HashSlug\Tests;


use Illuminate\Database\Eloquent\Model;
use Balping\HashSlug\HasHashSlug;
use Illuminate\Support\Facades\Route;
use Illuminate\Routing\Controller;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Foundation\Testing\Concerns\InteractsWithExceptionHandling;

class HashSlugTest extends \Orchestra\Testbench\TestCase
{
	public function setUp() {
		parent::setUp();
		$this->configureDatabase();
	}

	protected function configureDatabase(){
		$db = new DB;
		$db->addConnection([
			'driver'    => 'sqlite',
			'database'  => ':memory:',
			'charset'   => 'utf8',
			'collation' => 'utf8_unicode_ci',
			'prefix'    => '',
		]);
		$db->bootEloquent();
		$db->setAsGlobal();
		DB::schema()->create('posts', function ($table) {
			$table->increments('id');
			$table->string('title');
			$table->timestamps();
		});

		DB::schema()->create('comments', function ($table) {
			$table->increments('id');
			$table->string('body');
			$table->timestamps();
		});
	}

	/** @test */
	public function model_has_a_unique_slug(){
		$post1 = Post::forceCreate(["title" => "title1"]);

		$slug1 = $post1->slug();

		$this->assertEquals(5, strlen($slug1));

		$post2 = Post::forceCreate(["title" => "title2"]);

		$slug2 = $post2->slug();

		$this->assertFalse($slug1 == $slug2);
	}

	/** @test */
	public function model_can_be_found_by_slug(){
		$post = Post::forceCreate(["title" => "title1"]);

		$slug = $post->slug();

		$foundPost = Post::findBySlugOrFail($slug);

		$this->assertEquals($post->id, $foundPost->id);
	}

	/** @test */
	public function find_byinvalid_slug_returns_null(){
		$post = Post::forceCreate(["title" => "title1"]);

		$slug = 'XX' . $post->slug();

		$foundPost = Post::findBySlug($slug);

		$this->assertNull($foundPost);
	}

	/** @test */
	public function slugs_are_different_for_same_id_but_different_model(){
		$post = Post::forceCreate(["title" => "title1"]);

		$comment = Comment::forceCreate(["body" => "comment1"]);

		$this->assertEquals($post->id, $comment->id);

		$this->assertNotEquals($post->slug(), $comment->slug());
	}

	/** @test */
	public function slug_padding_can_be_set(){
		$post = PostLongSlug::forceCreate(["title" => "title1"]);

		$this->assertEquals(10, strlen($post->slug()));
	}

	/** @test */
	public function model_salt_can_be_customised(){
		$post = PostCustomSalt::forceCreate(["title" => "title1"]);
		$comment = CommentCustomSalt::forceCreate(["body" => "comment1"]);

		$this->assertEquals($post->slug(), $comment->slug());
	}

	/** @test */
	public function alphabet_can_be_customised(){
		$post = PostCustomAlphabet::forceCreate(["title" => "title1"]);

		$this->assertRegExp('/^[A-Z]{50}$/', $post->slug());
	}

	/** @test */
	public function urls_are_generated_using_slug(){
		Route::resource('/posts-nobind', '\Balping\HashSlug\Tests\PostControllerNoBind', [
			"middleware"	=> \Illuminate\Routing\Middleware\SubstituteBindings::class
		]);

		$post = Post::forceCreate(["title" => "title1"]);

		$this->assertEquals(
			'http://localhost/posts-nobind/' . $post->slug(),
			action('\Balping\HashSlug\Tests\PostControllerNoBind@show', $post)
		);

		$response = $this->get('/posts-nobind/' . $post->slug());

		$this->assertEquals($post->slug(), $response->getContent());
	}

	/** @test */
	public function implicit_route_model_binging(){
		Route::resource('/posts', '\Balping\HashSlug\Tests\PostController', [
			"middleware"	=> \Illuminate\Routing\Middleware\SubstituteBindings::class
		]);

		$post = Post::forceCreate(["title" => "title1"]);

		$this->app['config']->set('app.debug', true);

		$response = $this->get('/posts/' . $post->slug());

		$this->assertEquals($post->slug(), $response->getContent());
	}

	/** @test */
	public function explicit_route_model_binging(){
		Route::model('post', Post::class);

		Route::resource('/posts', '\Balping\HashSlug\Tests\PostController', [
			"middleware"	=> \Illuminate\Routing\Middleware\SubstituteBindings::class
		]);

		$post = Post::forceCreate(["title" => "title1"]);

		$this->app['config']->set('app.debug', true);

		$response = $this->get('/posts/' . $post->slug());

		$this->assertEquals($post->slug(), $response->getContent());
	}

}

class Post extends Model {
	use HasHashSlug;
}

class PostLongSlug extends Model {
	use HasHashSlug;

	protected static $minSlugLength = 10;

	protected $table = "posts";
}

class PostCustomSalt extends Model {
	use HasHashSlug;

	protected static $modelSalt = "customsalt";

	protected $table = "posts";
}

class PostCustomAlphabet extends Model {
	use HasHashSlug;

	protected static $minSlugLength = 50; //reduce chance
	protected static $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

	protected $table = "posts";
}

class CommentCustomSalt extends Model {
	use HasHashSlug;

	protected static $modelSalt = "customsalt";

	protected $table = "comments";
}


class Comment extends Model {
	use HasHashSlug;
}

class PostController extends Controller {
	public function show(Post $post){
		return $post->slug();
	}
}

class PostControllerNoBind extends Controller {
	public function show($slug){
		$post = Post::findBySlugOrFail($slug);
		return $post->slug();
	}
}