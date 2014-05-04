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
            $table -> string('image_url', 128);
            $table -> timestamps();

            $table -> foreign('game_id') -> references('id') -> on('games') -> on_update('cascade') -> on_delete('cascade');

            $table -> engine = 'InnoDB';

        });

        Schema::create('attachments', function(Blueprint $table) {
            $table -> increments('id');
            $table -> string('name', 128);
            $table -> integer('slot');
            $table -> string('game_id', 128);
            $table -> string('image_url', 128);
            $table -> timestamps();

            $table -> foreign('game_id') -> references('id') -> on('games') -> on_update('cascade') -> on_delete('cascade');

            $table -> engine = 'InnoDB';
        });

        Schema::create('attachment_weapon', function(Blueprint $table) {
            $table -> increments('id');
            $table -> integer('attachment_id') -> unsigned();
            $table -> integer('weapon_id') -> unsigned();
            $table -> timestamps();

            $table -> foreign('attachment_id') -> references('id') -> on('attachments') -> on_update('cascade') -> on_delete('cascade');
            $table -> foreign('weapon_id') -> references('id') -> on('weapons') -> on_update('cascade') -> on_delete('cascade');

            $table -> engine = 'InnoDB';
        });

        Schema::create('loadouts', function(Blueprint $table) {
            $table -> increments('id');
            $table -> integer('weapon_id') -> unsigned();
            $table -> timestamps();

            $table -> foreign('weapon_id') -> references('id') -> on('weapons') -> on_update('cascade') -> on_delete('cascade');

            $table -> engine = 'InnoDB';
        });

        Schema::create('attachment_loadout', function(Blueprint $table) {
            $table -> increments('id');
            $table -> integer('attachment_id') -> unsigned();
            $table -> integer('loadout_id') -> unsigned();
            $table -> timestamps();

            $table -> foreign('attachment_id') -> references('id') -> on('attachments') -> on_update('cascade') -> on_delete('cascade');
            $table -> foreign('loadout_id') -> references('id') -> on('loadouts') -> on_update('cascade') -> on_delete('cascade');

            $table -> engine = 'InnoDB';

        });

        Schema::create('loadout_user', function(Blueprint $table) {
            $table -> increments('id');
            $table -> string('user_id', 128);
            $table -> integer('loadout_id') -> unsigned();
            $table -> timestamps();

            $table -> foreign('user_id') -> references('email') -> on('users') -> on_update('cascade') -> on_delete('cascade');
            $table -> foreign('loadout_id') -> references('id') -> on('loadouts') -> on_update('cascade') -> on_delete('cascade');

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
        Schema::drop('attachments');
        Schema::drop('attachment_weapon');
    }

}
