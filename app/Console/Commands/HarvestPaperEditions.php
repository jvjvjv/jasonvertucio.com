<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Paper;

use Illuminate\Support\Facades\Http;

class HarvestPaperEditions extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'paper:harvest {edition_id?}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Command description';

  /**
   * Create a new command instance.
   *
   * @return void
   */
  public function __construct()
  {
    parent::__construct();
  }

  public function getEdition(String $id = null)
  {
    $url = 'http://paper.li/~api/papers/' . env('PAPER_ID');
    if ($id) {
      $url .= "?edition_id={$id}";
    }
    $this->info("Fetching {$url}");
    $response = Http::get($url);
    $paper = $response->json()['data'];
    try {
      try {
        $edition = Paper::create([
          'edition_id' => $paper['edition']['id'],
          'edition' => json_encode($paper['edition']),
          'published_at' => $paper['edition']['published_at']
        ]);
      } catch (\Illuminate\Database\QueryException $e) {
        $this->error($e->getMessage());
        $edition = Paper::where('edition_id', $paper['edition']['id']);
      }
      if ($paper['edition']['previous']) {
        $this->alert("Found previous edition {$paper['edition']['previous']}.");
        return $paper['edition']['previous'];
      } else {
        return null;
      }
    } catch (\ErrorException $e) {
      $this->error($e->getMessage());
      $this->info("I think we're done here.");
      return null;
    }
  }

  /**
   * Execute the console command.
   *
   * @return int
   */
  public function handle()
  {
    $edition_id = $this->argument('edition_id');
    $count = 0;
    do {
      $edition = Paper::where('edition_id',$edition_id)->first();
      if ($edition) {
        $this->info("Found ${edition_id} in db");
        $edition_id = json_decode($edition->edition,true)['previous'];
      } else {
        $edition_id = $this->getEdition($edition_id);
        $count++;
      }
    } while ($edition_id != null && $count < 90);
  }
}
