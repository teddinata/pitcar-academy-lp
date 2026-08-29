<?php

namespace Tests\Feature\Dashboard;

use App\Enums\UserRole;
use App\Filament\Resources\EducationConsultants\EducationConsultantResource;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\EducationConsultant;
use App\Models\Lead;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsLeadPayload;
use Tests\TestCase;

class LeadDashboardAccessTest extends TestCase
{
    use BuildsLeadPayload;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Filament::setCurrentPanel('admin');
    }

    private function makeLead(array $overrides = []): Lead
    {
        $this->postJson('/api/leads', $this->leadPayload($overrides));

        return Lead::latest('id')->firstOrFail();
    }

    public function test_an_inactive_user_cannot_reach_the_panel(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin, 'is_active' => false]);

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_an_active_user_can_reach_the_panel(): void
    {
        $user = User::factory()->create(['role' => UserRole::Consultant, 'is_active' => true]);

        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_a_consultant_only_sees_leads_assigned_to_them(): void
    {
        $mine = EducationConsultant::factory()->create(['priority' => 1]);
        $theirs = EducationConsultant::factory()->create(['priority' => 5]);

        $myLead = $this->makeLead(['whatsapp_number' => '081211110001']);
        $myLead->forceFill(['assigned_consultant_id' => $mine->id])->save();

        $otherLead = $this->makeLead(['whatsapp_number' => '081211110002']);
        $otherLead->forceFill(['assigned_consultant_id' => $theirs->id])->save();

        $user = User::factory()->create(['role' => UserRole::Consultant]);
        $mine->forceFill(['user_id' => $user->id])->save();

        $this->actingAs($user);

        $visible = LeadResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($visible->contains($myLead->id));
        $this->assertFalse($visible->contains($otherLead->id), 'a consultant must not read another consultant\'s prospect data');
    }

    public function test_a_consultant_without_a_consultant_record_sees_nothing(): void
    {
        $this->makeLead();
        $user = User::factory()->create(['role' => UserRole::Consultant]);

        $this->actingAs($user);

        $this->assertSame(0, LeadResource::getEloquentQuery()->count());
    }

    public function test_a_manager_sees_every_lead(): void
    {
        $this->makeLead(['whatsapp_number' => '081211110003']);
        $this->makeLead(['whatsapp_number' => '081211110004']);

        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));

        $this->assertSame(2, LeadResource::getEloquentQuery()->count());
    }

    public function test_only_admins_manage_users(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->assertTrue(UserResource::canAccess());

        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));
        $this->assertFalse(UserResource::canAccess());

        $this->actingAs(User::factory()->create(['role' => UserRole::Consultant]));
        $this->assertFalse(UserResource::canAccess());
    }

    public function test_consultants_cannot_manage_the_consultant_roster(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));
        $this->assertTrue(EducationConsultantResource::canAccess());

        $this->actingAs(User::factory()->create(['role' => UserRole::Consultant]));
        $this->assertFalse(EducationConsultantResource::canAccess());
    }

    public function test_leads_cannot_be_created_by_hand(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

        $this->assertFalse(LeadResource::canCreate());
    }

    public function test_only_an_admin_can_delete_a_lead(): void
    {
        $lead = $this->makeLead();

        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));
        $this->assertFalse(LeadResource::canDelete($lead));

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->assertTrue(LeadResource::canDelete($lead));
    }
}
