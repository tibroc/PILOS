<?php

namespace Database\Seeders;

use App\Models\RoomType;
use App\Models\ServerPool;
use Illuminate\Database\Seeder;

class RoomTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Only create room types if none exits
        if(RoomType::all()->count()==0) {
            $pool = ServerPool::all()->first();

            $pool->roomTypes()->createMany([
                ['name' => 'Lecture', 'color' => '#16a085'],
                ['name' => 'Meeting', 'color' => '#2c3e50'],
                ['name' => 'Exam', 'color' => '#c0392b'],
                ['name' => 'Seminar', 'color' => '#2980b9']
            ]);
        }
    }
}
