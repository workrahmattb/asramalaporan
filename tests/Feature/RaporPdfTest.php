<?php

namespace Tests\Feature;

use App\Models\Asrama;
use App\Models\AssessmentPeriod;
use App\Models\SantriAssessment;
use App\Models\User;
use App\Services\AssessmentPdfService;
use App\Services\SantriService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RaporPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_pdf_receives_all_saved_statuses_for_selected_dorm_and_period(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $asrama = Asrama::create(['name' => 'Asrama A', 'code' => 'A']);
        $other = Asrama::create(['name' => 'Asrama B', 'code' => 'B']);
        $period = AssessmentPeriod::create(['name' => 'Semester lama', 'start_date' => today(), 'end_date' => today(), 'rating_labels' => ['A']]);
        $secondPeriod = AssessmentPeriod::create(['name' => 'Semester baru', 'start_date' => today(), 'end_date' => today(), 'rating_labels' => ['A']]);
        $pembina = User::factory()->create(['role' => 'musyrifah']);
        $pembina->asramas()->attach($asrama, ['assignment_role' => 'musyrifah', 'start_date' => today()->toDateString()]);
        $outside = User::factory()->create(['role' => 'musyrif']);
        $inactive = User::factory()->create(['role' => 'admin', 'is_active' => false]);
        $expectedIds = [];
        foreach ([[$asrama, $period, 'approved'], [$asrama, $period, 'draft'], [$asrama, $period, 'submitted'], [$asrama, $period, 'revision'], [$other, $period, 'approved'], [$asrama, $secondPeriod, 'approved']] as $i => [$dorm, $semester, $status]) {
            $student = app(SantriService::class)->save($admin, ['name' => 'Santri '.$i, 'asrama_id' => $dorm->id]);
            $report = SantriAssessment::create(['santri_id' => $student->id, 'assessment_period_id' => $semester->id, 'asrama_id' => $dorm->id, 'user_id' => $admin->id, 'student_name' => 'Nama snapshot '.$i, 'asrama_name' => $dorm->name, 'period_name' => $semester->name, 'status' => $status]);
            if ($dorm->id === $asrama->id && $semester->id === $period->id) {
                $expectedIds[] = $report->id;
                $this->assertTrue($admin->can('print', $report));
                $this->assertTrue($pembina->can('print', $report));
                $this->assertFalse($outside->can('print', $report));
                $this->assertFalse($inactive->can('print', $report));
            }
        }
        $this->mock(AssessmentPdfService::class, function (Mockery\MockInterface $mock) use ($expectedIds, $asrama, $period): void {
            $mock->shouldReceive('render')->once()->withArgs(fn ($view, $data, $filename) => $view === 'assessments.print-asrama' && $data['reports']->modelKeys() === $expectedIds && $filename === 'rapor-asrama-'.$asrama->id.'-periode-'.$period->id.'.pdf')
                ->andReturn(response('%PDF-test', 200, ['Content-Type' => 'application/pdf']));
        });
        $this->actingAs($admin)->get(route('santri.print-asrama', [$asrama, $period]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_print_heading_and_logo_match_report_dorm_gender(): void
    {
        $report = new SantriAssessment(['student_name' => 'Santri', 'asrama_name' => 'Asrama', 'period_name' => 'Periode']);
        $report->setRelation('user', new User(['name' => 'Pembina']));
        $report->setRelation('items', collect());
        foreach (['putra' => "Pondok Pesantren Syafa'aturrasul Putra", 'putri' => "Pondok Pesantren Syafa'aturrasul Putri", '' => "Pondok Pesantren Syafa'aturrasul"] as $gender => $name) {
            $report->setRelation('asrama', new Asrama(['gender' => $gender]));
            $html = view('assessments.print', compact('report'))->render();
            $this->assertStringContainsString(e($name), $html);
            $this->assertStringContainsString('Kuantan Singingi, Riau', $html);
            if ($gender !== '') {
                $this->assertStringContainsString('data:image/png;base64,'.base64_encode(file_get_contents(public_path('logo-pondok-'.$gender.'.png'))), $html);
                $this->assertStringNotContainsString('data:image/png;base64,'.base64_encode(file_get_contents(public_path('logo-pondok-'.($gender === 'putra' ? 'putri' : 'putra').'.png'))), $html);
            } else {
                $this->assertStringNotContainsString('<img ', $html);
            }
            if ($gender === 'putra') {
                $this->assertStringNotContainsString(e("Syafa'aturrasul Putri"), $html);
            } elseif ($gender === 'putri') {
                $this->assertStringNotContainsString(e("Syafa'aturrasul Putra"), $html);
            }
        }
    }

    public function test_template_escapes_names_and_uses_saved_report_values_and_configured_signatories(): void
    {
        $report = new SantriAssessment(['student_name' => '<script>santri</script>', 'student_nisn' => '0012345678', 'asrama_name' => 'Asrama snapshot', 'period_name' => 'Semester snapshot', 'development' => 'Catatan tersimpan', 'next_focus' => 'Fokus tersimpan', 'reviewed_at' => '2026-06-19']);
        $report->setRelation('user', new User(['name' => 'Pembina tersimpan']));
        $report->setRelation('items', collect());
        $html = view('assessments.print', compact('report'))->render();
        foreach (['&lt;script&gt;santri&lt;/script&gt;', '0012345678', 'Asrama snapshot', 'Semester snapshot', 'Catatan tersimpan', 'Fokus tersimpan', 'Pembina tersimpan', 'Teluk Kuantan', 'Septian Kusuma Wijaya, A.Md', 'KH. Dr. Hamdani Purba, Lc., MA'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('<script>santri</script>', $html);
        $response = app(AssessmentPdfService::class)->render('assessments.print', compact('report'), 'rapor-test.pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('inline; filename="rapor-test.pdf"', $response->headers->get('Content-Disposition'));
    }
}
