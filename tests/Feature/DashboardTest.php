<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('non-admin users cannot access dashboard', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user)->get(route('dashboard'))->assertStatus(403);
});

test('admin users can access dashboard', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('dashboard'))->assertOk();
});