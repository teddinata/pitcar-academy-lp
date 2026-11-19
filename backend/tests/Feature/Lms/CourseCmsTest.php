<?php

namespace Tests\Feature\Lms;

use App\Enums\CourseStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Filament\Resources\Courses\RelationManagers\AttachmentsRelationManager;
use App\Filament\Resources\Courses\RelationManagers\ModulesRelationManager;
use App\Filament\Resources\Courses\RelationManagers\OffersRelationManager;
use App\Models\Course;
use App\Models\CourseOffer;
use App\Models\User;
use App\Services\SiteRebuild;
use Database\Seeders\LmsCourseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Admin › Kelas online › Produk online. */
class CourseCmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->seed(LmsCourseSeeder::class);
    }

    private function as(UserRole $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user);

        return $user;
    }

    private function ac(): Course
    {
        return Course::where('slug', 'kelas-online-ac')->firstOrFail();
    }

    public function test_consultants_cannot_open_the_menu_and_only_admins_create_or_change_prices(): void
    {
        $this->as(UserRole::Consultant);
        $this->assertFalse(CourseResource::canViewAny());

        $this->as(UserRole::Manager);
        $this->assertTrue(CourseResource::canViewAny());
        $this->assertFalse(CourseResource::canCreate());
        $this->assertFalse(CourseResource::canEditPrices());

        $this->as(UserRole::Admin);
        $this->assertTrue(CourseResource::canCreate());
        $this->assertTrue(CourseResource::canEditPrices());
        $this->assertFalse(CourseResource::canDelete($this->ac()));
    }

    public function test_the_list_shows_both_classes_with_their_bundle(): void
    {
        $this->as(UserRole::Admin);

        Livewire::test(ListCourses::class)
            ->assertCanSeeTableRecords(Course::all())
            ->assertSee('Kelas Inspeksi Mobil Bekas (Rp79.000)');
    }

    public function test_admin_changes_the_price_and_checkout_charges_it_at_once(): void
    {
        $this->as(UserRole::Admin);

        Livewire::test(EditCourse::class, ['record' => $this->ac()->getRouteKey()])
            ->fillForm(['price' => 129000, 'strike_price' => 549000])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Harga tersimpan');

        $this->getJson('/api/checkout/courses/kelas-online-ac')
            ->assertJsonPath('course.price', 129000)
            ->assertJsonPath('course.strike_price', 549000);
    }

    public function test_a_manager_edits_the_text_but_cannot_change_the_price(): void
    {
        $this->as(UserRole::Manager);

        Livewire::test(EditCourse::class, ['record' => $this->ac()->getRouteKey()])
            ->assertFormFieldIsDisabled('price')
            ->fillForm(['title' => 'Kelas AC Mobil (revisi)', 'price' => 1000])
            ->call('save')
            ->assertHasNoFormErrors();

        $course = $this->ac();
        $this->assertSame('Kelas AC Mobil (revisi)', $course->title);
        $this->assertSame(99000, $course->price);
    }

    public function test_slug_and_ad_id_are_locked_once_created(): void
    {
        $this->as(UserRole::Admin);

        Livewire::test(EditCourse::class, ['record' => $this->ac()->getRouteKey()])
            ->assertFormFieldIsDisabled('slug')
            ->assertFormFieldIsDisabled('tracking_id');
    }

    public function test_a_strike_price_must_be_above_the_price(): void
    {
        $this->as(UserRole::Admin);

        Livewire::test(EditCourse::class, ['record' => $this->ac()->getRouteKey()])
            ->fillForm(['price' => 99000, 'strike_price' => 50000])
            ->call('save')
            ->assertHasFormErrors(['strike_price']);
    }

    public function test_a_new_class_starts_as_draft_and_reports_its_slug_to_the_pixels(): void
    {
        $this->as(UserRole::Admin);

        Livewire::test(CreateCourse::class)
            ->fillForm(['title' => 'Kelas Kelistrikan Dasar', 'slug' => 'kelas-online-kelistrikan', 'price' => 99000])
            ->call('create')
            ->assertHasNoFormErrors();

        $course = Course::where('slug', 'kelas-online-kelistrikan')->firstOrFail();
        $this->assertSame(CourseStatus::Draft, $course->status);
        $this->assertSame('kelas-online-kelistrikan', $course->tracking_id);
        // A draft is not for sale.
        $this->getJson('/api/checkout/courses/kelas-online-kelistrikan')->assertNotFound();
    }

    public function test_curriculum_modules_hold_ordered_videos(): void
    {
        $this->as(UserRole::Admin);
        $course = $this->ac();

        Livewire::test(ModulesRelationManager::class, ['ownerRecord' => $course, 'pageClass' => EditCourse::class])
            ->callTableAction('create', data: [
                'title' => 'Pemeriksaan dasar',
                'lessons' => [
                    ['title' => 'Mengenal komponen', 'youtube_url' => 'https://youtu.be/abcdefghijk', 'duration_seconds' => '12:30', 'is_preview' => true],
                    ['title' => 'Cuci AC tanpa bongkar', 'youtube_url' => null, 'duration_seconds' => null, 'is_preview' => false],
                ],
            ])
            ->assertHasNoTableActionErrors();

        $module = $course->modules()->with('lessons')->firstOrFail();
        $this->assertSame(['Mengenal komponen', 'Cuci AC tanpa bongkar'], $module->lessons->pluck('title')->all());
        $this->assertSame(750, $module->lessons->first()->duration_seconds);
        $this->assertTrue($module->lessons->first()->is_preview);
    }

    public function test_a_lesson_link_must_be_a_youtube_video(): void
    {
        $this->as(UserRole::Admin);

        Livewire::test(ModulesRelationManager::class, ['ownerRecord' => $this->ac(), 'pageClass' => EditCourse::class])
            ->callTableAction('create', data: [
                'title' => 'Modul',
                'lessons' => [['title' => 'Video', 'youtube_url' => 'https://vimeo.com/123', 'duration_seconds' => null, 'is_preview' => false]],
            ])
            ->assertHasTableActionErrors();
    }

    public function test_attachments_are_stored_privately_with_type_and_size(): void
    {
        Storage::fake(config('filesystems.default'));
        $this->as(UserRole::Admin);
        $course = $this->ac();

        Livewire::test(AttachmentsRelationManager::class, ['ownerRecord' => $course, 'pageClass' => EditCourse::class])
            ->callTableAction('create', data: [
                'path' => UploadedFile::fake()->createWithContent('modul.pdf', "%PDF-1.4\n".str_repeat('isi modul ', 200)),
                'name' => 'Modul Pendamping AC Mobil.pdf',
                'lesson_id' => null,
            ])
            ->assertHasNoTableActionErrors();

        $attachment = $course->attachments()->firstOrFail();
        $this->assertSame('Modul Pendamping AC Mobil.pdf', $attachment->name);
        $this->assertStringStartsWith('lms/attachments/', $attachment->path);
        $this->assertGreaterThan(0, $attachment->size);
        Storage::disk(config('filesystems.default'))->assertExists($attachment->path);
    }

    public function test_bundle_offers_are_managed_per_class_without_duplicates(): void
    {
        $this->as(UserRole::Admin);
        $ac = $this->ac();
        $kelistrikan = Course::create(['title' => 'Kelas Kelistrikan', 'slug' => 'kelas-kelistrikan', 'price' => 99000, 'status' => CourseStatus::Published]);

        $manager = fn () => Livewire::test(OffersRelationManager::class, ['ownerRecord' => $ac, 'pageClass' => EditCourse::class]);

        // Inspeksi is already offered with AC.
        $inspeksi = Course::where('slug', 'kelas-online-inspeksi')->first();
        $manager()->callTableAction('create', data: ['offer_course_id' => $inspeksi->id, 'price' => 50000, 'is_active' => true])
            ->assertHasTableActionErrors(['offer_course_id']);

        $manager()->callTableAction('create', data: ['offer_course_id' => $kelistrikan->id, 'price' => 69000, 'headline' => 'Sekalian kelistrikan?', 'is_active' => true])
            ->assertHasNoTableActionErrors();

        $this->getJson('/api/checkout/courses/kelas-online-ac')
            ->assertJsonCount(2, 'offers')
            ->assertJsonFragment(['slug' => 'kelas-kelistrikan', 'offer_price' => 69000]);
        $this->assertSame(1, CourseOffer::where('course_id', $ac->id)->where('offer_course_id', $kelistrikan->id)->count());
    }

    public function test_republish_runs_the_deploy_workflow_on_github(): void
    {
        config(['services.site_deploy' => ['repo' => 'pemilik/pitcar-academy-lp', 'token' => 'ghp_test', 'workflow' => 'deploy.yml', 'ref' => 'main']]);
        Http::fake(['api.github.com/*' => Http::response(null, 204)]);
        $this->as(UserRole::Admin);

        Livewire::test(ListCourses::class)
            ->callAction('republish')
            ->assertNotified('Diterbitkan ulang');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.github.com/repos/pemilik/pitcar-academy-lp/actions/workflows/deploy.yml/dispatches'
            && $request['ref'] === 'main'
            && $request->hasHeader('Authorization', 'Bearer ghp_test'));
    }

    public function test_republish_explains_itself_when_not_set_up(): void
    {
        config(['services.site_deploy.repo' => null, 'services.site_deploy.token' => null]);
        Http::fake();

        $this->assertSame(false, app(SiteRebuild::class)->dispatch()['ok']);
        Http::assertNothingSent();
    }
}
