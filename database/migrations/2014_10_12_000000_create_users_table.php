<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name');
            $table->string('id_card_number');
            $table->integer('branch');
            $table->string('residential_address');
            $table->string('nickname');
            $table->enum('sex', ['male','female']);
            $table->string('marital_status');
            $table->string('office_address');
            $table->string('state_of_origin');
            $table->string('local_government');
            $table->string('phone');
            $table->integer('bvn');
            $table->integer('sms_alert');
            $table->enum('id_type', ['international_passport', 'drivers_license', 'national_id']);
            $table->string('passport');
            $table->string('nok');
            $table->string('nok_address');
            $table->enum('nok_residential_status', ['landlord', 'tenant']);
            $table->string('nok_sex');
            $table->string('nok_phone');
            $table->string('nok_relationship');
            $table->string('last_login');
            $table->rememberToken();
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
        Schema::dropIfExists('users');
    }
}
