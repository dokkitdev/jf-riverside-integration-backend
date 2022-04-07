<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class JobsTableAddIsAppointmentSentField extends Migration
{
    public function up()
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->boolean('is_appointment_sent')->default(false);
        });
    }

    public function down()
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropColumn('is_appointment_sent');
        });
    }
}
