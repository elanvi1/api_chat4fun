<?php

use App\Models\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNotificationUser extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notification_user', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('notification_id')->unsigned();
            $table->bigInteger('user_id')->unsigned();
            $table->enum('status',[Notification::READ_STATUS,Notification::UNREAD_STATUS,Notification::REMOVED_STATUS])->default((Notification::UNREAD_STATUS));
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('notification_id')->references('id')->on('notifications')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('notification_user');
    }
}
