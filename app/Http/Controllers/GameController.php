<?php

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Config;


class GameController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index()
    {
        return view('games/index', ['games' => Game::all()]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return View
     **/
    public function create()
    {
        $targetImagePath = 'ymd/images/' . \Str::random(40);
        $presignedRequest = $this->createPresignedPostRequest($targetImagePath);
        return view('games.create', ['presignedUrl' => $presignedRequest->getFormAttributes()['action'], 'presignedInputs' => $presignedRequest->getFormInputs()]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     *
     * @return RedirectResponse
     */
    public function store(Request $request)
    {
        $game = Game::create($request->all());
        $game->save();

        return redirect()->route('games.index');
    }

    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function promotion()
    {
        $games = Game::all()->where('release_date', '<', now())->sortByDesc('release_date')->take(5);
        return view('games/index', compact('games'));
    }

    /**
     * Display the specified resource.
     *
     * @param  Game  $game
     *
     * @return View
     */
    public function show(Request $request, Game $game)
    {
        if ($request->prefers(['text', 'image']) == 'image') {
            return redirect(Storage::disk('s3')->temporaryUrl($game->image_path, now()->addMinutes(2)));
        }
        return view('games/show', compact('game'));
    }

    /**
     * @param  Game  $game
     *
     * @return RedirectResponse
     */
    public function purchase(Game $game): RedirectResponse
    {
        if ($game->buyByUser(Auth::user())) {
            return redirect()->route('games.index');
        }
        return redirect()->back()->with(
            'error',
            'Achat impossible'
        );
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Game  $game
     *
     * @return Response
     */
    public function edit(Game $game)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  \App\Models\Game  $game
     *
     * @return Response
     */
    public function update(Request $request, Game $game)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Game  $game
     *
     * @return Response
     */
    public function destroy(Game $game)
    {
        //
    }

    /**
     * Create presigned URL.
     *
     * @param  \App\Models\Game  $game
     *
     * @return Response
     */
    public function createPresignedPostRequest($key, $expiration = "+2 hours")
    {
        $client = new \Aws\S3\S3Client([
            'version' => 'latest',
            'region' => env('AWS_DEFAULT_REGION'),
        ]);

        $bucket = config('filesystems.disks.s3.bucket');

        $formInputs = ['acl' => 'private', 'key' => $key];

        $options = [
            ['acl' => 'private'],
            ['bucket' => $bucket],
            ['eq', '$key', $key],
        ];


        $postObject = new \Aws\S3\PostObjectV4(
            $client,
            $bucket,
            $formInputs,
            $options,
            $expiration
        );

        return $postObject;
    }
}
