<?php

use App\Models\Friendship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFriendshipsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('friendships', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('main_id')->unsigned();
            $table->bigInteger('friend_id')->unsigned();
            $table->enum('status',[Friendship::ACCEPTED_STATUS,Friendship::BLOCKED_STATUS,Friendship::REMOVED_STATUS,Friendship::DELETED_STATUS,Friendship::PENDING_STATUS])->default(Friendship::PENDING_STATUS);
            $table->timestamp('blocked_at')->nullable();
            $table->string('alias',100)->nullable();
            $table->enum('presence_friend',[Friendship::USER_ACTIVE,Friendship::USER_INACTIVE])->default(Friendship::USER_INACTIVE);
            $table->bigInteger('unread_messages')->unsigned()->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['main_id','friend_id']);

            $table->foreign('main_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('friend_id')->references('id')->on('users')->onDelete('NO ACTION');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('friendships');
    }
}
