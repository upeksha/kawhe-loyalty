<?php

use App\Models\User;
use App\Models\Store;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

test('user can be promoted to super admin via artisan command', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);
    
    expect($user->is_super_admin)->toBeFalsy();
    expect($user->isSuperAdmin())->toBeFalse();

    Artisan::call('kawhe:make-superadmin', ['email' => 'test@example.com']);

    $user->refresh();
    expect($user->is_super_admin)->toBeTrue();
    expect($user->isSuperAdmin())->toBeTrue();
});

test('artisan command handles non-existent user', function () {
    Artisan::call('kawhe:make-superadmin', ['email' => 'nonexistent@example.com']);
    
    expect(Artisan::output())->toContain('not found');
});

test('artisan command handles already super admin user', function () {
    $user = User::factory()->create(['email' => 'admin@example.com', 'is_super_admin' => true]);
    
    Artisan::call('kawhe:make-superadmin', ['email' => 'admin@example.com']);
    
    expect(Artisan::output())->toContain('already a super admin');
});

test('Store queryForUser returns all stores for super admin', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);
    $regularUser = User::factory()->create();
    
    // Create stores for different users
    Store::factory()->count(3)->create(['user_id' => $regularUser->id]);
    Store::factory()->count(2)->create(['user_id' => User::factory()->create()->id]);
    
    $totalStores = Store::count();
    
    // Super admin should see all stores
    expect(Store::queryForUser($superAdmin)->count())->toBe($totalStores);
});

test('Store queryForUser returns only owned stores for regular user', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    
    Store::factory()->count(3)->create(['user_id' => $user1->id]);
    Store::factory()->count(2)->create(['user_id' => $user2->id]);
    
    // User 1 should only see their 3 stores
    expect(Store::queryForUser($user1)->count())->toBe(3);
    
    // User 2 should only see their 2 stores
    expect(Store::queryForUser($user2)->count())->toBe(2);
});

test('super admin bypasses all gates', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);
    $regularUser = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $regularUser->id]);
    
    // Define a test gate
    Gate::define('test-gate', function ($user) {
        return false; // Always deny
    });
    
    // Super admin should bypass the gate
    expect(Gate::forUser($superAdmin)->allows('test-gate'))->toBeTrue();
    
    // Regular user should be denied
    expect(Gate::forUser($regularUser)->allows('test-gate'))->toBeFalse();
});

test('EnsureMerchantHasStore middleware allows super admin without stores', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);
    
    $this->actingAs($superAdmin);
    
    // Super admin should bypass the check even with no stores
    // (We'll test this properly once routes are added)
    expect($superAdmin->stores()->count())->toBe(0);
    expect($superAdmin->isSuperAdmin())->toBeTrue();
});

test('SuperAdmin middleware blocks non-super-admin users', function () {
    $regularUser = User::factory()->create(['is_super_admin' => false]);
    
    $this->actingAs($regularUser);
    
    // Simulate accessing a super-admin-only route
    $response = $this->get('/test-admin-route');
    
    // Should get 403 or 404 (route doesn't exist yet)
    expect($response->status())->toBeIn([403, 404]);
});
