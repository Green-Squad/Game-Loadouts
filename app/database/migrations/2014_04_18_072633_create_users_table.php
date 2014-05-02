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
	}

	/**
	 * Reverse the migrations.
	 ** @return void
	 */

	public function down() {
		Schema::drop('users');
	}

}
