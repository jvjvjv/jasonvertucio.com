<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

use App\Models\User;
use App\Models\CanvasPostsTags as PostsTags;

use \Wink\WinkAuthor;
use \Wink\WinkPage;
use \Wink\WinkPost;
use \Wink\WinkTag;

use \Canvas\Models\User as CanvasUser;
use \Canvas\Models\Page as CanvasPage;
use \Canvas\Models\Post as CanvasPost;
use \Canvas\Models\Tag  as CanvasTag;

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

  private $winkAuthorMapping;


  /**
   * Create a new command instance.
   *
   * @return void
   */
  public function __construct()
  {
    $this->winkAuthorMapping = array();
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
    $this->info("Mapping Wink Authors to users ...");
    $this->mapWinkAuthors();

    // Map Wink Tags
    $this->info("Mapping Wink Tags ...");
    $this->mapWinkTags();

    // Map Wink Pages
    $this->info("Mapping Wink Pages ...");
    $this->mapWinkPages();

    // Map Wink Posts
    $this->info("Mapping Wink Posts ...");
    $this->mapWinkPosts();

    $this->info("Done");
  }

  private function mapWinkAuthors() {
    $wink_authors = WinkAuthor::all();
    $wink_authors->each(function($author) {
      $this->info("Processing Author: {$author->name}");
      $user =CanvasUser::whereEmail($author->email)->first();
      if ($user) {
        array_push($this->winkAuthorMapping,[
          'user_id' => $user->id,
          'wink_author_id' => $author->id,
        ]);
        $this->comment("{$author->name} already processed");
      } else {
        $new_pw = Str::random(12);
        $this->warn("Creating new user: {$author->name}. This new user's password is \"${new_pw}\".");
        $user = CanvasUser::create([
          'id' => $author->id,
          'email' => $author->email,
          'name' => $author->name,
          'password' => Hash::make($new_pw),
          'username' => $author->slug,
          'summary' => $author->bio,
          'avatar' => $author->avatar,
          'dark_mode' => 1,
          'digest' => 0,
        ]);

        array_push($this->winkAuthorMapping,[
          'user_id' => $user->id,
          'wink_author_id' => $author->id,
        ]);
      }
    });
  }

  private function mapWinkTags() {
    $wink_tags = WinkTag::all();
    $wink_tags->each(function($tag) {
      $this->info("Processing Tag: {$tag->name}");
      CanvasTag::firstOrCreate([
        'id' => $tag->id,
        'slug' => $tag->slug,
        'name' => $tag->name,
        'user_id' => $this->winkAuthorMapping[0]['user_id'],
      ]);
    });
  }

  private function mapWinkPages() {
    $wink_pages = WinkPage::all();
    // TODO: If Canvas supports pages, add pages here.
    $this->comment("Or not.... this wasn't really done.");
  }
  
  private function mapWinkPosts() {
    $wink_posts = WinkPost::with('tags')->get();
    $wink_posts->each(function($post) {
      $author = collect($this->winkAuthorMapping)->firstWhere('wink_author_id',$post->author_id);
      $this->info("Processing Post: {$post->title}");
      if ( ! CanvasPost::where('id', $post->id)->first() ) {
        $this->comment("{$post->id} does not exist, so creating it.");
        CanvasPost::firstOrCreate([
          'id' => $post->id,
          'slug' => $post->slug,
          'title' => $post->title,
          'summary' => $post->excerpt,
          'body' => $post->body,
          'published_at' => $post->published ? $post->publish_date : null,
          'featured_image' => $post->featured_image,
          'featured_image_caption' => $post->featured_image_caption,
          'user_id' => $author['user_id'],
          'meta' => $post->meta,
        ]);
      } else {
        $this->comment("{$post->id} already exists in database.");
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
  }
}
