<?php

namespace Tests\Feature\api\v1\Room;

use App\Enums\RoomUserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Services\MeetingService;
use App\Services\RoomFileService;
use Database\Seeders\ServerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user, $room, $role, $managePermission, $viewAllPermission;
    protected $file_valid, $file_wrongmime, $file_toobig;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user                 = User::factory()->create();
        $this->role                 = Role::factory()->create();
        $this->managePermission     = Permission::factory()->create(['name'=>'rooms.manage']);
        $this->viewAllPermission    = Permission::factory()->create(['name'=>'rooms.viewAll']);
        $this->room                 = Room::factory()->create();
        $this->file_valid           = UploadedFile::fake()->create('document.pdf', config('bigbluebutton.max_filesize') * 1000 - 1, 'application/pdf');
        $this->file_wrongmime       = UploadedFile::fake()->create('documents.zip', config('bigbluebutton.max_filesize') * 1000 - 1, 'application/zip');
        $this->file_toobig          = UploadedFile::fake()->create('document.pdf', config('bigbluebutton.max_filesize') * 1000 + 1, 'application/pdf');
    }

    /**
     * Test to upload a valid file as different users
     */
    public function testUploadValidFile()
    {
        // Testing guests
        $this->postJson(route('api.v1.rooms.files.add', ['room'=>$this->room]), ['file' => $this->file_valid])
            ->assertUnauthorized();

        // Testing user
        $this->actingAs($this->user)->postJson(route('api.v1.rooms.files.add', ['room'=>$this->room]), ['file' => $this->file_valid])
            ->assertForbidden();

        // Testing member
        $this->room->members()->attach($this->user, ['role'=>RoomUserRole::USER]);
        $this->actingAs($this->user)->postJson(route('api.v1.rooms.files.add', ['room'=>$this->room]), ['file' => $this->file_valid])
            ->assertForbidden();

        // Testing moderator member
        $this->room->members()->sync([$this->user->id,['role'=>RoomUserRole::MODERATOR]]);
        $this->actingAs($this->user)->postJson(route('api.v1.rooms.files.add', ['room'=>$this->room]), ['file' => $this->file_valid])
            ->assertForbidden();

        // Testing co-owner member
        $this->room->members()->sync([$this->user->id,['role'=>RoomUserRole::CO_OWNER]]);
        $this->actingAs($this->user)->postJson(route('api.v1.rooms.files.add', ['room'=>$this->room]), ['file' => $this->file_valid])
            ->assertSuccessful();

        // Testing owner
        $this->actingAs($this->room->owner)->postJson(route('api.v1.rooms.files.add', ['room'=>$this->room]), ['file' => $this->file_valid])
            ->assertSuccessful();

        // Remove membership roles
        $this->room->members()->sync([]);

        // Test view all permission
        $this->user->roles()->attach($this->role);
        $this->role->permissions()->attach($this->viewAllPermission);
        $this->actingAs($this->user)->postJson(route('api.v1.rooms.files.add', ['room'=>$this->room]), ['file' => $this->file_valid])
            ->assertForbidden();
        $this->role->permissions()->detach($this->viewAllPermission);

        // Test manage permission
        $this->role->permissions()->attach($this->managePermission);
        $this->actingAs($this->user)->postJson(route('api.v1.rooms.files.add', ['room'=>$this->room]), ['file' => $this->file_valid])
            ->assertSuccessful();
        $this->role->permissions()->detach($this->managePermission);

        Storage::disk('local')->assertExists($this->room->id.'/'.$this->file_valid->hashName());
    }

    /**
     * Test to upload different invalid files
     */
    public function testUploadInvalidFile()
    {
        // No file
        $this->actingAs($this->room->owner)->postJson(route('api.v1.rooms.files.add', ['room'=>$this->room]))
            ->assertJsonValidationErrors('file');

        // File invalid file type
        $this->actingAs($this->room->owner)->postJson(route('api.v1.rooms.files.add', ['room'=>$this->room]), ['file' => $this->file_wrongmime])
            ->assertJsonValidationErrors('file');
        Storage::disk('local')->assertMissing($this->room->id.'/'.$this->file_wrongmime->hashName());

        // File too large
        $this->actingAs($this->room->owner)->postJson(route('api.v1.rooms.files.add', ['room'=>$this->room]), ['file' => $this->file_toobig])
            ->assertJsonValidationErrors('file');
        Storage::disk('local')->assertMissing($this->room->id.'/'.$this->file_toobig->hashName());
    }

    /**
     * Testing access to internal and public file list as different users and permissions
     */
    public function testViewFiles()
    {
        $this->actingAs($this->room->owner)->postJson(route('api.v1.rooms.files.get', ['room'=>$this->room]), ['file' => $this->file_valid])
            ->assertSuccessful();

        \Auth::logout();
        // Testing access for room owners only file list

        // Testing guests without guest access
        $this->getJson(route('api.v1.rooms.files.get', ['room'=>$this->room]))
            ->assertForbidden();

        $this->room->allowGuests = true;
        $this->room->save();

        // Testing guests with guest access
        $this->getJson(route('api.v1.rooms.files.get', ['room'=>$this->room]))
            ->assertSuccessful();

        // Testing users
        $this->actingAs($this->user)->postJson(route('api.v1.rooms.files.get', ['room'=>$this->room]))
            ->assertForbidden();
        \Auth::logout();

        $this->room->accessCode = $this->faker->numberBetween(111111111, 999999999);
        $this->room->save();

        // Testing guests without access code
        $this->getJson(route('api.v1.rooms.files.get', ['room'=>$this->room]))
            ->assertForbidden();

        // Testing users without access code
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.files.get', ['room'=>$this->room]))
            ->assertForbidden();
        \Auth::logout();

        // Testing guests with access code
        $this->withHeaders(['Access-Code' => $this->room->accessCode])->getJson(route('api.v1.rooms.files.get', ['room'=>$this->room]))
            ->assertSuccessful()
            ->assertJsonCount(0, 'data.files');
        $this->flushHeaders();

        // Testing users with access code
        $this->actingAs($this->user)->withHeaders(['Access-Code' => $this->room->accessCode])->getJson(route('api.v1.rooms.files.get', ['room'=>$this->room]))
            ->assertSuccessful()
            ->assertJsonCount(0, 'data.files');
        $this->flushHeaders();

        // Testing member
        $this->room->members()->attach($this->user, ['role'=>RoomUserRole::USER]);
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.files.get', ['room'=>$this->room]))
            ->assertSuccessful()
            ->assertJsonCount(0, 'data.files');

        // Testing moderator member
        $this->room->members()->sync([$this->user->id,['role'=>RoomUserRole::MODERATOR]]);
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.files.get', ['room'=>$this->room]))
            ->assertSuccessful()
            ->assertJsonCount(0, 'data.files');

        // Testing co-owner member
        $this->room->members()->sync([$this->user->id,['role'=>RoomUserRole::CO_OWNER]]);
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.files.get', ['room'=>$this->room]))
            ->assertSuccessful()
            ->assertJsonFragment(['filename'=>$this->file_valid->name]);

        // Testing owner
        $this->actingAs($this->room->owner)->getJson(route('api.v1.rooms.files.get', ['room'=>$this->room]))
            ->assertSuccessful()
            ->assertJsonFragment(['filename'=>$this->file_valid->name]);

        // Remove membership roles and test with view all permission
        $this->room->members()->sync([]);
        $this->user->roles()->attach($this->role);
        $this->role->permissions()->attach($this->viewAllPermission);
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.files.get', ['room'=>$this->room]))
            ->assertSuccessful()
            ->assertJsonFragment(['filename'=>$this->file_valid->name]);
        $this->role->permissions()->detach($this->viewAllPermission);

        // -- Testing file list shared with all users that have access to the room --

        $this->room->members()->attach($this->user, ['role'=> RoomUserRole::USER]);

        $room_file           = $this->room->files()->where('filename', $this->file_valid->name)->first();
        $room_file->download = true;
        $room_file->save();

        // File shared
        $this->actingAs($this->user)->getJson(route('api.v1.rooms.files.get', ['room'=>$this->room]))
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.files')
            ->assertJsonFragment(['filename'=>$this->file_valid->name]);
    }

    /**
     * Test getting file download url and download of file that is shared with participants of a room without an access code
     */
    public function testDownloadFilesDownload()
    {
        $this->actingAs($this->room->owner)->postJson(route('api.v1.rooms.files.get', ['room'=>$this->room]), ['file' => $this->file_valid])
            ->assertSuccessful();
        $room_file           = $this->room->files()->where('filename', $this->file_valid->name)->first();
        $room_file->download = true;
        $room_file->save();
        \Auth::logout();

        $download_link = route('api.v1.rooms.files.show', ['room'=>$room_file->room,'file'=>$room_file]);

        // Access as guest, without guest access
        $this->get($download_link)
            ->assertForbidden();

        // Testing user
        $this->actingAs($this->user)->get($download_link)
            ->assertSuccessful();

        \Auth::logout();

        // Allow guest access
        $this->room->allowGuests = true;
        $this->room->save();
        $response = $this->get($download_link);
        $response->assertSuccessful();
        $this->assertIsString($response->json('url'));

        // Download file
        $this->get($response->json('url'))
            ->assertSuccessful();
    }

    /**
     * Test get download url of file that is shared with participants of a room that requires an access code
     */
    public function testDownloadFilesDownloadWithAccessCode()
    {
        $this->actingAs($this->room->owner)->postJson(route('api.v1.rooms.files.get', ['room'=>$this->room]), ['file' => $this->file_valid])
            ->assertSuccessful();
        $this->room->accessCode  = $this->faker->numberBetween(111111111, 999999999);
        $this->room->save();
        $room_file           = $this->room->files()->where('filename', $this->file_valid->name)->first();
        $room_file->download = true;
        $room_file->save();
        \Auth::logout();

        $download_link = route('api.v1.rooms.files.show', ['room'=>$room_file->room,'file'=>$room_file]);

        // Access as guest, without guest access and without access code
        $this->get($download_link)
            ->assertForbidden();

        // Access as guest, without guest access and with access code
        $this->withHeaders(['Access-Code' => $this->room->accessCode])->get($download_link)
            ->assertForbidden();
        $this->flushHeaders();

        // Testing user without access code
        $this->actingAs($this->user)->get($download_link)
            ->assertForbidden();

        // Testing user with access code
        $this->actingAs($this->user)->get(route('api.v1.rooms.show', ['room'=>$this->room->id, 'roomFile' => $room_file,'code'=>$this->room->accessCode]))
            ->assertSuccessful();

        // Testing member
        $this->room->members()->attach($this->user, ['role'=>RoomUserRole::USER]);
        $this->actingAs($this->user)->get($download_link)
            ->assertSuccessful();

        // Testing moderator member
        $this->room->members()->sync([$this->user->id,['role'=>RoomUserRole::MODERATOR]]);
        $this->actingAs($this->user)->get($download_link)
            ->assertSuccessful();

        // Testing owner
        $this->actingAs($this->room->owner)->get($download_link)
            ->assertSuccessful();

        // Remove membership roles and test with view all permission
        $this->room->members()->sync([]);
        $this->user->roles()->attach($this->role);
        $this->role->permissions()->attach($this->viewAllPermission);
        $this->actingAs($this->user)->get($download_link)
            ->assertSuccessful();

        \Auth::logout();

        // Allow guest access
        $this->room->allowGuests = true;
        $this->room->save();

        // Access as guest, with guest access and without access code
        $this->get($download_link)
            ->assertForbidden();

        // Access as guest, with guest access and access code
        $this->withHeaders(['Access-Code' => $this->room->accessCode])->get($download_link)
            ->assertSuccessful();
        $this->flushHeaders();
    }

    /**
     * Test get download url of file that is not with participants of a room
     */
    public function testDownloadFilesDownloadDisabled()
    {
        $this->actingAs($this->room->owner)->postJson(route('api.v1.rooms.files.get', ['room'=>$this->room]), ['file' => $this->file_valid])
            ->assertSuccessful();
        $this->room->allowGuests = true;
        $this->room->save();

        $room_file = $this->room->files()->where('filename', $this->file_valid->name)->first();
        \Auth::logout();

        $download_link = route('api.v1.rooms.files.show', ['room'=>$room_file->room,'file'=>$room_file]);

        // Access as guest
        $this->get($download_link)
            ->assertForbidden();

        // Testing member
        $this->room->members()->attach($this->user, ['role'=>RoomUserRole::USER]);
        $this->actingAs($this->user)->get($download_link)
            ->assertForbidden();

        // Testing moderator member
        $this->room->members()->sync([$this->user->id,['role'=>RoomUserRole::MODERATOR]]);
        $this->actingAs($this->user)->get($download_link)
            ->assertForbidden();

        // Testing owner
        $this->actingAs($this->room->owner)->get($download_link)
            ->assertSuccessful();

        // Remove membership roles and test with view all permission
        $this->room->members()->sync([]);
        $this->user->roles()->attach($this->role);
        $this->role->permissions()->attach($this->viewAllPermission);
        $this->actingAs($this->user)->get($download_link)
            ->assertSuccessful();
    }

    /**
     * Check if get download url of a file from an other room is working, if parameters in the url are changed
     */
    public function testDownloadFilesDownloadUrlManipulation()
    {
        $this->actingAs($this->room->owner)->postJson(route('api.v1.rooms.files.get', ['room'=>$this->room]), ['file' => $this->file_valid])
            ->assertSuccessful();

        $other_room = Room::factory()->create();

        $room_file = $this->room->files()->where('filename', $this->file_valid->name)->first();

        // Testing for room without permission
        $this->actingAs($this->room->owner)->get(route('api.v1.rooms.files.show', ['room'=>$other_room->id, 'file' => $room_file]))
            ->assertForbidden();

        // Testing for room with permission
        $other_room->owner()->associate($this->room->owner);
        $other_room->save();
        $this->actingAs($this->room->owner)->get(route('api.v1.rooms.files.show', ['room'=>$other_room->id, 'file' => $room_file]))
            ->assertNotFound();
    }

    /**
     * Testing download link given to bbb to download files
     */
    public function testDownloadForBBB()
    {
        $this->actingAs($this->room->owner)->postJson(route('api.v1.rooms.files.get', ['room'=>$this->room]), ['file' => $this->file_valid])
            ->assertSuccessful();

        $room_file = $this->room->files()->where('filename', $this->file_valid->name)->first();

        \Auth::logout();

        $this->get((new RoomFileService($room_file))->url())
            ->assertSuccessful();
    }

    /**
     * Testing to delete uploaded files
     */
    public function testFilesDelete()
    {
        $this->actingAs($this->room->owner)->postJson(route('api.v1.rooms.files.get', ['room'=>$this->room]), ['file' => $this->file_valid])
            ->assertSuccessful();
        $room_file = $this->room->files()->where('filename', $this->file_valid->name)->first();

        Storage::disk('local')->assertExists($this->room->id.'/'.$this->file_valid->hashName());

        \Auth::logout();

        // Testing guest
        $this->deleteJson(route('api.v1.rooms.files.remove', ['room'=>$this->room->id, 'file' => $room_file]))
            ->assertUnauthorized();

        // Testing user
        $this->actingAs($this->user)->deleteJson(route('api.v1.rooms.files.remove', ['room'=>$this->room->id, 'file' => $room_file]))
            ->assertForbidden();

        // Testing member
        $this->room->members()->attach($this->user, ['role'=>RoomUserRole::USER]);
        $this->actingAs($this->user)->deleteJson(route('api.v1.rooms.files.remove', ['room'=>$this->room->id, 'file' => $room_file]))
            ->assertForbidden();

        // Testing moderator member
        $this->room->members()->sync([$this->user->id,['role'=>RoomUserRole::MODERATOR]]);
        $this->actingAs($this->user)->deleteJson(route('api.v1.rooms.files.remove', ['room'=>$this->room->id, 'file' => $room_file]))
            ->assertForbidden();

        // Remove membership roles and test with view all permission
        $this->room->members()->sync([]);
        $this->user->roles()->attach($this->role);
        $this->role->permissions()->attach($this->viewAllPermission);
        $this->actingAs($this->user)->deleteJson(route('api.v1.rooms.files.remove', ['room'=>$this->room->id, 'file' => $room_file]))
            ->assertForbidden();
        $this->role->permissions()->detach($this->viewAllPermission);

        // test with manage permission
        $this->role->permissions()->attach($this->managePermission);
        $this->actingAs($this->user)->deleteJson(route('api.v1.rooms.files.remove', ['room'=>$this->room->id, 'file' => $room_file]))
            ->assertSuccessful();
        $this->role->permissions()->detach($this->managePermission);

        // recreate file
        $this->actingAs($this->room->owner)->postJson(route('api.v1.rooms.files.get', ['room'=>$this->room]), ['file' => $this->file_valid])
            ->assertSuccessful();
        $room_file = $this->room->files()->where('filename', $this->file_valid->name)->first();

        // Testing co-owner
        $this->room->members()->sync([$this->user->id,['role'=>RoomUserRole::CO_OWNER]]);
        $this->actingAs($this->user)->deleteJson(route('api.v1.rooms.files.remove', ['room'=>$this->room->id, 'file' => $room_file]))
            ->assertSuccessful();

        // recreate file
        $this->actingAs($this->room->owner)->postJson(route('api.v1.rooms.files.get', ['room'=>$this->room]), ['file' => $this->file_valid])
            ->assertSuccessful();
        $room_file = $this->room->files()->where('filename', $this->file_valid->name)->first();

        // Testing owner
        $this->actingAs($this->room->owner)->deleteJson(route('api.v1.rooms.files.remove', ['room'=>$this->room->id, 'file' => $room_file]))
            ->assertSuccessful();

        // Testing delete again
        $this->actingAs($this->room->owner)->deleteJson(route('api.v1.rooms.files.remove', ['room'=>$this->room->id, 'file' => $room_file]))
            ->assertNotFound();

        // Check if file was deleted as well
        Storage::disk('local')->assertMissing($this->room->id.'/'.$this->file_valid->hashName());
    }

    /**
     * Testing to get file download url for file that was deleted on the drive
     */
    public function testGetDownloadLinkForFileDeleteFromDrive()
    {
        $this->actingAs($this->room->owner)->postJson(route('api.v1.rooms.files.get', ['room'=>$this->room]), ['file' => $this->file_valid])
            ->assertSuccessful();
        $room_file = $this->room->files()->where('filename', $this->file_valid->name)->first();

        Storage::disk('local')->assertExists($this->room->id.'/'.$this->file_valid->hashName());

        // delete file on the drive
        Storage::disk('local')->delete($this->room->id.'/'.$this->file_valid->hashName());

        $download_link = route('api.v1.rooms.files.show', ['room'=>$room_file->room,'file'=>$room_file]);

        // try to access deleted file
        $this->get($download_link)
            ->assertNotFound();

        // Check if model was deleted as well
        $this->assertDatabaseMissing('room_files', ['id'=>$room_file->id]);
    }

    /**
     * Testing to access file that was deleted on the drive
     */
    public function testDownloadDeletedFileFromDrive()
    {
        $this->actingAs($this->room->owner)->postJson(route('api.v1.rooms.files.get', ['room'=>$this->room]), ['file' => $this->file_valid])
            ->assertSuccessful();
        $room_file = $this->room->files()->where('filename', $this->file_valid->name)->first();

        Storage::disk('local')->assertExists($this->room->id.'/'.$this->file_valid->hashName());

        // try to access deleted file
        $response = $this->get( route('api.v1.rooms.files.show', ['room'=>$room_file->room,'file'=>$room_file]))
            ->assertSuccessful();

        $this->assertIsString($response->json('url'));

        // delete file on the drive
        Storage::disk('local')->delete($this->room->id.'/'.$this->file_valid->hashName());

        // Download file
        $this->get($response->json('url'))
            ->assertNotFound();

        // Check if model was deleted as well
        $this->assertDatabaseMissing('room_files', ['id'=>$room_file->id]);
    }

    /**
     * Test if delete is working or bypassing permission by manipulating route parameters
     */
    public function testDeleteFileUrlManipulation()
    {
        $this->actingAs($this->room->owner)->postJson(route('api.v1.rooms.files.get', ['room'=>$this->room]), ['file' => $this->file_valid])
            ->assertSuccessful();

        $other_room = Room::factory()->create();

        $room_file = $this->room->files()->where('filename', $this->file_valid->name)->first();

        // Testing for room without permission
        $this->actingAs($this->room->owner)->deleteJson(route('api.v1.rooms.files.remove', ['room'=>$other_room->id, 'file' => $room_file]))
            ->assertForbidden();

        // Testing for room with permission
        $other_room->owner()->associate($this->room->owner);
        $other_room->save();
        $this->actingAs($this->room->owner)->deleteJson(route('api.v1.rooms.files.remove', ['room'=>$other_room->id, 'file' => $room_file]))
            ->assertNotFound();
    }

    /**
     * Test updating file attributes
     */
    public function testUpdateFile()
    {
        $this->actingAs($this->room->owner)->postJson(route('api.v1.rooms.files.get', ['room'=>$this->room]), ['file' => $this->file_valid])
            ->assertSuccessful();
        $room_file = $this->room->files()->where('filename', $this->file_valid->name)->first();

        $room_file->useinmeeting = false;
        $room_file->download     = false;
        $room_file->save();

        Storage::disk('local')->assertExists($this->room->id.'/'.$this->file_valid->hashName());

        \Auth::logout();

        $route  = route('api.v1.rooms.files.update', ['room'=>$this->room->id, 'file' => $room_file]);
        $params = [
            'useinmeeting'=> true,
            'download'    => true,
            'default'     => false,
        ];

        // Testing guest
        $this->putJson($route, $params)
            ->assertUnauthorized();

        // Testing user
        $this->actingAs($this->user)->putJson($route, $params)
            ->assertForbidden();

        // Testing member
        $this->room->members()->attach($this->user, ['role'=>RoomUserRole::USER]);
        $this->actingAs($this->user)->putJson($route, $params)
            ->assertForbidden();

        // Testing moderator member
        $this->room->members()->sync([$this->user->id,['role'=>RoomUserRole::MODERATOR]]);
        $this->actingAs($this->user)->putJson($route, $params)
            ->assertForbidden();

        // Testing co-owner
        $this->room->members()->sync([$this->user->id,['role'=>RoomUserRole::CO_OWNER]]);
        $this->actingAs($this->user)->putJson($route, $params)
            ->assertSuccessful();

        // Testing owner
        $this->actingAs($this->room->owner)->putJson($route, $params)
            ->assertSuccessful();

        // Remove membership roles and test with view all permission
        $this->room->members()->sync([]);
        $this->user->roles()->attach($this->role);
        $this->role->permissions()->attach($this->viewAllPermission);
        $this->actingAs($this->user)->putJson($route, $params)
            ->assertForbidden();
        $this->role->permissions()->detach($this->viewAllPermission);

        // test with manage permission
        $this->role->permissions()->attach($this->managePermission);
        $this->actingAs($this->user)->putJson($route, $params)
            ->assertSuccessful();
        $this->role->permissions()->detach($this->managePermission);

        $room_file->refresh();

        $this->assertTrue($room_file->useinmeeting);
        $this->assertTrue($room_file->download);
        $this->assertTrue($room_file->default); // Manually setting default to false is forbidden

        // Testing for other room
        $other_room = Room::factory()->create();
        // Testing for room without permission
        $this->actingAs($this->room->owner)->putJson(route('api.v1.rooms.files.update', ['room'=>$other_room->id, 'file' => $room_file]), $params)
            ->assertForbidden();

        // Testing for room with permission
        $other_room->owner()->associate($this->room->owner);
        $other_room->save();
        $this->actingAs($this->room->owner)->putJson(route('api.v1.rooms.files.update', ['room'=>$other_room->id, 'file' => $room_file]), $params)
            ->assertNotFound();
    }

    /**
     * Test setting file default
     */
    public function testUpdateDefault()
    {
        $file_1     = UploadedFile::fake()->create('document1.pdf', config('bigbluebutton.max_filesize') - 1, 'application/pdf');
        $file_2     = UploadedFile::fake()->create('document2.pdf', config('bigbluebutton.max_filesize') - 1, 'application/pdf');

        $this->actingAs($this->room->owner)->postJson(route('api.v1.rooms.files.get', ['room'=>$this->room]), ['file' => $file_1])
            ->assertSuccessful();
        $this->actingAs($this->room->owner)->postJson(route('api.v1.rooms.files.get', ['room'=>$this->room]), ['file' => $file_2])
            ->assertSuccessful();

        $room_file_1 = $this->room->files()->where('filename', $file_1->name)->first();
        $room_file_2 = $this->room->files()->where('filename', $file_2->name)->first();

        $this->assertFalse($room_file_1->default);
        $this->assertFalse($room_file_1->useinmeeting);

        $this->assertFalse($room_file_2->default);
        $this->assertFalse($room_file_2->useinmeeting);

        // Set new default without useinmeeting
        $this->actingAs($this->room->owner)->putJson(route('api.v1.rooms.files.update', ['room'=>$this->room->id, 'file' => $room_file_2]), ['default'=>true])
            ->assertSuccessful();
        $room_file_1->refresh();
        $room_file_2->refresh();
        $this->assertFalse($room_file_1->default);
        $this->assertFalse($room_file_1->useinmeeting);
        $this->assertFalse($room_file_2->default);
        $this->assertFalse($room_file_2->useinmeeting);

        // Set new default with useinmeeting
        $this->actingAs($this->room->owner)->putJson(route('api.v1.rooms.files.update', ['room'=>$this->room->id, 'file' => $room_file_1]), ['useinmeeting'=>true])
            ->assertSuccessful();
        $this->actingAs($this->room->owner)->putJson(route('api.v1.rooms.files.update', ['room'=>$this->room->id, 'file' => $room_file_2]), ['useinmeeting'=>true])
            ->assertSuccessful();
        $room_file_1->refresh();
        $room_file_2->refresh();
        $this->assertTrue($room_file_1->default);
        $this->assertTrue($room_file_1->useinmeeting);
        $this->assertFalse($room_file_2->default);
        $this->assertTrue($room_file_2->useinmeeting);

        // Remove current default
        $this->actingAs($this->room->owner)->deleteJson(route('api.v1.rooms.files.remove', ['room'=>$this->room->id, 'file' => $room_file_1]))
            ->assertSuccessful();
        $room_file_2->refresh();
        $this->assertTrue($room_file_2->default);
        $this->assertTrue($room_file_2->useinmeeting);
    }

    /**
     * Testing to start a meeting with a file
     */
    public function testStartMeetingWithFile()
    {
        $room = Room::factory()->create();

        $this->actingAs($room->owner)->postJson(route('api.v1.rooms.files.get', ['room'=>$room]), ['file' => $this->file_valid])
            ->assertSuccessful();

        // Adding server(s)
        $this->seed(ServerSeeder::class);

        // Create server
        $response = $this->actingAs($room->owner)->getJson(route('api.v1.rooms.start', ['room'=>$room,'record_attendance' => 1]))
            ->assertSuccessful();
        $this->assertIsString($response->json('url'));

        // Try to start bbb meeting
        $response = Http::withOptions(['allow_redirects' => false])->get($response->json('url'));
        $this->assertEquals(302, $response->status());
        $this->assertArrayHasKey('Location', $response->headers());

        // Clear
        (new MeetingService($room->runningMeeting()))->end();
    }
}
