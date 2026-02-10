<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function super_admin_can_access_all_user_routes()
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        
        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertStatus(200);
            
        $this->actingAs($user)
            ->get(route('admin.users.pending'))
            ->assertStatus(200);
            
        $this->actingAs($user)
            ->get(route('admin.users.invitations'))
            ->assertStatus(200);
    }
    
    /** @test */
    public function super_admin_can_access_role_routes()
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        
        $this->actingAs($user)
            ->get(route('admin.roles.index'))
            ->assertStatus(200);
    }
    
    /** @test */
    public function admin_can_access_user_routes()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertStatus(200);
            
        $this->actingAs($user)
            ->get(route('admin.users.pending'))
            ->assertStatus(200);
    }
    
    /** @test */
    public function admin_cannot_access_settings()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Settings routes require super_admin middleware
        $this->actingAs($user)
            ->get(route('admin.settings.general'))
            ->assertStatus(403);
            
        $this->actingAs($user)
            ->get(route('admin.settings.auth'))
            ->assertStatus(403);
    }
    
    /** @test */
    public function super_admin_can_access_settings()
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        
        $this->actingAs($user)
            ->get(route('admin.settings.general'))
            ->assertStatus(200);
            
        $this->actingAs($user)
            ->get(route('admin.settings.auth'))
            ->assertStatus(200);
            
        $this->actingAs($user)
            ->get(route('admin.settings.modules'))
            ->assertStatus(200);
            
        $this->actingAs($user)
            ->get(route('admin.settings.theme'))
            ->assertStatus(200);
            
        $this->actingAs($user)
            ->get(route('admin.settings.layout'))
            ->assertStatus(200);
    }
    
    /** @test */
    public function package_routes_accessible_to_all_admins()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        
        // Both admin and super_admin can access packages
        $this->actingAs($admin)
            ->get(route('admin.packages.available'))
            ->assertStatus(200);
            
        $this->actingAs($admin)
            ->get(route('admin.packages.installed'))
            ->assertStatus(200);
            
        $this->actingAs($superAdmin)
            ->get(route('admin.packages.available'))
            ->assertStatus(200);
    }
    
    /** @test */
    public function legacy_user_tab_urls_redirect_permanently()
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        
        // Old URL: /admin/users?tab=pending (via legacy route)
        $response = $this->actingAs($user)->get('/admin/users-legacy?tab=pending');
        $response->assertStatus(301);
        $response->assertRedirect(route('admin.users.pending'));
        
        // Old URL: /admin/users?tab=invitations
        $response = $this->actingAs($user)->get('/admin/users-legacy?tab=invitations');
        $response->assertStatus(301);
        $response->assertRedirect(route('admin.users.invitations'));
    }
    
    /** @test */
    public function legacy_package_urls_redirect_permanently()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Old URL: /admin/packages?view=installed
        $response = $this->actingAs($user)->get('/admin/packages-legacy?view=installed');
        $response->assertStatus(301);
        $response->assertRedirect(route('admin.packages.installed'));
        
        // Old URL: /admin/packages?view=updates
        $response = $this->actingAs($user)->get('/admin/packages-legacy?view=updates');
        $response->assertStatus(301);
        $response->assertRedirect(route('admin.packages.updates'));
    }
    
    /** @test */
    public function legacy_settings_urls_redirect_permanently()
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        
        // Old URL: /admin/settings?tab=auth
        $response = $this->actingAs($user)->get('/admin/settings-legacy?tab=auth');
        $response->assertStatus(301);
        $response->assertRedirect(route('admin.settings.auth'));
        
        // Old URL: /admin/settings?tab=modules
        $response = $this->actingAs($user)->get('/admin/settings-legacy?tab=modules');
        $response->assertStatus(301);
        $response->assertRedirect(route('admin.settings.modules'));
    }
    
    /** @test */
    public function deep_linking_works_correctly()
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        
        // Direct access to deep URL should work
        $this->actingAs($user)
            ->get(route('admin.users.invitations'))
            ->assertStatus(200)
            ->assertSee('Invitations', false); // Verify correct page loaded
    }
    
    /** @test */
    public function named_routes_are_defined()
    {
        $this->assertTrue(route('admin.dashboard') !== null);
        $this->assertTrue(route('admin.users.index') !== null);
        $this->assertTrue(route('admin.users.pending') !== null);
        $this->assertTrue(route('admin.users.invitations') !== null);
        $this->assertTrue(route('admin.packages.available') !== null);
        $this->assertTrue(route('admin.packages.installed') !== null);
        $this->assertTrue(route('admin.packages.updates') !== null);
        $this->assertTrue(route('admin.settings.general') !== null);
        $this->assertTrue(route('admin.settings.auth') !== null);
        $this->assertTrue(route('admin.settings.modules') !== null);
        $this->assertTrue(route('admin.settings.theme') !== null);
        $this->assertTrue(route('admin.settings.layout') !== null);
    }
    
    /** @test */
    public function admin_cannot_access_activity_logs()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        // Logs require super_admin
        $this->actingAs($admin)
            ->get(route('admin.logs'))
            ->assertStatus(403);
    }
    
    /** @test */
    public function super_admin_can_access_activity_logs()
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        
        $this->actingAs($user)
            ->get(route('admin.logs'))
            ->assertStatus(200);
    }
    
    /** @test */
    public function all_admins_can_access_export()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        
        $this->actingAs($admin)
            ->get(route('admin.export'))
            ->assertStatus(200);
            
        $this->actingAs($superAdmin)
            ->get(route('admin.export'))
            ->assertStatus(200);
    }
    
    /** @test */
    public function packages_default_route_redirects_to_available()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Accessing /admin/packages directly should redirect to /admin/packages/available
        $this->actingAs($user)
            ->get('/admin/packages')
            ->assertStatus(301)
            ->assertRedirect(route('admin.packages.available'));
    }
}
