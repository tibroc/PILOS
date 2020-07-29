<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddRoomMember;
use App\Http\Requests\UpdateRoomSettings;
use App\Http\Resources\PrivateRoomFile;
use App\Http\Resources\RoomSettings;
use App\Http\Resources\RoomUser;
use App\Room;
use App\RoomFile;
use App\Server;
use App\User;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RoomController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Room::class, 'room');
        $this->middleware(['auth:api_users,api'])->except(['show','join','start']);
        $this->middleware('room.guest_protection',['only' => ['show','join','start']]);
        $this->middleware('room.authorize:true',['only' => ['show']]);
        $this->middleware('room.authorize',['only' => ['start','join']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        return response()->json([
                'data' => [
                    'myRooms'     => \App\Http\Resources\Room::collection(Auth::user()->myRooms()->with('owner')->get()),
                    'sharedRooms' => \App\Http\Resources\Room::collection(Auth::user()->sharedRooms()->with('owner')->get())
                ]
        ]);
    }

    /**
     * Store a new created room
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //TODO implement
    }

    /**
     * Return all general room details
     *
     * @param  Room $room
     * @return \App\Http\Resources\Room
     */
    public function show(Room $room, Request $request)
    {
        return new \App\Http\Resources\Room($room, $request->authorized);
    }

    /**
     * Return all room settings
     * @param Room $room
     * @return RoomSettings
     */
    public function getSettings(Room $room)
    {
        $this->authorize('viewSettings', $room);
        return new RoomSettings($room);
    }

    /**
     * Start a new meeting
     * @param Room $room
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function start(Room $room, Request $request)
    {
        $this->authorize('start', $room);

        $name = Auth::guest() ? $request->name : Auth::user()->firstname.' '.Auth::user()->lastname;
        $id   = Auth::guest() ? session()->getId() : Auth::user()->username;

        $meeting = $room->runningMeeting();
        if (!$meeting) {
            $servers = Server::where('status', true)->get();
            $server  = $servers->random();

            $meeting = $room->meetings()->create();
            $meeting->server()->associate($server);
            $meeting->start       = date('Y-m-d H:i:s');
            $meeting->attendeePW  = bin2hex(random_bytes(5));
            $meeting->moderatorPW = bin2hex(random_bytes(5));
            $meeting->save();

            if (!$meeting->start()) {
                // @TODO Error
            }
        }

        return response()->json(['url'=>$meeting->getJoinUrl($name, $room->getRole(Auth::user()), $id)]);
    }

    /**
     * Join an existing meeting
     * @param Room $room
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function join(Room $room, Request $request)
    {
        $name = Auth::guest() ? $request->name : Auth::user()->firstname.' '.Auth::user()->lastname;
        $id   = Auth::guest() ? session()->getId() : Auth::user()->username;

        $meeting = $room->runningMeeting();
        if ($meeting == null) {
            return response()->json('not_running', 460);
        }

        if (!$meeting->start()) {
            // @TODO Error
        }

        return response()->json(['url'=>$meeting->getJoinUrl($name, $room->getRole(Auth::user()), $id)]);
    }

    /**
     * Update room settings
     * @param UpdateRoomSettings $request
     * @param Room $room
     * @return RoomSettings
     */
    public function update(UpdateRoomSettings $request, Room $room)
    {
        $room->name            = $request->name;
        $room->welcome         = $request->welcome;
        $room->maxParticipants = $request->maxParticipants;
        $room->duration        = $request->duration;
        $room->accessCode      = $request->accessCode;

        $room->muteOnStart                    = $request->muteOnStart;
        $room->lockSettingsDisableCam         = $request->lockSettingsDisableCam;
        $room->webcamsOnlyForModerator        = $request->webcamsOnlyForModerator;
        $room->lockSettingsDisableMic         = $request->lockSettingsDisableMic;
        $room->lockSettingsDisablePrivateChat = $request->lockSettingsDisablePrivateChat;
        $room->lockSettingsDisablePublicChat  = $request->lockSettingsDisablePublicChat;
        $room->lockSettingsDisableNote        = $request->lockSettingsDisableNote;
        $room->lockSettingsLockOnJoin         = $request->lockSettingsLockOnJoin;
        $room->lockSettingsHideUserList       = $request->lockSettingsHideUserList;
        $room->everyoneCanStart               = $request->everyoneCanStart;
        $room->allowMembership                = $request->allowMembership;
        $room->allowGuests                    = $request->allowGuests;

        $room->defaultRole = $request->defaultRole;
        $room->lobby       = $request->lobby;
        $room->roomType()->associate($request->roomType);

        $room->save();

        return new RoomSettings($room);
    }

    /**
     * Delete a room and all related data
     *
     * @param Room $room
     * @return \Illuminate\Http\Response
     */
    public function destroy(Room $room)
    {
        //TODO implement; maybe keep some statistical data
    }
}
