<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

use App\User;
use App\CanvasPostsTags as PostsTags;
use \Wink\WinkAuthor;
use \Wink\WinkPage;
use \Wink\WinkPost;
use \Wink\WinkTag;
use \Canvas\UserMeta;
use \Canvas\Page;
use \Canvas\Post;
use \Canvas\Tag;

use Str;
use Hash;

class MigrateWinkToCanvasCommand extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'wink:to-canvas';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Takes a Wink Blog and moves it to Canvas';

  /**
   * Create a new command instance.
   *
   * @return void
   */
  public function __construct()
  {
    parent::__construct();
  }

  /**
   * Execute the console command.
   *
   * @return int
   */
  public function handle()
  {
    // Map WinkAuthor to User wherever possible
    $wink_author_mapping = array();
    $wink_authors = WinkAuthor::all();
    $wink_authors->each(function($author) use (&$wink_author_mapping) {
      $this->info("Processing Author: {$author->name}");
      $user = User::whereEmail($author->email)->first();
      if ($user) {
        array_push($wink_author_mapping,[
          'user_id' => $user->id,
          'wink_author_id' => $author->id,
        ]);
        if (!UserMeta::whereUserId($user->id)->first()) {
          UserMeta::create([
            'user_id' => $user->id,
            'username' => $author->slug,
            'summary' => $author->bio,
            'avatar' => $author->avatar,
            'dark_mode' => 0,
            'digest' => 0,
          ]);
        }
      } else {
        $new_pw = Hash::make(Str::random(12));
        $this->warn("Creating new user: {$author->name}. This new user's password is \"${new_pw}\".");
        $user = User::create([
          'email' => $author->email,
          'name' => $author->name,
          'password' => $new_pw,
        ]);
        array_push($wink_author_mapping,[
          'user_id' => $user->id,
          'wink_author_id' => $author->id,
        ]);
        UserMeta::create([
          'user_id' => $user->id,
          'username' => $author->slug,
          'summary' => $author->bio,
          'avatar' => $author->avatar,
          'dark_mode' => 0,
          'digest' => 0,
        ]);
      }
    });

    // Map Wink Tags
    $wink_tags = WinkTag::all();
    $wink_tags->each(function($tag) use ($wink_author_mapping) {
      $this->info("Processing Tag: {$tag->name}");
      Tag::firstOrCreate([
        'id' => $tag->id,
        'slug' => $tag->slug,
        'name' => $tag->name,
        'user_id' => $wink_author_mapping[0]['user_id'],
      ]);
    });

    // Map Wink Pages
    $wink_pages = WinkPage::all();
    // TODO: If Canvas supports pages, add pages here.

    // Map Wink Posts
    $wink_posts = WinkPost::with('tags')->get();
    $wink_posts->each(function($post) use ($wink_author_mapping) {
      $author = collect($wink_author_mapping)->firstWhere('wink_author_id',$post->author_id);
      $this->info("Processing Post: {$post->title}");
      if ( ! Post::where('id', $post->id) ) {
        Post::firstOrCreate([
          'id' => $post->id,
          'slug' => $post->slug,
          'title' => $post->title,
          'summary' => $post->excerpt,
          'body' => $post->body,
          'published_at' => $post->published_date,
          'featured_image' => $post->featured_image,
          'featured_image_caption' => $post->featured_image_caption,
          'user_id' => $author['user_id'],
          'meta' => $post->meta,
        ]);
      }
      $tags = $post->tags;
      if (sizeof($tags) > 0) {
        $tags->each(function($tag) use ($post) {
          if ( ! PostsTags::where('post_id',$post->id)->where('tag_id',$tag->id)->first() ) {
            $this->info("Processing Tag \"{$tag->name}\" on Post.");
            PostsTags::firstOrCreate([
              'post_id' => $post->id,
              'tag_id' => $tag->id,
            ]);
          }
        });
      }
    });

    $this->info("Done");
  }
}
