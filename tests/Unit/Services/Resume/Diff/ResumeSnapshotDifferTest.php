<?php

namespace Tests\Unit\Services\Resume\Diff;

use App\Services\Resume\Diff\EntryDiff;
use App\Services\Resume\Diff\ResumeDiff;
use App\Services\Resume\Diff\ResumeSnapshotDiffer;
use PHPUnit\Framework\TestCase;

class ResumeSnapshotDifferTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function job(string $company, string $start, array $overrides = []): array
    {
        return array_merge([
            'jobTitle' => 'Engineer',
            'company' => $company,
            'location' => 'Portland, OR',
            'dates' => [$start, 'Present'],
            'bullets' => ["Did work at {$company}"],
            'jobTitleLabel' => '',
            'salaryStart' => null,
            'salaryEnd' => null,
            'isFreelance' => false,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function resume(): array
    {
        return [
            'personal' => [
                'name' => 'Jason Vertucio',
                'title' => 'Engineer',
                'email' => 'jason@example.com',
                'summary' => 'Builds <b>things</b>.',
            ],
            'skills' => [
                'top' => [['title' => 'Languages', 'list' => ['PHP', 'Go', 'Perl']]],
                'other' => [['title' => 'Tools', 'list' => ['Docker', 'Git']]],
            ],
            'experience' => [
                $this->job('Acme', '2020', ['bullets' => ['Led a team of 4 engineers', 'Shipped the API', 'Cut costs']]),
                $this->job('Globex', '2017'),
            ],
            'education' => [['institution' => 'State University', 'degree' => 'BS', 'dates' => ['2010', '2014']]],
            'projects' => [
                ['projectName' => 'Alpha', 'description' => 'First', 'bullets' => ['One']],
                ['projectName' => 'Beta', 'description' => 'Second', 'bullets' => []],
            ],
        ];
    }

    private function diff(array $before, array $after): ResumeDiff
    {
        return (new ResumeSnapshotDiffer)->diff($before, $after);
    }

    /**
     * @return list<array{section: string, item: string, field: string, kind: string, before: ?string, after: ?string, shown: bool}>
     */
    private function changes(ResumeDiff $diff): array
    {
        return array_map(
            fn (array $change): array => array_diff_key($change, ['tokens' => true, 'section_key' => true]),
            $diff->changes(),
        );
    }

    public function test_identical_snapshots_have_no_changes(): void
    {
        $diff = $this->diff($this->resume(), $this->resume());

        $this->assertTrue($diff->isEmpty());
        $this->assertSame(0, $diff->count());
    }

    public function test_missing_null_and_empty_values_are_equal(): void
    {
        $after = $this->resume();
        $after['personal']['phone'] = '';
        $after['experience'][1]['jobTitleLabel'] = null;
        unset($after['experience'][1]['salaryStart']);
        $after['personal']['name'] = '  Jason Vertucio ';

        $this->assertTrue($this->diff($this->resume(), $after)->isEmpty());
    }

    public function test_a_reworded_bullet_is_one_modified_bullet_with_word_tokens(): void
    {
        $after = $this->resume();
        $after['experience'][0]['bullets'][0] = 'Led a team of 6 engineers';

        $diff = $this->diff($this->resume(), $after);

        $this->assertSame([[
            'section' => 'Experience',
            'item' => 'Acme — Engineer',
            'field' => 'Bullet 1',
            'kind' => 'modified',
            'before' => 'Led a team of 4 engineers',
            'after' => 'Led a team of 6 engineers',
            'shown' => true,
        ]], $this->changes($diff));
        $this->assertContains(['op' => 'delete', 'text' => '4'], $diff->changes()[0]['tokens']);
        $this->assertContains(['op' => 'insert', 'text' => '6'], $diff->changes()[0]['tokens']);
    }

    public function test_a_bullet_added_between_unchanged_bullets_is_the_only_change(): void
    {
        $after = $this->resume();
        array_splice($after['experience'][0]['bullets'], 1, 0, ['Mentored three juniors']);

        $diff = $this->diff($this->resume(), $after);
        $acme = $diff->section('experience')->entries[0];

        $this->assertSame([['section' => 'Experience', 'item' => 'Acme — Engineer', 'field' => 'Bullet 2', 'kind' => 'added', 'before' => null, 'after' => 'Mentored three juniors', 'shown' => true]], $this->changes($diff));
        $this->assertSame(['equal', 'added', 'equal', 'equal'], array_column($acme->bullets, 'status'));
    }

    public function test_an_added_job_and_a_removed_job(): void
    {
        $after = $this->resume();
        $after['experience'][1] = $this->job('Initech', '2015', ['jobTitle' => 'Developer', 'location' => 'Austin, TX', 'bullets' => ['Fixed the TPS reports', 'Moved the stapler']]);

        $diff = $this->diff($this->resume(), $after);
        $entries = $diff->section('experience')->entries;

        $this->assertSame(['unchanged', 'removed', 'added'], array_map(fn (EntryDiff $entry): string => $entry->status, $entries));
        $this->assertSame([
            ['section' => 'Experience', 'item' => 'Globex — Engineer', 'field' => '', 'kind' => 'removed', 'before' => 'Engineer, Globex, 2017 - Present (1 bullet)', 'after' => null, 'shown' => true],
            ['section' => 'Experience', 'item' => 'Initech — Developer', 'field' => '', 'kind' => 'added', 'before' => null, 'after' => 'Developer, Initech, 2015 - Present (2 bullets)', 'shown' => true],
        ], $this->changes($diff));
    }

    public function test_a_removed_entry_is_listed_where_it_was(): void
    {
        $after = $this->resume();
        array_shift($after['projects']);

        $entries = $this->diff($this->resume(), $after)->section('projects')->entries;

        $this->assertSame(['Alpha', 'Beta'], array_map(fn (EntryDiff $entry): string => $entry->label, $entries));
        $this->assertSame(['removed', 'unchanged'], array_map(fn (EntryDiff $entry): string => $entry->status, $entries));
        $this->assertSame('Alpha', $entries[0]->value('projectName'));
    }

    public function test_swapped_jobs_are_reported_as_moved_without_field_changes(): void
    {
        $after = $this->resume();
        $after['experience'] = array_reverse($after['experience']);

        $diff = $this->diff($this->resume(), $after);

        $this->assertSame([['section' => 'Experience', 'item' => 'Acme — Engineer', 'field' => 'Position', 'kind' => 'moved', 'before' => null, 'after' => null, 'shown' => true]], $this->changes($diff));
        $this->assertSame(['unchanged', 'unchanged'], array_map(fn (EntryDiff $entry): string => $entry->status, $diff->section('experience')->entries));
    }

    public function test_a_renamed_entry_sharing_most_fields_is_one_modified_entry(): void
    {
        $after = $this->resume();
        $after['experience'][0]['company'] = 'Acme Corporation';

        $diff = $this->diff($this->resume(), $after);

        $this->assertSame([['section' => 'Experience', 'item' => 'Acme Corporation — Engineer', 'field' => 'Company', 'kind' => 'modified', 'before' => 'Acme', 'after' => 'Acme Corporation', 'shown' => true]], $this->changes($diff));
    }

    public function test_skills_are_compared_as_a_set_ignoring_case(): void
    {
        $after = $this->resume();
        $after['skills']['top'][0]['list'] = ['PHP', 'go', 'Rust'];

        $diff = $this->diff($this->resume(), $after);
        $languages = $diff->section('skills')->group('top')[0];

        $this->assertSame([
            ['section' => 'Skills', 'item' => 'Languages', 'field' => 'Skill', 'kind' => 'removed', 'before' => 'Perl', 'after' => null, 'shown' => true],
            ['section' => 'Skills', 'item' => 'Languages', 'field' => 'Skill', 'kind' => 'added', 'before' => null, 'after' => 'Rust', 'shown' => true],
        ], $this->changes($diff));
        $this->assertSame([['status' => 'equal', 'text' => 'PHP'], ['status' => 'equal', 'text' => 'go'], ['status' => 'removed', 'text' => 'Perl'], ['status' => 'added', 'text' => 'Rust']], $languages->items);
    }

    public function test_reordered_skills_are_one_order_change(): void
    {
        $after = $this->resume();
        $after['skills']['other'][0]['list'] = ['Git', 'Docker'];

        $this->assertSame(
            [['section' => 'Skills', 'item' => 'Tools', 'field' => 'Skill order', 'kind' => 'order_changed', 'before' => null, 'after' => null, 'shown' => true]],
            $this->changes($this->diff($this->resume(), $after)),
        );
    }

    public function test_a_formatting_only_change_is_reported_as_formatting(): void
    {
        $after = $this->resume();
        $after['personal']['summary'] = 'Builds <strong>things</strong>.';

        $changes = $this->diff($this->resume(), $after)->changes();

        $this->assertSame('formatting', $changes[0]['kind']);
        $this->assertNull($changes[0]['tokens']);
    }

    public function test_a_salary_change_is_formatted_and_not_shown(): void
    {
        $before = $this->resume();
        $before['experience'][0]['salaryStart'] = ['amount' => 85000.0, 'period' => 'per_year'];
        $after = $before;
        $after['experience'][0]['salaryStart'] = ['amount' => 92500.5, 'period' => 'per_year'];

        $this->assertSame(
            [['section' => 'Experience', 'item' => 'Acme — Engineer', 'field' => 'Salary (start)', 'kind' => 'modified', 'before' => '85,000 / year', 'after' => '92,500.50 / year', 'shown' => false]],
            $this->changes($this->diff($before, $after)),
        );
    }

    public function test_a_contact_change_is_not_shown(): void
    {
        $after = $this->resume();
        $after['personal']['email'] = 'jv@example.com';
        $after['experience'][1]['isFreelance'] = true;

        $changes = $this->changes($this->diff($this->resume(), $after));

        $this->assertSame(['Email', false, 'jason@example.com', 'jv@example.com'], [$changes[0]['field'], $changes[0]['shown'], $changes[0]['before'], $changes[0]['after']]);
        $this->assertSame(['Freelance', false, 'No', 'Yes'], [$changes[1]['field'], $changes[1]['shown'], $changes[1]['before'], $changes[1]['after']]);
    }

    public function test_a_single_job_sent_where_the_list_belongs_is_a_one_job_list(): void
    {
        $before = $this->resume();
        $before['experience'] = [$this->job('Acme', '2020')];
        $after = $before;
        $after['experience'] = $this->job('Acme', '2020', ['location' => 'Remote']);
        $after['projects'] = 'not a list';
        $after['skills'] = ['top' => 'nope', 'other' => [['title' => 'Tools', 'list' => 'Docker']]];
        $after['personal'] = null;

        $diff = $this->diff($before, $after);
        $experience = $diff->section('experience')->entries;

        $this->assertCount(1, $experience);
        $this->assertSame('modified', $experience[0]->status);
        $this->assertSame('Remote', $experience[0]->field('location')->after);
        $this->assertSame(['removed', 'removed'], array_map(fn (EntryDiff $entry): string => $entry->status, $diff->section('projects')->entries));
    }

    public function test_a_moved_entry_among_several_is_the_only_one_reported(): void
    {
        $before = $this->resume();
        $before['experience'] = [$this->job('A', '2020'), $this->job('B', '2019'), $this->job('C', '2018'), $this->job('D', '2017')];
        $after = $before;
        $after['experience'] = [$before['experience'][3], $before['experience'][0], $before['experience'][1], $before['experience'][2]];

        $this->assertSame(
            [['section' => 'Experience', 'item' => 'D — Engineer', 'field' => 'Position', 'kind' => 'moved', 'before' => null, 'after' => null, 'shown' => true]],
            $this->changes($this->diff($before, $after)),
        );
    }

    public function test_malformed_values_are_compared_without_throwing(): void
    {
        $after = $this->resume();
        $after['personal'] = ['name' => ['Jason'], 'title' => null, 'summary' => 42];
        $after['skills'] = 'not skills';
        $after['experience'] = [null, 'a string', ['company' => ['Acme'], 'dates' => [['2020'], null], 'bullets' => [['nested'], 7, null, 'Real bullet']]];
        $after['education'] = null;
        $after['projects'] = [['projectName' => 'Alpha', 'bullets' => 'One']];

        $diff = $this->diff($this->resume(), $after);

        $this->assertGreaterThan(0, $diff->count());
        $this->assertSame('42', $diff->section('personal')->entries[0]->value('summary'));
        $job = array_values(array_filter($diff->section('experience')->entries, fn (EntryDiff $entry): bool => $entry->isAdded()))[0];
        $this->assertSame(['7', 'Real bullet'], $job->after['bullets']);
        $alpha = $diff->section('projects')->entries[0];
        $this->assertSame('Alpha', $alpha->label);
        $this->assertSame([['status' => 'equal', 'text' => 'One', 'tokens' => null]], $alpha->bullets);
    }
}
