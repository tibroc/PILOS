<?php

namespace App\Http\Controllers;

use App\Room;
use App\RoomFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class FileController
 * Handle file management for rooms
 * @package App\Http\Controllers
 */
class FileController extends Controller
{
    /**
     * Display/Download a file of a room
     *
     * @param  Request          $request
     * @param  Room             $room
     * @param  RoomFile         $roomFile
     * @return StreamedResponse
     */
    public function show(Request $request, Room $room, RoomFile $roomFile)
    {
        if (!$roomFile->room->is($room)) {
            abort(404);
        }

        return $this->download($request, $roomFile);
    }

    /**
     * Display/Download a file
     *
     * @param  RoomFile         $roomFile
     * @return StreamedResponse
     */
    public function download(Request $request, RoomFile $roomFile)
    {
        // Download file/view in browser
        return Storage::download($roomFile->path, $roomFile->filename, [
            'Content-Disposition' => 'inline; filename="'. $roomFile->filename .'"'
        ]);
    }
}
