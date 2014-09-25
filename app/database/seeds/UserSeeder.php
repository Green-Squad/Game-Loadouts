<?php
class UserSeeder extends Seeder {
  public function run() {    $user = new User;    $user -> username = 'GS Admin';    $user -> email = 'admin@admin.com';    $user -> password = Hash::make('password');    $user -> role = 'Admin';    $user -> disabled_until = date('Y-m-d H:i:s');    $user -> failed_attempts = 0;	$user -> confirm_token = 1;    $user -> save();  }}