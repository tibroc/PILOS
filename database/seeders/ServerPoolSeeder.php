<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ServerPoolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Only create server pools if none exits
        if (\App\Models\ServerPool::all()->count() == 0) {
            $default = \App\Models\ServerPool::create(['name' => 'Standard', 'description' => '']);
            $default->servers()->sync(\App\Models\Server::all());
        }
    }
}
