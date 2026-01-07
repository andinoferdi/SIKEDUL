<?php

use App\Models\User;

test('non-admin cannot access admin users page', function () {
    $user = User::factory()->create(['role' => 'user']);

    $response = $this->actingAs($user)->get(route('admin.users.index'));

    $response->assertStatus(403);
});

test('admin can view users list', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get(route('admin.users.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('admin/users/index'));
});

test('admin can search users', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['email' => 'test@example.com']);

    $response = $this->actingAs($admin)->get(route('admin.users.index', ['search' => 'test']));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('admin/users/index')
        ->has('users.data', 1)
    );
});

test('admin can disable user', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['is_disabled' => 0]);

    $response = $this->actingAs($admin)->patch(route('admin.users.toggle-status', $user));

    expect($user->fresh()->is_disabled)->toBeTrue();
    $response->assertRedirect();
    $response->assertSessionHas('success');
});

test('admin can enable disabled user', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['is_disabled' => 1]);

    $response = $this->actingAs($admin)->patch(route('admin.users.toggle-status', $user));

    expect($user->fresh()->is_disabled)->toBeFalse();
    $response->assertRedirect();
    $response->assertSessionHas('success');
});

test('admin cannot disable themselves', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_disabled' => 0]);

    $response = $this->actingAs($admin)->patch(route('admin.users.toggle-status', $admin));

    expect($admin->fresh()->is_disabled)->toBeFalse();
    $response->assertSessionHasErrors(['error']);
});

test('disabled user cannot login', function () {
    $user = User::factory()->withoutTwoFactor()->create(['is_disabled' => 1]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors(['email']);
});

test('admin can view pagination', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->count(15)->create();

    $response = $this->actingAs($admin)->get(route('admin.users.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('admin/users/index')
        ->has('users.data', 10) // First page has 10 items
        ->where('users.total', 16) // Including the admin
    );
});

test('admin can create new user', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $userData = [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'username' => 'newuser',
        'phone' => '+628123456789',
        'password' => 'password123',
        'timezone' => 'Asia/Jakarta',
    ];

    $response = $this->actingAs($admin)->post(route('admin.users.store'), $userData);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(User::where('email', 'newuser@example.com')->exists())->toBeTrue();

    $user = User::where('email', 'newuser@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('New User');
    expect($user->role)->toBe('user'); // Auto-assigned to 'user' role

    // Check email_verified_at is set (auto-verified)
    $this->assertDatabaseHas('users', [
        'email' => 'newuser@example.com',
    ]);
    $this->assertDatabaseMissing('users', [
        'email' => 'newuser@example.com',
        'email_verified_at' => null,
    ]);
});

test('create user validates required fields', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->post(route('admin.users.store'), []);

    $response->assertSessionHasErrors(['name', 'email', 'username', 'password']);
});

test('create user validates unique email', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $existingUser = User::factory()->create(['email' => 'existing@example.com']);

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Test User',
        'email' => 'existing@example.com',
        'username' => 'testuser',
        'password' => 'password123',
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('regular user cannot create users', function () {
    $user = User::factory()->create(['role' => 'user']);

    $response = $this->actingAs($user)->post(route('admin.users.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'username' => 'testuser',
        'password' => 'password123',
    ]);

    $response->assertStatus(403);
});
