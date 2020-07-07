<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMeetingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->string('id');
            $table->string('room_id',11);
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');
            $table->unsignedBigInteger('server_id')->nullable();
            $table->foreign('server_id')->references('id')->on('servers')->onDelete('cascade');
            $table->unique('id');
            $table->string('attendeePW',64)->nullable();
            $table->string('moderatorPW',64)->nullable();
            $table->boolean('isBreakout')->default(false);
            $table->integer('sequence')->default(0);
            $table->dateTime('start')->nullable();
            $table->dateTime('end')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('meetings');
    }
}
