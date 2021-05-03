<?php

namespace Tests\Feature\api\v1\Room;

use App\Enums\CustomStatusCodes;
use App\Enums\RoomLobby;
use App\Enums\RoomUserRole;
use App\Enums\ServerStatus;
use App\Permission;
use App\Role;
use App\Room;
use App\RoomType;
use App\Server;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * General room api feature tests
 * @package Tests\Feature\api\v1\Room
 */
class RoomTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user, $role, $createPermission, $managePermission, $viewAllPermission;

    /**
     * Setup ressources for all tests
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create([
            'bbb_skip_check_audio' => true
        ]);

        $this->seed('RolesAndPermissionsSeeder');

        $this->role                 = factory(Role::class)->create();
        $this->createPermission     = Permission::where('name', 'rooms.create')->first();
        $this->managePermission     = Permission::where('name', 'rooms.manage')->first();
        $this->viewAllPermission    = Permission::where('name', 'rooms.viewAll')->first();
    }

    /**
     * Check if the permission inheritance is setup correct
     */
    public function testPermissionInheritances()
    {
        $this->user->roles()->attach($this->role);

        // Check without any permissions
        $this->assertFalse($this->user->can('rooms.create'));
        $this->assertFalse($this->user->can('rooms.viewAll'));
        $this->assertFalse($this->user->can('rooms.manage'));

        // Check with create rooms permission
        $this->role->permissions()->attach($this->createPermission);
        $this->assertTrue($this->user->can('rooms.create'));
        $this->assertFalse($this->user->can('rooms.viewAll'));
        $this->assertFalse($this->user->can('rooms.manage'));
        $this->role->permissions()->detach($this->createPermission);

        // Check with view all rooms permission
        $this->role->permissions()->attach($this->viewAllPermission);
        $this->assertFalse($this->user->can('rooms.create'));
        $this->assertTrue($this->user->can('rooms.viewAll'));
        $this->assertFalse($this->user->can('rooms.manage'));
        $this->role->permissions()->detach($this->viewAllPermission);

        // Check with manage rooms permission
        $this->role->permissions()->attach($this->managePermission);
        $this->assertTrue($this->user->can('rooms.create'));
        $this->assertTrue($this->user->can('rooms.viewAll'));
        $this->assertTrue($this->user->can('rooms.manage'));
        $this->role->permissions()->detach($this->managePermission);
    }

    /**
     * Test to create a new room with and without the required permissions
     */
    public function testCreateNewRoom()
    {
        setting(['room_limit' => '-1']);

        $room = ['roomType' => $this->faker->randomElement(RoomType::pluck('id')), 'name' => $this->faker->word];

        // Test unauthenticated user
        $this->postJson(route('api.v1.rooms.store'), $room)
            ->assertUnauthorized();

        // Test unauthorized user
        $this->actingAs($this->user)->postJson(route('api.v1.rooms.store'), $room)
            ->assertForbidden();

        // Authorize user
        $role       = factory(Role::class)->create();
        $role->permissions()->attach($this->createPermission);
        $this->user->roles()->attach($role);

        // Try again
        $this->actingAs($this->user)->postJson(route('api.v1.rooms.store'), $room)
            ->assertCreated();

        // -- Try different invalid requests --

        // empty name and invalid roomtype
        $room = ['roomType' => 0, 'name' => ''];
        $this->actingAs($this->user)->postJson(route('api.v1.rooms.store'), $room)
            ->assertJsonValidationErrors(['name', 'roomType']);

        // missing parameters
        $room = [];
        $this->actingAs($this->user)->postJson(route('api.v1.rooms.store'), $room)
            ->assertJsonValidationErrors(['name', 'roomType']);
    }

    /**
     * Check if the room limit is reached and the creation of new rooms is prevented
     */
    public function testCreateNewRoomReachLimit()
    {
        $role       = factory(Role::class)->create();
        $role->permissions()->attach($this->createPermission);
        $this->user->roles()->attach($role);
        setting(['room_limit' => '1']);

        $room_1 = ['roomType'=>$this->faker->randomElement(RoomType::pluck('id')),'name'=>$this->faker->word];
        $room_2 = ['roomType'=>$this->faker->randomElement(RoomType::pluck('id')),'name'=>$this->faker->word];

        // Create first room
        $this->actingAs($this->user)->postJson(route('api.v1.rooms.store'), $room_1)
            ->assertCreated();

        // Create second room, expect reach of limit
        $this->actingAs($this->user)->postJson(route('api.v1.rooms.store'), $room_2)
            ->assertStatus(CustomStatusCodes::ROOM_LIMIT_EXCEEDED);
    }

    /**
     * Test to delete a room
     */
    public function testDeleteRoom()
    {
        $room = factory(Room::class)->create();

        // Test unauthenticated user
        $this->deleteJson(route('api.v1.rooms.destroy', ['room'=> $room]))
            ->assertUnauthorized();

        // Test with normal user
        $this->actingAs($this->user)->deleteJson(route('api.v1.rooms.destroy', ['room'=> $room]))
            ->assertForbidden();

        // Test as member user
        $room->members()->sync([$this->user->id,['role'=>RoomUserRole::USER]]);
        $this->actingAs($this->user)->deleteJson(route('api.v1.rooms.destroy', ['room'=> $room]))
            ->assertForbidden();

        // Test as member moderator
        $room->members()->sync([$this->user->id,['role'=>RoomUserRole::MODERATOR]]);
        $this->actingAs($this->user)->deleteJson(route('api.v1.rooms.destroy', ['room'=> $room]))
            ->assertForbidden();

        // Test as member co-owner
        $room->members()->sync([$this->user->id,['role'=>RoomUserRole::CO_OWNER]]);
        $this->actingAs($this->user)->deleteJson(route('api.v1.rooms.destroy', ['room'=> $room]))
            ->assertForbidden();

        // Remove membership
        $room->members()->sync([]);

        // Test remove with view all rooms permission
        $this->user->roles()->attach($this->role);
        $this->role->permissions()->attach($this->viewAllPermission);
        $this->actingAs($this->user)->deleteJson(route('api.v1.rooms.destroy', ['room'=> $room]))
            ->assertForbidden();
        $this->role->permissions()->detach($this->viewAllPermission);

        // Test remove with manage rooms permission
        $this->role->permissions()->attach($this->managePermission);
        $this->actingAs($this->user)->deleteJson(route('api.v1.rooms.destroy', ['room'=> $room]))
            ->assertNoContent();
        $this->role->permissions()->detach($this->managePermission);

        // Recreate room
        $room = factory(Room::class)->create();

        // Add ownership
        $room->owner()->associate($this->user);
        $room->save();

        // Test with owner
        $this->actingAs($this->user)->deleteJson(route('api.v1.rooms.destroy', ['room'=> $room]))
            ->assertNoContent();

        // Try again after deleted
        $this->actingAs($this->user)->deleteJson(route('api.v1.rooms.destroy', ['room'=> $room]))
            ->assertNotFound();
    }

    /**
     * Test if guests can access room
     */
    public function testGuestAccess()
    {
        $room = factory(Room::class)->create([
            'allowGuests' => true
        ]);
        $this->getJson(route('api.v1.rooms.show', ['room'=>$room]))
            ->assertStatus(200);
    }

    /**
     * Test if guests are prevented from accessing room
     */
    public function testDisableGuestAccess()
    {
        $room = factory(Room::class)->create();
        $this->getJson(route('api.v1.rooms.show', ['room'=>$room]))
            ->assertStatus(403);
    }

    /**
     * Test how guests can log into room with or without valid access code
     */
    public function testAccessCodeGuests()
    {
        $room = factory(Room::class)->create([
            'allowGuests' => true,
            'accessCode'  => $this->faker->numberBetween(111111111, 999999999)
        ]);
        // Try without access code
        $this->getJson(route('api.v1.rooms.show', ['room'=>$room]))
            ->assertStatus(200)
            ->assertJsonFragment(['authenticated' => false]);

        // Try with empty access code
        $this->withHeaders(['Access-Code' => ''])->getJson(route('api.v1.rooms.show', ['room'=>$room]))
            ->assertUnauthorized();

        // Try with random access code
        $this->withHeaders(['Access-Code' => $this->faker->numberBetween(111111111, 999999999)])->getJson(route('api.v1.rooms.show', ['room'=>$room]))
            ->assertUnauthorized();

        // Try with correct access code
        $this->withHeaders(['Access-Code' => $room->accessCode])->getJson(route('api.v1.rooms.show', ['room'=>$room]))
            ->assertStatus(200)
            ->assertJsonFragment(['authenticated' => true]);
    }

    /**
     * Test how users can log into room with or without valid access code
     */
    public function testAccessCodeUser()
    {
        $room = factory(Room::class)->create([
            'allowGuests' => true,
            'accessCode'  => $this->faker->numberBetween(111111111, 999999999)
        ]);

        // Try without access code
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.show', ['room'=>$room]))
            ->assertStatus(200)
            ->assertJsonFragment(['authenticated' => false, 'allowMembership' => false]);

        // Try with empty access code
        $this->withHeaders(['Access-Code' => ''])->getJson(route('api.v1.rooms.show', ['room'=>$room]))
            ->assertUnauthorized();

        // Try with random access code
        $this->withHeaders(['Access-Code' => $this->faker->numberBetween(111111111, 999999999)])->getJson(route('api.v1.rooms.show', ['room'=>$room]))
            ->assertUnauthorized();

        // Try with correct access code
        $this->withHeaders(['Access-Code' => $room->accessCode])->getJson(route('api.v1.rooms.show', ['room'=>$room]))
            ->assertStatus(200)
            ->assertJsonFragment(['authenticated' => true]);

        // Try without access code but membership
        $this->flushHeaders();

        // Try with member user
        $room->members()->sync([$this->user->id,['role'=>RoomUserRole::USER]]);
        $this->getJson(route('api.v1.rooms.show', ['room'=>$room]))
            ->assertStatus(200)
            ->assertJsonFragment(['authenticated' => true]);

        // Try with member moderator
        $room->members()->sync([$this->user->id,['role'=>RoomUserRole::MODERATOR]]);
        $this->getJson(route('api.v1.rooms.show', ['room'=>$room]))
            ->assertStatus(200)
            ->assertJsonFragment(['authenticated' => true]);

        // Try with member co-owner
        $room->members()->sync([$this->user->id,['role'=>RoomUserRole::CO_OWNER]]);
        $this->getJson(route('api.v1.rooms.show', ['room'=>$room]))
            ->assertStatus(200)
            ->assertJsonFragment(['authenticated' => true]);

        $room->members()->sync([]);

        // Try with view all rooms permission
        $this->user->roles()->attach($this->role);
        $this->role->permissions()->attach($this->viewAllPermission);
        $this->getJson(route('api.v1.rooms.show', ['room'=>$room]))
            ->assertStatus(200)
            ->assertJsonFragment(['authenticated' => true]);
        $this->role->permissions()->detach($this->viewAllPermission);

        // Try as owner
        $room->owner()->associate($this->user);
        $room->save();
        $room->members()->sync([$this->user->id,['role'=>RoomUserRole::CO_OWNER]]);
        $this->getJson(route('api.v1.rooms.show', ['room'=>$room]))
            ->assertStatus(200)
            ->assertJsonFragment(['authenticated' => true]);
    }

    /**
     * Test list of rooms without filter
     */
    public function testRoomList()
    {
        setting(['pagination_page_size' => 10]);
        $user      = factory(User::class)->create(['firstname'=>'John','lastname'=>'Doe']);
        $roomType1 = factory(RoomType::class)->create();
        $roomType2 = factory(RoomType::class)->create(['allow_listing'=>false]);
        $roomType3 = factory(RoomType::class)->create(['allow_listing'=>true]);

        $room1 = factory(Room::class)->create(['name'=>'test a','user_id'=>$user->id,'room_type_id'=>$roomType1->id,'listed'=>false,'accessCode'=>123456789]);
        $room2 = factory(Room::class)->create(['name'=>'test b','user_id'=>$user->id,'room_type_id'=>$roomType1->id,'listed'=>false,'accessCode'=>null]);
        $room3 = factory(Room::class)->create(['name'=>'room a','user_id'=>$user->id,'room_type_id'=>$roomType2->id,'listed'=>true,'accessCode'=>123456789]);
        $room4 = factory(Room::class)->create(['name'=>'room b','user_id'=>$user->id,'room_type_id'=>$roomType2->id,'listed'=>true,'accessCode'=>null]);
        $room5 = factory(Room::class)->create(['name'=>'room b','user_id'=>$user->id,'room_type_id'=>$roomType3->id,'listed'=>true,'accessCode'=>null]);

        // Testing guests access
        $this->getJson(route('api.v1.rooms.index'))
            ->assertUnauthorized();

        // Test as logged in user, without viewAll rooms permission, should only see rooms
        // that are listing and have a room type that allows listing
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.index'))
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id'=>$room5->id,'name'=>$room5->name])
            ->assertJsonCount(7, 'meta');

        // Test with viewAll rooms permission
        $role       = factory(Role::class)->create();
        $role->permissions()->attach($this->viewAllPermission);
        $this->user->roles()->attach($role);
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.index'))
            ->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonFragment(['id'=>$room1->id,'name'=>$room1->name])
            ->assertJsonFragment(['id'=>$room2->id,'name'=>$room2->name])
            ->assertJsonFragment(['id'=>$room3->id,'name'=>$room3->name])
            ->assertJsonFragment(['id'=>$room4->id,'name'=>$room4->name])
            ->assertJsonCount(7, 'meta');

        // Find by room name
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.index').'?search=test')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonCount(7, 'meta');

        $this->actingAs($this->user)->getJson(route('api.v1.rooms.index').'?search=test+a')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.index').'?search=test+a+xyz')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // Find by owner name
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.index').'?search=john')
            ->assertStatus(200)
            ->assertJsonCount(5, 'data');
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.index').'?search=john+d')
            ->assertStatus(200)
            ->assertJsonCount(5, 'data');
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.index').'?search=john+d+xzy')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // Find by owner name and room name
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.index').'?search=test+john')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.index').'?search=test+john+xyz')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // Filter by room types
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.index').'?roomTypes[]='.$roomType1->id)
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonCount(7, 'meta');

        $this->actingAs($this->user)->getJson(route('api.v1.rooms.index').'?roomTypes[]='.$roomType1->id.'&roomTypes[]='.$roomType2->id)
            ->assertStatus(200)
            ->assertJsonCount(4, 'data');
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.index').'?roomTypes[]=0')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // Filter by room types and search
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.index').'?roomTypes[]='.$roomType1->id.'&search=test+a')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    /**
     * Test list of rooms with filter
     */
    public function testRoomListWithFilter()
    {
        $rooms = factory(Room::class, 4)->create();

        // Testing guests access
        $this->getJson(route('api.v1.rooms.index').'?filter=own')
            ->assertUnauthorized();

        // Invalid filter
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.index').'?filter=123')
            ->assertStatus(400);

        // Testing ownership and membership
        $rooms[0]->members()->attach($this->user, ['role'=>RoomUserRole::USER]);
        $rooms[1]->members()->attach($this->user, ['role'=>RoomUserRole::USER]);
        $rooms[2]->owner()->associate($this->user);
        $rooms[2]->save();
        $rooms[3]->owner()->associate($this->user);
        $rooms[3]->save();

        // Testing working filter
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.index').'?filter=own')
            ->assertOk()
            ->assertJsonFragment(['id'=>$rooms[2]->id])
            ->assertJsonFragment(['id'=>$rooms[3]->id])
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.total_no_filter', 2)
            ->assertJsonCount(8, 'meta');

        $this->actingAs($this->user)->getJson(route('api.v1.rooms.index').'?filter=shared')
            ->assertOk()
            ->assertJsonFragment(['id'=>$rooms[0]->id])
            ->assertJsonFragment(['id'=>$rooms[1]->id])
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.total_no_filter', 2);
    }

    /**
     * Test search for rooms
     */
    public function testRoomSearch()
    {
        $room = factory(Room::class)->create(['name'=>'Meeting One']);

        // Testing ownership and membership
        $room->owner()->associate($this->user);
        $room->save();

        // Testing without query
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.index').'?filter=own')
            ->assertOk()
            ->assertJsonFragment(['id'=>$room->id])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.total_no_filter', 1);

        // Testing with empty query
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.index').'?filter=own&search=')
            ->assertOk()
            ->assertJsonFragment(['id'=>$room->id])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.total_no_filter', 1);

        // Testing with fragment of the room name
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.index').'?filter=own&search=One')
            ->assertOk()
            ->assertJsonFragment(['id'=>$room->id])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.total_no_filter', 1);

        // Testing with full name
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.index').'?filter=own&search=Meeting One')
            ->assertOk()
            ->assertJsonFragment(['id'=>$room->id])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.total_no_filter', 1);

        // Testing with invalid name
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.index').'?filter=own&search=Meeting Two')
            ->assertOk()
            ->assertJsonMissing(['id'=>$room->id])
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.total_no_filter', 1);
    }

    /**
     * Test callback route for meetings
     */
    public function testEndMeetingCallback()
    {
        $room = factory(Room::class)->create();

        $server               = new Server();
        $server->base_url     = $this->faker->url;
        $server->salt         = $this->faker->sha1;
        $server->status       = ServerStatus::ONLINE;
        $server->name         = $this->faker->word;
        $server->save();

        $meeting = $room->meetings()->create();
        $meeting->server()->associate($server);
        $meeting->start       = date('Y-m-d H:i:s');
        $meeting->attendeePW  = bin2hex(random_bytes(5));
        $meeting->moderatorPW = bin2hex(random_bytes(5));
        $meeting->save();

        self::assertNull($meeting->end);

        $url = route('api.v1.meetings.endcallback', ['meeting'=>$meeting,'salt'=>$meeting->getCallbackSalt(true)]);

        // check with invalid salt
        $this->getJson($url.'test')
            ->assertUnauthorized();

        $this->getJson($url)
            ->assertSuccessful();

        // Check if timestamp was set
        $meeting->refresh();
        self::assertNotNull($meeting->end);
        $end = $meeting->end;

        // Check if second call doesn't change timestamp
        $url = route('api.v1.meetings.endcallback', ['meeting'=>$meeting,'salt'=>$meeting->getCallbackSalt(true)]);
        $this->getJson($url)
            ->assertSuccessful();

        $meeting->refresh();
        self::assertEquals($meeting->end, $end);
    }

    public function testSettingsAccess()
    {
        $room = factory(Room::class)->create();

        // Testing guests access
        $this->getJson(route('api.v1.rooms.settings', ['room'=>$room]))
            ->assertUnauthorized();

        // Testing access any user
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.settings', ['room'=>$room]))
            ->assertForbidden();

        // Testing member
        $room->members()->attach($this->user, ['role'=>RoomUserRole::USER]);
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.settings', ['room'=>$room]))
            ->assertForbidden();

        // Testing member as moderator
        $room->members()->sync([$this->user->id,['role'=>RoomUserRole::MODERATOR]]);
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.settings', ['room'=>$room]))
            ->assertForbidden();

        // Testing member as co-owner
        $room->members()->sync([$this->user->id,['role'=>RoomUserRole::CO_OWNER]]);
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.settings', ['room'=>$room]))
            ->assertSuccessful();

        // Reset room membership
        $room->members()->sync([]);

        // Try with view all rooms permission
        $this->user->roles()->attach($this->role);
        $this->role->permissions()->attach($this->viewAllPermission);
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.settings', ['room'=>$room]))
            ->assertSuccessful();
        $this->role->permissions()->detach($this->viewAllPermission);

        // Testing access owner
        $this->actingAs($room->owner)->getJson(route('api.v1.rooms.settings', ['room'=>$room]))
            ->assertSuccessful();
    }

    public function testUpdateSettings()
    {
        $room = factory(Room::class)->create();
        // Get current settings
        $response = $this->actingAs($room->owner)->getJson(route('api.v1.rooms.settings', ['room'=>$room]))
            ->assertSuccessful();

        $settings = $response->json('data');

        $roomType = $this->faker->randomElement(RoomType::all());

        $settings['accessCode']                     = $this->faker->numberBetween(111111111, 999999999);
        $settings['allowGuests']                    = $this->faker->boolean;
        $settings['allowMembership']                = $this->faker->boolean;
        $settings['defaultRole']                    = $this->faker->randomElement([RoomUserRole::MODERATOR,RoomUserRole::USER]);
        $settings['duration']                       = $this->faker->numberBetween(1, 50);
        $settings['everyoneCanStart']               = true;
        $settings['lobby']                          = $this->faker->randomElement(RoomLobby::getValues());
        $settings['lockSettingsDisableCam']         = $this->faker->boolean;
        $settings['lockSettingsDisableMic']         = $this->faker->boolean;
        $settings['lockSettingsDisableNote']        = $this->faker->boolean;
        $settings['lockSettingsDisablePrivateChat'] = $this->faker->boolean;
        $settings['lockSettingsDisablePublicChat']  = $this->faker->boolean;
        $settings['lockSettingsHideUserList']       = $this->faker->boolean;
        $settings['lockSettingsLockOnJoin']         = $this->faker->boolean;
        $settings['maxParticipants']                = $this->faker->numberBetween(1, 50);
        $settings['muteOnStart']                    = $this->faker->boolean;
        $settings['name']                           = $this->faker->word;
        $settings['roomType']                       = $roomType->id;
        $settings['webcamsOnlyForModerator']        = $this->faker->boolean;

        // Testing user
        $this->actingAs($this->user)->putJson(route('api.v1.rooms.update', ['room'=>$room]), $settings)
            ->assertForbidden();

        // Testing member as user
        $room->members()->sync([$this->user->id,['role'=>RoomUserRole::USER]]);
        $this->actingAs($this->user)->putJson(route('api.v1.rooms.update', ['room'=>$room]), $settings)
            ->assertForbidden();

        // Testing member as moderator
        $room->members()->sync([$this->user->id,['role'=>RoomUserRole::MODERATOR]]);
        $this->actingAs($this->user)->putJson(route('api.v1.rooms.update', ['room'=>$room]), $settings)
            ->assertForbidden();

        // Testing member as co-owner
        $room->members()->sync([$this->user->id,['role'=>RoomUserRole::CO_OWNER]]);
        $this->actingAs($this->user)->putJson(route('api.v1.rooms.update', ['room'=>$room]), $settings)
            ->assertSuccessful();

        // Reset room membership
        $room->members()->sync([]);

        // Try with view all rooms permission
        $this->user->roles()->attach($this->role);
        $this->role->permissions()->attach($this->viewAllPermission);
        $this->actingAs($this->user)->putJson(route('api.v1.rooms.update', ['room'=>$room]), $settings)
            ->assertForbidden();
        $this->role->permissions()->detach($this->viewAllPermission);

        // Try with view all rooms permission
        $this->user->roles()->attach($this->role);
        $this->role->permissions()->attach($this->managePermission);
        $this->actingAs($this->user)->putJson(route('api.v1.rooms.update', ['room'=>$room]), $settings)
            ->assertSuccessful();
        $this->role->permissions()->detach($this->managePermission);

        // Test as owner
        $this->actingAs($room->owner)->putJson(route('api.v1.rooms.update', ['room'=>$room]), $settings)
            ->assertSuccessful();

        // Get new settings
        $response = $this->actingAs($room->owner)->getJson(route('api.v1.rooms.settings', ['room'=>$room]))
            ->assertSuccessful();

        $new_settings         = $response->json('data');
        $settings['roomType'] = new \App\Http\Resources\RoomType($roomType);

        $this->assertJsonStringEqualsJsonString(json_encode($new_settings), json_encode($settings));
    }

    public function testUpdateSettingsInvalid()
    {
        $room = factory(Room::class)->create();
        // Get current settings
        $response = $this->actingAs($room->owner)->getJson(route('api.v1.rooms.settings', ['room'=>$room]))
            ->assertSuccessful();

        $settings = $response->json('data');

        $settings['accessCode']      = $this->faker->numberBetween(1111111, 9999999);
        $settings['defaultRole']     = RoomUserRole::GUEST;
        $settings['duration']        = -10;
        $settings['lobby']           = 5;
        $settings['maxParticipants'] = -10;
        $settings['name']            = null;
        $settings['roomType']        = 0;

        $this->putJson(route('api.v1.rooms.update', ['room'=>$room]), $settings)
            ->assertJsonValidationErrors(['accessCode','defaultRole','duration','lobby','maxParticipants','name','roomType']);
    }

    /**
     * Testing to start room but no server available
     */
    public function testStartOnlyModeratorsNoServer()
    {
        $room = factory(Room::class)->create([
            'accessCode'  => $this->faker->numberBetween(111111111, 999999999)
        ]);

        // Testing guests
        $this->getJson(route('api.v1.rooms.start', ['room'=>$room]))
            ->assertForbidden();
        $this->getJson(route('api.v1.rooms.start', ['room'=>$room,'code'=>$room->accessCode]))
            ->assertForbidden();

        // Testing authorized users
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.start', ['room'=>$room]))
            ->assertForbidden();
        $this->getJson(route('api.v1.rooms.start', ['room'=>$room,'code'=>$room->accessCode]))
            ->assertForbidden();

        // Testing member
        $room->members()->attach($this->user, ['role'=>RoomUserRole::USER]);
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.start', ['room'=>$room]))
            ->assertForbidden();

        // Testing member as moderator
        $room->members()->sync([$this->user->id,['role'=>RoomUserRole::MODERATOR]]);
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.start', ['room'=>$room]))
            ->assertStatus(CustomStatusCodes::NO_SERVER_AVAILABLE);

        // Testing member as co-owner
        $room->members()->sync([$this->user->id,['role'=>RoomUserRole::CO_OWNER]]);
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.start', ['room'=>$room]))
            ->assertStatus(CustomStatusCodes::NO_SERVER_AVAILABLE);

        // Reset room membership
        $room->members()->sync([]);

        // Try with view all rooms permission
        $this->user->roles()->attach($this->role);
        $this->role->permissions()->attach($this->viewAllPermission);
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.start', ['room'=>$room]))
            ->assertForbidden();
        $this->role->permissions()->detach($this->viewAllPermission);

        // Try with manage all rooms permission
        $this->user->roles()->attach($this->role);
        $this->role->permissions()->attach($this->managePermission);
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.start', ['room'=>$room]))
            ->assertStatus(CustomStatusCodes::NO_SERVER_AVAILABLE);
        $this->role->permissions()->detach($this->managePermission);

        // Testing owner
        $this->actingAs($room->owner)->getJson(route('api.v1.rooms.start', ['room'=>$room]))
            ->assertStatus(CustomStatusCodes::NO_SERVER_AVAILABLE);
    }

    /**
     * Testing to start room with guests allowed, and everyone can start but no server available
     */
    public function testStartNoServer()
    {
        $room = factory(Room::class)->create([
            'allowGuests'      => true,
            'everyoneCanStart' => true,
            'accessCode'       => $this->faker->numberBetween(111111111, 999999999)
        ]);

        // Testing guests
        $this->getJson(route('api.v1.rooms.start', ['room'=>$room]))
            ->assertForbidden();
        $this->withHeaders(['Access-Code' => $this->faker->numberBetween(111111111, 999999999)])->getJson(route('api.v1.rooms.start', ['room'=>$room]))
            ->assertUnauthorized();
        $this->withHeaders(['Access-Code' => $room->accessCode])->getJson(route('api.v1.rooms.start', ['room'=>$room]))
            ->assertJsonValidationErrors('name');
        $this->withHeaders(['Access-Code' => $room->accessCode])->getJson(route('api.v1.rooms.start', ['room'=>$room,'name'=>'<script>alert("HI");</script>']))
            ->assertJsonValidationErrors('name');
        $this->withHeaders(['Access-Code' => $room->accessCode])->getJson(route('api.v1.rooms.start', ['room'=>$room,'name'=>$this->faker->name]))
            ->assertStatus(CustomStatusCodes::NO_SERVER_AVAILABLE);

        $this->flushHeaders();

        // Testing authorized users
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.start', ['room'=>$room]))
            ->assertForbidden();
        $this->withHeaders(['Access-Code' => $this->faker->numberBetween(111111111, 999999999)])->getJson(route('api.v1.rooms.start', ['room'=>$room]))
            ->assertUnauthorized();
        $this->withHeaders(['Access-Code' => $room->accessCode])->getJson(route('api.v1.rooms.start', ['room'=>$room]))
            ->assertStatus(CustomStatusCodes::NO_SERVER_AVAILABLE);

        $this->flushHeaders();

        // Testing owner
        $this->actingAs($room->owner)->getJson(route('api.v1.rooms.start', ['room'=>$room]))
            ->assertStatus(CustomStatusCodes::NO_SERVER_AVAILABLE);

        // Testing member
        $room->members()->attach($this->user, ['role'=>RoomUserRole::USER]);
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.start', ['room'=>$room]))
            ->assertStatus(CustomStatusCodes::NO_SERVER_AVAILABLE);
    }

    public function testStartAndJoinWithWrongServerDetails()
    {
        $room = factory(Room::class)->create();

        // Adding fake server(s)
        $server =  factory(Server::class)->create();
        $room->roomType->serverPool->servers()->sync([$server->id]);

        // Create meeting
        $this->actingAs($room->owner)->getJson(route('api.v1.rooms.start', ['room' => $room]))
            ->assertStatus(CustomStatusCodes::ROOM_START_FAILED);

        $server->refresh();
        $this->assertEquals(ServerStatus::OFFLINE, $server->status);

        // Create meeting
        $this->actingAs($room->owner)->getJson(route('api.v1.rooms.join', ['room' => $room]))
            ->assertStatus(CustomStatusCodes::MEETING_NOT_RUNNING);
    }

    /**
     * Tests starting new meeting with a running bbb server
     */
    public function testStartWithServer()
    {
        $room = factory(Room::class)->create();
        $room->owner->update(['bbb_skip_check_audio' => true]);

        // Adding server(s)
        $this->seed('ServerSeeder');

        // Create meeting
        $response = $this->actingAs($room->owner)->getJson(route('api.v1.rooms.start', ['room'=>$room]))
            ->assertSuccessful();
        $this->assertIsString($response->json('url'));
        $queryParams = [];
        parse_str(parse_url($response->json('url'))['query'], $queryParams);
        $this->assertEquals('true', $queryParams['userdata-bbb_skip_check_audio']);

        // Try to start bbb meeting
        $response = Http::withOptions(['allow_redirects' => false])->get($response->json('url'));
        $this->assertEquals(302, $response->status());
        $this->assertArrayHasKey('Location', $response->headers());

        // Clear
        $room->runningMeeting()->endMeeting();

        // Check with wrong salt/secret
        foreach (Server::all() as $server) {
            $server->salt = 'TEST';
            $server->save();
        }
        $room2 = factory(Room::class)->create(['room_type_id'=>$room->roomType->id]);
        $this->actingAs($room2->owner)->getJson(route('api.v1.rooms.start', ['room'=>$room2]))
            ->assertStatus(CustomStatusCodes::ROOM_START_FAILED);
    }

    /**
     * Test joining a meeting with a running bbb server
     */
    public function testJoin()
    {
        $room = factory(Room::class)->create([
            'allowGuests' => true,
            'accessCode'  => $this->faker->numberBetween(111111111, 999999999)
        ]);
        $room->owner->update(['bbb_skip_check_audio' => true]);

        // Adding server(s)
        $this->seed('ServerSeeder');

        // Testing join with meeting not running
        $this->actingAs($room->owner)->getJson(route('api.v1.rooms.join', ['room'=>$room]))
            ->assertStatus(CustomStatusCodes::MEETING_NOT_RUNNING);

        // Test to join a meeting marked in the db as running, but isn't running on the server
        $meeting              = $room->meetings()->create();
        $meeting->start       = date('Y-m-d H:i:s');
        $meeting->attendeePW  = bin2hex(random_bytes(5));
        $meeting->moderatorPW = bin2hex(random_bytes(5));
        $meeting->server()->associate(Server::where('status', ServerStatus::ONLINE)->get()->random());
        $meeting->save();
        $this->actingAs($room->owner)->getJson(route('api.v1.rooms.join', ['room'=>$room]))
            ->assertStatus(CustomStatusCodes::MEETING_NOT_RUNNING);
        $meeting->refresh();
        $this->assertNotNull($meeting->end);

        // Start meeting
        $response = $this->actingAs($room->owner)->getJson(route('api.v1.rooms.start', ['room'=>$room]))
            ->assertSuccessful();
        $this->assertIsString($response->json('url'));
        $queryParams = [];
        parse_str(parse_url($response->json('url'))['query'], $queryParams);
        $this->assertEquals('true', $queryParams['userdata-bbb_skip_check_audio']);

        // Try to start bbb meeting
        $response = Http::withOptions(['allow_redirects' => false])->get($response->json('url'));
        $this->assertEquals(302, $response->status());
        $this->assertArrayHasKey('Location', $response->headers());

        \Auth::logout();

        // Check if room is running
        $this->getJson(route('api.v1.rooms.show', ['room'=>$room]))
            ->assertSuccessful()
            ->assertJsonFragment(['running' => true]);

        // Join as guest, without required access code
        $this->getJson(route('api.v1.rooms.join', ['room'=>$room]))
            ->assertForbidden();

        // Join as guest without name
        $this->withHeaders(['Access-Code' => $room->accessCode])->getJson(route('api.v1.rooms.join', ['room'=>$room]))
            ->assertJsonValidationErrors('name');

        // Join as guest with invalid/dangerous name
        $this->withHeaders(['Access-Code' => $room->accessCode])->getJson(route('api.v1.rooms.join', ['room'=>$room,'name'=>'<script>alert("HI");</script>']))
            ->assertJsonValidationErrors('name');

        // Join as guest
        $response = $this->withHeaders(['Access-Code' => $room->accessCode])->getJson(route('api.v1.rooms.join', ['room'=>$room,'name'=>$this->faker->name]))
            ->assertSuccessful();
        $queryParams = [];
        parse_str(parse_url($response->json('url'))['query'], $queryParams);
        $this->assertEquals('false', $queryParams['userdata-bbb_skip_check_audio']);

        $this->flushHeaders();

        // Join as authorized users
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.join', ['room'=>$room]))
            ->assertForbidden();
        $this->withHeaders(['Access-Code' => $this->faker->numberBetween(111111111, 999999999)])->getJson(route('api.v1.rooms.join', ['room'=>$room]))
            ->assertUnauthorized();
        $this->withHeaders(['Access-Code' => $room->accessCode])->getJson(route('api.v1.rooms.join', ['room'=>$room]))
            ->assertSuccessful();

        $this->flushHeaders();

        // Testing owner
        $this->actingAs($room->owner)->getJson(route('api.v1.rooms.join', ['room'=>$room]))
            ->assertSuccessful();

        // Testing member
        $room->members()->attach($this->user, ['role'=>RoomUserRole::USER]);
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.join', ['room'=>$room]))
            ->assertSuccessful();

        $runningMeeting = $room->runningMeeting();
        // Clear
        $this->assertNull($runningMeeting->end);
        $runningMeeting->endMeeting();
        $runningMeeting->refresh();
        $this->assertNotNull($runningMeeting->end);
    }

    /**
     * Test joining urls contains correct role and name
     */
    public function testJoinUrl()
    {
        $room = factory(Room::class)->create([
            'allowGuests' => true
        ]);

        // Adding server(s)
        $this->seed('ServerSeeder');

        // Start meeting
        $response = $this->actingAs($room->owner)->getJson(route('api.v1.rooms.start', ['room'=>$room]))
            ->assertSuccessful();
        $runningMeeting = $room->runningMeeting();
        $attendeePW     = $runningMeeting->attendeePW;
        $moderatorPW    = $runningMeeting->moderatorPW;

        \Auth::logout();

        // Join as guest
        $guestName = $this->faker->name;
        $response  = $this->getJson(route('api.v1.rooms.join', ['room'=>$room,'name'=>$guestName]))
            ->assertSuccessful();
        $queryParams = [];
        parse_str(parse_url($response->json('url'))['query'], $queryParams);
        $this->assertEquals($attendeePW, $queryParams['password']);
        $this->assertEquals('true', $queryParams['guest']);
        $this->assertEquals($guestName, $queryParams['fullName']);

        // Join as authorized users
        $response = $this->actingAs($this->user)->getJson(route('api.v1.rooms.join', ['room'=>$room]))
            ->assertSuccessful();
        $queryParams = [];
        parse_str(parse_url($response->json('url'))['query'], $queryParams);
        $this->assertEquals($attendeePW, $queryParams['password']);
        $this->assertEquals($this->user->fullname, $queryParams['fullName']);

        // Testing owner
        $response = $this->actingAs($room->owner)->getJson(route('api.v1.rooms.join', ['room'=>$room]))
            ->assertSuccessful();
        $queryParams = [];
        parse_str(parse_url($response->json('url'))['query'], $queryParams);
        $this->assertEquals($moderatorPW, $queryParams['password']);

        // Testing member user
        $room->members()->sync([$this->user->id => ['role'=>RoomUserRole::USER]]);
        $response = $this->actingAs($this->user)->getJson(route('api.v1.rooms.join', ['room'=>$room]))
            ->assertSuccessful();
        $queryParams = [];
        parse_str(parse_url($response->json('url'))['query'], $queryParams);
        $this->assertEquals($attendeePW, $queryParams['password']);

        // Testing member moderator
        $room->members()->sync([$this->user->id => ['role'=>RoomUserRole::MODERATOR]]);
        $response = $this->actingAs($this->user)->getJson(route('api.v1.rooms.join', ['room'=>$room]))
            ->assertSuccessful();
        $queryParams = [];
        parse_str(parse_url($response->json('url'))['query'], $queryParams);
        $this->assertEquals($moderatorPW, $queryParams['password']);

        // Testing member co-owner
        $room->members()->sync([$this->user->id => ['role'=>RoomUserRole::CO_OWNER]]);
        $response = $this->actingAs($this->user)->getJson(route('api.v1.rooms.join', ['room'=>$room]))
            ->assertSuccessful();
        $queryParams = [];
        parse_str(parse_url($response->json('url'))['query'], $queryParams);
        $this->assertEquals($moderatorPW, $queryParams['password']);

        // Reset room membership
        $room->members()->sync([]);

        // Testing with view all rooms permission
        $this->user->roles()->attach($this->role);
        $this->role->permissions()->attach($this->viewAllPermission);
        $response = $this->actingAs($this->user)->getJson(route('api.v1.rooms.join', ['room'=>$room]))
            ->assertSuccessful();
        $queryParams = [];
        parse_str(parse_url($response->json('url'))['query'], $queryParams);
        $this->assertEquals($attendeePW, $queryParams['password']);
        $this->role->permissions()->detach($this->viewAllPermission);

        // Testing with manage rooms permission
        $this->user->roles()->attach($this->role);
        $this->role->permissions()->attach($this->managePermission);
        $response = $this->actingAs($this->user)->getJson(route('api.v1.rooms.join', ['room'=>$room]))
            ->assertSuccessful();
        $queryParams = [];
        parse_str(parse_url($response->json('url'))['query'], $queryParams);
        $this->assertEquals($moderatorPW, $queryParams['password']);
        $this->role->permissions()->detach($this->managePermission);

        // Clear
        $this->assertNull($runningMeeting->end);
        $runningMeeting->endMeeting();
        $runningMeeting->refresh();
        $this->assertNotNull($runningMeeting->end);
    }

    /**
     * Test lobby behavior if enabled for everyone
     */
    public function testLobbyEnabled()
    {
        $room = factory(Room::class)->create([
            'allowGuests' => true,
            'lobby'       => RoomLobby::ENABLED,
        ]);

        // Adding server(s)
        $this->seed('ServerSeeder');

        // Start meeting
        $this->actingAs($room->owner)->getJson(route('api.v1.rooms.start', ['room'=>$room]))
            ->assertSuccessful();

        // Check guests
        $this->assertTrue($this->checkGuestWaitPage($room));

        // Check auth. users
        $this->assertTrue($this->checkGuestWaitPage($room, $this->user));

        // Check owner
        $this->assertFalse($this->checkGuestWaitPage($room, $room->owner));

        // Testing member
        $room->members()->attach($this->user, ['role'=>RoomUserRole::USER]);
        $this->assertTrue($this->checkGuestWaitPage($room, $this->user));

        // Testing moderator member
        $room->members()->sync([$this->user->id,['role'=>RoomUserRole::MODERATOR]]);
        $this->assertFalse($this->checkGuestWaitPage($room, $this->user));

        // Testing co-owner member
        $room->members()->sync([$this->user->id,['role'=>RoomUserRole::CO_OWNER]]);
        $this->assertFalse($this->checkGuestWaitPage($room, $this->user));

        // Clear
        $room->runningMeeting()->endMeeting();
    }

    /**
     * Test lobby behavior if enabled only for guests
     */
    public function testLobbyOnlyGuests()
    {
        $room = factory(Room::class)->create([
            'allowGuests' => true,
            'lobby'       => RoomLobby::ONLY_GUEST,
        ]);
        // Adding server(s)
        $this->seed('ServerSeeder');

        // Start meeting
        $this->actingAs($room->owner)->getJson(route('api.v1.rooms.start', ['room'=>$room]))
            ->assertSuccessful();

        // Check guests
        $this->assertTrue($this->checkGuestWaitPage($room));

        // Check auth. users
        $this->assertFalse($this->checkGuestWaitPage($room, $this->user));

        // Check owner
        $this->assertFalse($this->checkGuestWaitPage($room, $room->owner));

        // Testing member
        $room->members()->attach($this->user, ['role'=>RoomUserRole::USER]);
        $this->assertFalse($this->checkGuestWaitPage($room, $this->user));

        // Testing moderator member
        $room->members()->sync([$this->user->id,['role'=>RoomUserRole::MODERATOR]]);
        $this->assertFalse($this->checkGuestWaitPage($room, $this->user));

        // Testing co-owner member
        $room->members()->sync([$this->user->id,['role'=>RoomUserRole::CO_OWNER]]);
        $this->assertFalse($this->checkGuestWaitPage($room, $this->user));

        // Clear
        $room->runningMeeting()->endMeeting();
    }

    /**
     * Check if user or guest enters room or lobby
     * @param $room Room room
     * @param  null $user user or guest
     * @return bool is entering lobby
     */
    protected function checkGuestWaitPage($room, $user = null)
    {
        // logout from previous calls
        \Auth::logout();
        // login as user is not guest
        $request = $user == null ? $this : $this->actingAs($user);
        // join meeting
        $response = $request->getJson(route('api.v1.rooms.join', ['room'=>$room,'code'=>$room->accessCode,'name'=>$this->faker->name]))
            ->assertSuccessful();
        // check if response has a join url
        $this->assertIsString($response->json('url'));
        // check if join url is working
        $response        = Http::withOptions(['allow_redirects' =>['track_redirects' => true]])->get($response->json('url'));
        $headersRedirect = $response->getHeader(\GuzzleHttp\RedirectMiddleware::HISTORY_HEADER);
        $this->assertNotEmpty($headersRedirect);

        return Str::contains(last($headersRedirect), 'guest-wait.html');
    }
}
