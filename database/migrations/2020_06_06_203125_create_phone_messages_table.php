<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePhoneMessagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('phone_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('message');
            $table->string('sid', 64);
            $table->unsignedBigInteger('from_phone_id');
            $table->unsignedBigInteger('to_phone_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('from_phone_id')->references('id')->on('phone_numbers');
            $table->foreign('to_phone_id')->references('id')->on('phone_numbers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('phone_messages');
    }
}
