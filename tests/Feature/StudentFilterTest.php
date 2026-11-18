<?php

namespace Tests\Feature;

use App\Models\Participation;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentFilterTest extends TestCase
{
    use RefreshDatabase;

    private string $teacher = '/ruang-guru/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/siswa';

    private function student(string $nis, string $name, string $class, bool $voted = false): Student
    {
        $student = Student::query()->create(['nis' => $nis, 'name' => $name, 'class_name' => $class]);
        if ($voted) {
            Participation::query()->create(['student_id' => $student->id]);
        }
        return $student;
    }

    public function test_search_class_and_participation_filters_work_together(): void
    {
        $this->student('00101', 'Alya Putri', 'X A', true);
        $this->student('00102', 'ALYA Khair', 'X A');
        $this->student('00103', 'Alya Bintang', 'X B', true);
        $this->student('00104', 'Bima Pratama', 'X A', true);

        $this->get($this->teacher.'?'.http_build_query(['q' => 'aLyA', 'class' => 'X A', 'participation' => 'voted']))
            ->assertOk()->assertViewHas('students', fn ($students) => $students->pluck('nis')->all() === ['00101']);
        $this->get($this->teacher.'?'.http_build_query(['q' => 'alya', 'class' => 'X A', 'participation' => 'pending']))
            ->assertOk()->assertViewHas('students', fn ($students) => $students->pluck('nis')->all() === ['00102']);
        $this->get($this->teacher.'?'.http_build_query(['q' => 'alya', 'participation' => 'all']))
            ->assertOk()->assertViewHas('students', fn ($students) => $students->total() === 3);
    }

    public function test_nis_search_preserves_leading_zeroes_and_wildcards_are_literal(): void
    {
        $this->student('00017', 'Nama 100%_A', 'X A');
        $this->student('17000', 'Nama lain', 'X A');
        $this->get($this->teacher.'?q=0001')->assertOk()
            ->assertViewHas('students', fn ($students) => $students->pluck('nis')->all() === ['00017']);
        $this->get($this->teacher.'?'.http_build_query(['q' => '%_']))->assertOk()
            ->assertViewHas('students', fn ($students) => $students->pluck('nis')->all() === ['00017']);
    }

    public function test_filter_pagination_retains_query_and_does_not_disclose_candidate_choices(): void
    {
        for ($i = 1; $i <= 51; $i++) {
            $this->student(sprintf('%05d', $i), sprintf('Siswa %02d', $i), 'X A');
        }
        $this->student('99999', 'Siswa kelas lain', 'X B');
        $params = ['q' => 'Siswa', 'class' => 'X A', 'participation' => 'pending'];
        $this->get($this->teacher.'?'.http_build_query($params))->assertOk()
            ->assertViewHas('students', function ($students) use ($params) {
                parse_str(parse_url($students->nextPageUrl(), PHP_URL_QUERY), $next);
                return $students->count() === 50 && $students->total() === 51 &&
                    $next['q'] === $params['q'] && $next['class'] === $params['class'] &&
                    $next['participation'] === $params['participation'] && $next['page'] === '2' &&
                    $students->every(fn ($student) => ! array_key_exists('candidate_id', $student->getAttributes()) &&
                        array_key_exists('participation_exists', $student->getAttributes()));
            });
        $this->get($this->teacher.'?'.http_build_query($params + ['page' => 2]))->assertOk()
            ->assertViewHas('students', fn ($students) => $students->count() === 1);
    }

    public function test_page_size_can_be_selected_and_is_kept_in_pagination_links(): void
    {
        for ($i = 1; $i <= 51; $i++) {
            $this->student(sprintf('%05d', $i), sprintf('Siswa %02d', $i), 'X A');
        }

        foreach ([10, 25, 50, 100] as $pageSize) {
            $params = ['class' => 'X A', 'per_page' => $pageSize];
            $this->get($this->teacher.'?'.http_build_query($params))->assertOk()
                ->assertViewHas('students', function ($students) use ($pageSize) {
                    if ($students->perPage() !== $pageSize || $students->count() !== min(51, $pageSize)) {
                        return false;
                    }
                    if ($pageSize === 100) {
                        return ! $students->hasMorePages();
                    }
                    parse_str(parse_url($students->nextPageUrl(), PHP_URL_QUERY), $next);
                    return $next['per_page'] === (string) $pageSize && $next['class'] === 'X A' && $next['page'] === '2';
                });
        }

        $this->get($this->teacher.'?per_page=25&page=2')->assertOk()
            ->assertViewHas('students', fn ($students) => $students->perPage() === 25 && $students->count() === 25);
    }

    public function test_no_matches_and_invalid_filter_values_are_handled(): void
    {
        $this->student('001', 'Alya', 'X A');
        $this->get($this->teacher.'?q=tidak-ada')->assertOk()->assertSee('Tidak ada siswa yang sesuai')
            ->assertViewHas('registered', 1)->assertViewHas('students', fn ($students) => $students->total() === 0);
        $this->from($this->teacher)->get($this->teacher.'?participation=invalid')->assertRedirect($this->teacher)->assertSessionHasErrors('participation');
        $this->from($this->teacher)->get($this->teacher.'?q%5B%5D=Alya')->assertRedirect($this->teacher)->assertSessionHasErrors('q');
        $this->from($this->teacher)->get($this->teacher.'?per_page=1000')->assertRedirect($this->teacher)->assertSessionHasErrors('per_page');
    }
}
