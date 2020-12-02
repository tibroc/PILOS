<?php

namespace Tests\Feature\api\v1;

use App\Permission;
use App\Role;
use App\User;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;

    /**
     * Setup resources for all tests
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
    }

    /**
     * Tests that the correct application wide settings provided
     *
     * @return void
     */
    public function testApplicationSettings()
    {
        setting(['logo' => 'testlogo.svg']);
        setting(['pagination_page_size' => '123']);
        setting(['own_rooms_pagination_page_size' => '123']);
        setting(['room_limit' => '-1']);

        $this->getJson(route('api.v1.application'))
            ->assertJson([
                'data' => [
                    'logo'                           => 'testlogo.svg',
                    'pagination_page_size'           => '123',
                    'own_rooms_pagination_page_size' => '123',
                    'room_limit'                     => '-1',
                ]
            ])
            ->assertSuccessful();
    }

    /**
     * Tests that updates application settings with valid inputs and image file upload
     *
     * @return void
     */
    public function testUpdateApplicationSettingsWithValidInputsImageFile()
    {
        $payload = [
            'name'                           => 'test',
            'logo_file'                      => UploadedFile::fake()->image('logo.svg'),
            'favicon_file'                   => UploadedFile::fake()->create('favicon.ico', 100, 'image/x-icon'),
            'pagination_page_size'           => '10',
            'own_rooms_pagination_page_size' => '15',
            'room_limit'                     => '-1',
        ];

        // Unauthorized Test
        $this->putJson(route('api.v1.application.update'), $payload)
            ->assertUnauthorized();

        // Forbidden Test
        $this->actingAs($this->user)->putJson(route('api.v1.application.update'), $payload)
            ->assertForbidden();

        // Add necessary role and permission to user to update application settings
        $role       = factory(Role::class)->create();
        $permission = factory(Permission::class)->create(['name' => 'settings.update']);
        $role->permissions()->attach($permission);
        $this->user->roles()->attach($role);

        $this->actingAs($this->user)->putJson(route('api.v1.application.update'), $payload)
            ->assertSuccessful();
    }

    /**
     * Tests that updates application settings with valid inputs and and image url
     *
     * @return void
     */
    public function testUpdateApplicationSettingsWithValidInputsImageUrl()
    {
        $payload = [
            'name'                           => 'test',
            'favicon'                        => '/storage/image/favicon.ico',
            'logo'                           => '/storage/image/testfile.svg',
            'pagination_page_size'           => '10',
            'own_rooms_pagination_page_size' => '15',
            'room_limit'                     => '-1',
        ];

        // Unauthorized Test
        $this->putJson(route('api.v1.application.update'), $payload)
            ->assertUnauthorized();

        // Forbidden Test
        $this->actingAs($this->user)->putJson(route('api.v1.application.update'), $payload)
            ->assertForbidden();

        // Add necessary role and permission to user to update application settings
        $role       = factory(Role::class)->create();
        $permission = factory(Permission::class)->create(['name' => 'settings.update']);
        $role->permissions()->attach($permission);
        $this->user->roles()->attach($role);

        $this->actingAs($this->user)->putJson(route('api.v1.application.update'), $payload)
            ->assertSuccessful();
    }

    /**
     * Tests that updates application settings with valid inputs, having a file url and file upload.
     * Uploaded files should have a higher priority and overwrite possible urls
     *
     * @return void
     */
    public function testUpdateApplicationSettingsWithValidInputsImageFileAndUrl()
    {
        $payload = [
            'name'                           => 'test',
            'logo'                           => '/storage/image/testfile.svg',
            'logo_file'                      => UploadedFile::fake()->image('logo.svg'),
            'favicon'                        => '/storage/image/favicon.ico',
            'favicon_file'                   => UploadedFile::fake()->create('favicon.ico', 100, 'image/x-icon'),
            'pagination_page_size'           => '10',
            'own_rooms_pagination_page_size' => '15',
            'room_limit'                     => '-1',
        ];

        // Add necessary role and permission to user to update application settings
        $role       = factory(Role::class)->create();
        $permission = factory(Permission::class)->create(['name' => 'settings.update']);
        $role->permissions()->attach($permission);
        $this->user->roles()->attach($role);

        $response = $this->actingAs($this->user)->putJson(route('api.v1.application.update'), $payload);
        $response->assertSuccessful();

        $this->assertFalse($response->json('data.logo') == '/storage/image/testfile.svg');
    }

    /**
     * Tests that updates application settings with invalid inputs
     *
     * @return void
     */
    public function testUpdateApplicationSettingsWithInvalidInputs()
    {
        // Add necessary role and permission to user to update application settings
        $role       = factory(Role::class)->create();
        $permission = factory(Permission::class)->create(['name' => 'settings.update']);
        $role->permissions()->attach($permission);
        $this->user->roles()->attach($role);

        $this->actingAs($this->user)->putJson(route('api.v1.application.update'),
            [
                'name'                           => '',
                'favicon'                        => '',
                'favicon_file'                   => 'notimagefile',
                'logo'                           => '',
                'logo_file'                      => 'notimagefile',
                'pagination_page_size'           => 'notnumber',
                'own_rooms_pagination_page_size' => 'notnumber',
                'room_limit'                     => 'notnumber',
            ]
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'name',
                'favicon_file',
                'favicon',
                'logo',
                'logo_file',
                'pagination_page_size',
                'own_rooms_pagination_page_size',
                'room_limit'
            ]);
    }

    /**
     * Tests that updates application settings with invalid inputs for numeric input
     *
     * @return void
     */
    public function testUpdateApplicationSettingsMinMax()
    {
        // Add necessary role and permission to user to update application settings
        $role       = factory(Role::class)->create();
        $permission = factory(Permission::class)->create(['name' => 'settings.update']);
        $role->permissions()->attach($permission);
        $this->user->roles()->attach($role);

        // inputs lower than allowed minimum
        $this->actingAs($this->user)->putJson(route('api.v1.application.update'),
            [
                'name'                           => 'test',
                'favicon'                        => '/storage/image/favicon.ico',
                'logo'                           => '/storage/image/testfile.svg',
                'pagination_page_size'           => '0',
                'own_rooms_pagination_page_size' => '0',
                'room_limit'                     => '-2',
            ]
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'pagination_page_size',
                'own_rooms_pagination_page_size',
                'room_limit'
            ]);

        // inputs higher than allowed minimum
        $this->putJson(route('api.v1.application.update'),
            [
                'name'                           => 'test',
                'favicon'                        => '/storage/image/favicon.ico',
                'logo'                           => '/storage/image/testfile.svg',
                'pagination_page_size'           => '101',
                'own_rooms_pagination_page_size' => '26',
                'room_limit'                     => '101',
            ]
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'pagination_page_size',
                'own_rooms_pagination_page_size',
                'room_limit'
            ]);
    }
}