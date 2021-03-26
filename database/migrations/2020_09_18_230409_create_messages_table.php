<?php

use App\Models\Message;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMessagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('sender_id')->unsigned()->index();
            $table->bigInteger('messageable_id')->unsigned()->index();
            $table->string('messageable_type');
            $table->longText('message');
            $table->enum('status',[Message::SENT_STATUS,Message::READ_STATUS])->default(Message::SENT_STATUS);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('sender_id')->references('id')->on('users')->onDelete('NO ACTION');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('messages');
    }
}
