<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('unverified users are redirected to verification notice', function () {
    $user = User::factory()->unverified()->create(['role' => 'user']);

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('verification.notice'));
});

test('verified users can access dashboard', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

test('verified admin users can access dashboard', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('dashboard'))->assertOk();
});
