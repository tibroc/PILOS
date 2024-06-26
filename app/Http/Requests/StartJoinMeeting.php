<?php

namespace App\Http\Requests;

use App\Rules\ValidName;
use App\Services\RoomAuthService;
use Illuminate\Foundation\Http\FormRequest;

class StartJoinMeeting extends FormRequest
{
    protected RoomAuthService $roomAuthService;

    public function __construct(RoomAuthService $roomAuthService)
    {
        $this->roomAuthService = $roomAuthService;
    }
    
    public function rules()
    {
        return [
            // require name if not logged in or not using a room token
            'name'              => auth()->check() || $this->roomAuthService->getRoomToken($this->room) ? '' : ['required','min:2','max:50',  new ValidName() ],
            'record_attendance' => 'required|boolean',
        ];
    }
}
