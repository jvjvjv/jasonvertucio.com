<?php

namespace App\Http\Controllers;

use App\Contracts\ResumeDataServiceContract;
use Illuminate\Http\Request;

use Canvas\Models\Post;

class HomeController extends Controller {

    public function __construct(protected ResumeDataServiceContract $resumeData) {}

    public function index(Request $request) {

        $path = resource_path() . "/config/config.json";
        $config = json_decode(file_get_contents($path), true);
        $latest_post = Post::published()->orderBy('published_at', 'DESC')->first();
        $resumeData = $this->resumeData->getDisplayData();

        return view('home', [
            'blog'       => $latest_post,
            'config'     => $config,
            'btc'        => null,
            'resumeData' => $resumeData,
        ]);
    }
}
