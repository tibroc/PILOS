<?php

namespace App\Http\Controllers\api\v1;

use App\Enums\CustomStatusCodes;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateRoom;
use App\Http\Requests\StartJoinMeeting;
use App\Http\Requests\UpdateRoomSettings;
use App\Http\Resources\RoomSettings;
use App\Models\Meeting;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\RoomService;
use Auth;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Room::class, 'room');
    }

    /**
     * Return a json array with all rooms the user owners or is member of
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $collection     = null;
        $additionalMeta = [];

        if ($request->has('filter')) {
            switch ($request->filter) {
                case 'own':
                    $collection                                = Auth::user()->myRooms()->with('owner');
                    $additionalMeta['meta']['total_no_filter'] = $collection->count();

                    break;
                case 'shared':
                    $collection                                =  Auth::user()->sharedRooms()->with('owner');
                    $additionalMeta['meta']['total_no_filter'] = $collection->count();

                    break;
                default:
                    abort(400);
            }

            if ($request->has('search') && trim($request->search) != '') {
                $collection = $collection->where('name', 'like', '%' . $request->search . '%');
            }

            $collection = $collection->orderBy('name')->paginate(setting('own_rooms_pagination_page_size'));

            return \App\Http\Resources\Room::collection($collection)->additional($additionalMeta);
        }

        $collection =  Room::with('owner');
        if (Auth::user()->cannot('viewAll', Room::class)) {
            $collection = $collection
                ->where('listed', 1)
                ->whereNull('accessCode')
                ->whereIn('room_type_id', RoomType::where('allow_listing', 1)->get('id'));
        }

        if ($request->has('search') && trim($request->search) != '') {
            $searchQueries  =  explode(' ', preg_replace('/\s\s+/', ' ', $request->search));
            foreach ($searchQueries as $searchQuery) {
                $collection = $collection->where(function ($query) use ($searchQuery) {
                    $query->where('name', 'like', '%' . $searchQuery . '%')
                            ->orWhereHas('owner', function ($query2) use ($searchQuery) {
                                $query2->where('firstname', 'like', '%' . $searchQuery . '%')
                                       ->orWhere('lastname', 'like', '%' . $searchQuery . '%');
                            });
                });
            }
        }

        if ($request->has('roomTypes')) {
            $collection->whereIn('room_type_id', $request->roomTypes);
        }

        $collection = $collection->orderBy('name')->paginate(setting('pagination_page_size'));

        return \App\Http\Resources\Room::collection($collection);
    }

    /**
     * Store a new created room
     *
     * @param  \Illuminate\Http\Request              $request
     * @return \App\Http\Resources\Room|JsonResponse
     */
    public function store(CreateRoom $request)
    {
        if (Auth::user()->room_limit !== -1 && Auth::user()->myRooms()->count() >= Auth::user()->room_limit) {
            abort(CustomStatusCodes::ROOM_LIMIT_EXCEEDED, __('app.errors.room_limit_exceeded'));
        }

        $room             = new Room();
        $room->name       = $request->name;
        $room->accessCode = rand(111111111, 999999999);
        $room->roomType()->associate($request->roomType);
        $room->owner()->associate(Auth::user());
        $room->save();

        return new \App\Http\Resources\Room($room, true);
    }

    /**
     * Return all general room details
     *
     * @param  Room                     $room
     * @return \App\Http\Resources\Room
     */
    public function show(Room $room, Request $request)
    {
        return new \App\Http\Resources\Room($room, $request->authenticated, true, $request->token);
    }

    /**
     * Return all room settings
     * @param  Room         $room
     * @return RoomSettings
     */
    public function getSettings(Room $room)
    {
        $this->authorize('viewSettings', $room);

        return new RoomSettings($room);
    }

    /**
     * Start a new meeting
     * @param  Room                   $room
     * @param  StartJoinMeeting       $request
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function start(Room $room, StartJoinMeeting $request)
    {
        $this->authorize('start', [$room, $request->get('token')]);

        $roomService = new RoomService($room);
        $url         = $roomService
            ->start($request->record_attendance)
            ->getJoinUrl($request);

        return response()->json(['url' => $url]);
    }

    /**
     * Join a running meeting
     * @param  Room             $room
     * @param  StartJoinMeeting $request
     * @return JsonResponse
     */
    public function join(Room $room, StartJoinMeeting $request)
    {
        $roomService = new RoomService($room);
        $url         = $roomService
            ->join($request->record_attendance)
            ->getJoinUrl($request);

        return response()->json(['url' => $url]);
    }

    /**
     * Update room settings
     * @param  UpdateRoomSettings $request
     * @param  Room               $room
     * @param  Room               $room
     * @return RoomSettings
     */
    public function update(UpdateRoomSettings $request, Room $room)
    {
        $room->name            = $request->name;
        $room->welcome         = $request->welcome;
        $room->maxParticipants = $request->maxParticipants;
        $room->duration        = $request->duration;
        $room->accessCode      = $request->accessCode;
        $room->listed          = $request->listed;

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

        $room->record_attendance              = $request->record_attendance;

        $room->defaultRole = $request->defaultRole;
        $room->lobby       = $request->lobby;
        $room->roomType()->associate($request->roomType);

        $room->save();

        return new RoomSettings($room);
    }

    /**
     * Delete a room and all related data
     *
     * @param  Room                      $room
     * @return \Illuminate\Http\Response
     */
    public function destroy(Room $room)
    {
        $room->delete();

        return response()->noContent();
    }

    /**
     * List of all meeting of the given room
     *
     * @param  Room                                                        $room
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     * @throws AuthorizationException
     */
    public function meetings(Room $room)
    {
        $this->authorize('viewStatistics', $room);

        return \App\Http\Resources\Meeting::collection($room->meetings()->orderByDesc('start')->whereNotNull('start')->paginate(setting('pagination_page_size')));
    }
}
