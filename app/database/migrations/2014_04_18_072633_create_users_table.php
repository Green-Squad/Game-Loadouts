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
            $table -> string('username', 128);
            $table -> string('password', 64);
            $table -> string('role', 16);
            $table -> integer('converted_guest');
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
            $table -> string('image_url', 128);
            $table -> string('thumb_url', 128);
            $table -> timestamps();

            $table -> engine = 'InnoDB';
        });

        Schema::create('weapons', function(Blueprint $table) {
            $table -> increments('id');
            $table -> string('name', 128);
            $table -> string('game_id', 128);
            $table -> string('image_url', 128);
            $table -> string('thumb_url', 128);
            $table -> integer('min_attachments');
            $table -> integer('max_attachments');
            $table -> string('type', 64);
            $table -> timestamps();

            $table -> foreign('game_id') -> references('id') -> on('games') -> onUpdate('cascade') -> onDelete('cascade');

            $table -> engine = 'InnoDB';

        });

        Schema::create('attachments', function(Blueprint $table) {
            $table -> increments('id');
            $table -> string('name', 128);
            $table -> string('slot', 64);
            $table -> string('game_id', 128);
            $table -> string('image_url', 128);
            $table -> string('thumb_url', 128);
            $table -> timestamps();

            $table -> foreign('game_id') -> references('id') -> on('games') -> onUpdate('cascade') -> onDelete('cascade');

            $table -> engine = 'InnoDB';
        });

        Schema::create('attachment_weapon', function(Blueprint $table) {
            $table -> increments('id');
            $table -> integer('attachment_id') -> unsigned();
            $table -> integer('weapon_id') -> unsigned();
            $table -> timestamps();

            $table -> foreign('attachment_id') -> references('id') -> on('attachments') -> onUpdate('cascade') -> onDelete('cascade');
            $table -> foreign('weapon_id') -> references('id') -> on('weapons') -> onUpdate('cascade') -> onDelete('cascade');

            $table -> engine = 'InnoDB';
        });

        Schema::create('loadouts', function(Blueprint $table) {
            $table -> increments('id');
            $table -> integer('weapon_id') -> unsigned();
            $table -> timestamps();

            $table -> foreign('weapon_id') -> references('id') -> on('weapons') -> onUpdate('cascade') -> onDelete('cascade');

            $table -> engine = 'InnoDB';
        });

        Schema::create('attachment_loadout', function(Blueprint $table) {
            $table -> increments('id');
            $table -> integer('attachment_id') -> unsigned();
            $table -> integer('loadout_id') -> unsigned();
            $table -> timestamps();

            $table -> foreign('attachment_id') -> references('id') -> on('attachments') -> onUpdate('cascade') -> onDelete('cascade');
            $table -> foreign('loadout_id') -> references('id') -> on('loadouts') -> onUpdate('cascade') -> onDelete('cascade');

            $table -> engine = 'InnoDB';

        });

        Schema::create('loadout_user', function(Blueprint $table) {
            $table -> increments('id');
            $table -> string('user_id', 128);
            $table -> integer('loadout_id') -> unsigned();
            $table -> timestamps();

            $table -> foreign('user_id') -> references('email') -> on('users') -> onUpdate('cascade') -> onDelete('cascade');
            $table -> foreign('loadout_id') -> references('id') -> on('loadouts') -> onUpdate('cascade') -> onDelete('cascade');

            $table -> engine = 'InnoDB';
        });
        
        Schema::create('betas', function(Blueprint $table) {
            $table -> string('id', 32) -> primary();
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
