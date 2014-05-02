<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('users', function(Blueprint $table) {
            $table -> string('email', 128) -> primary();
            $table -> string('password', 64);
            $table -> string('first_name', 50);
            $table -> string('last_name', 50);
            $table -> string('role', 16);
            $table -> string('remember_token', 100);
            $table -> dateTime('disabled_until');
            $table -> integer('failed_attempts');
            $table -> string('confirm_token', 100);
            $table -> timestamps();

            $table -> engine = 'InnoDB';
        });

        Schema::create('games', function(Blueprint $table) {
            $table -> string('id', 128) -> primary();
            $table -> integer('live');
            $table -> timestamps();

            $table -> engine = 'InnoDB';
        });

        Schema::create('weapons', function(Blueprint $table) {
            $table -> increments('id');
            $table -> string('name', 128);
            $table -> string('game_id', 128);
            $table -> timestamps();

            $table -> foreign('game_id') -> references('id') -> on('games') -> on_update('cascade') -> on_delete('cascade');

            $table -> engine = 'InnoDB';
        });
    }

    /**
     * Reverse the migrations.
     ** @return void
     */

    public function down() {
        Schema::drop('users');
        Schema::drop('games');
        Schema::drop('weapons');
    }

}
