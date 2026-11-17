<?php

namespace Tests\Feature;

use App\Services\Resume\Diff\ResumeBaseline;
use App\Services\Resume\Diff\ResumeDiff;
use App\Services\Resume\Diff\ResumeSnapshotDiffer;
use Tests\TestCase;

class ResumeDiffViewTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function resume(): array
    {
        return [
            'personal' => ['name' => 'Jason Vertucio', 'title' => 'Engineer', 'summary' => 'Builds things.', 'email' => 'jason@example.com'],
            'skills' => ['top' => [['title' => 'Languages', 'list' => ['PHP', 'Go']]], 'other' => []],
            'experience' => [['jobTitle' => 'Engineer', 'company' => 'Acme', 'dates' => ['2020', 'Present'], 'bullets' => ['Led a team of 4 engineers', 'Shipped it']]],
            'education' => [['institution' => 'State University', 'degree' => 'BS', 'dates' => ['2010', '2014']]],
            'projects' => [['projectName' => 'Alpha', 'bullets' => ['One']], ['projectName' => 'Beta', 'bullets' => []]],
        ];
    }

    private function baseline(): ResumeBaseline
    {
        return new ResumeBaseline('base', 'version 2026.1.0, the one it branched from', [], ['base' => 'Version it branched from (2026.1.0)']);
    }

    private function diff(array $after): ResumeDiff
    {
        return (new ResumeSnapshotDiffer)->diff($this->resume(), $after);
    }

    public function test_the_panel_lists_a_reworded_bullet_and_an_added_skill_under_their_sections(): void
    {
        $after = $this->resume();
        $after['experience'][0]['bullets'][0] = 'Led a team of 6 engineers';
        $after['skills']['top'][0]['list'][] = 'Rust';

        $view = $this->blade('<x-resume.diff.changes-panel :diff="$diff" :baseline="$baseline" :revision-id="7" />', [
            'diff' => $this->diff($after),
            'baseline' => $this->baseline(),
        ]);

        $view->assertSeeInOrder(['2 changes', 'Compared with version 2026.1.0, the one it branched from.', 'Skills', 'Added', 'Languages', 'Rust', 'Experience', 'Changed', 'Acme — Engineer', 'Bullet 1'], false);
        $view->assertSee('<del class="rd-del">4</del><ins class="rd-ins">6</ins>', false);
        $view->assertDontSee('name="compare"', false);
    }

    public function test_the_panel_says_when_the_revision_matches_its_baseline(): void
    {
        $view = $this->blade('<x-resume.diff.changes-panel :diff="$diff" :baseline="$baseline" :revision-id="7" />', [
            'diff' => $this->diff($this->resume()),
            'baseline' => $this->baseline(),
        ]);

        $view->assertSee('No changes')
            ->assertSee('This revision matches version 2026.1.0, the one it branched from.', false)
            ->assertDontSee('rd-row', false);
    }

    public function test_the_panel_escapes_snapshot_text_and_flags_hidden_fields(): void
    {
        $after = $this->resume();
        $after['personal']['email'] = '<script>alert(1)</script>@example.com';

        $view = $this->blade('<x-resume.diff.changes-panel :diff="$diff" :baseline="$baseline" :revision-id="7" />', [
            'diff' => $this->diff($after),
            'baseline' => $this->baseline(),
        ]);

        $view->assertSee('Not shown on resume')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_the_panel_offers_only_the_available_baselines(): void
    {
        $baseline = new ResumeBaseline('previous', 'revision #1', [], [
            'base' => 'Version it branched from (2026.1.0)',
            'previous' => 'Previous revision (#1)',
        ]);

        $view = $this->blade('<x-resume.diff.changes-panel :diff="$diff" :baseline="$baseline" :revision-id="7" />', [
            'diff' => $this->diff($this->resume()),
            'baseline' => $baseline,
        ]);

        $view->assertSee('<input type="hidden" name="revision" value="7">', false)
            ->assertSee('<option value="previous" selected>', false)
            ->assertDontSee('value="live"', false);
    }

    public function test_the_marked_up_resume_strikes_a_removed_project_and_marks_a_reworded_bullet(): void
    {
        $after = $this->resume();
        $after['experience'][0]['bullets'][0] = 'Led a team of 6 engineers';
        array_shift($after['projects']);
        $diff = $this->diff($after);

        $experience = (string) $this->blade('<x-resume.diff.experience :section="$section" />', ['section' => $diff->section('experience')]);
        $projects = (string) $this->blade('<x-resume.diff.projects :section="$section" />', ['section' => $diff->section('projects')]);

        $this->assertStringContainsString('Led a team of <del class="rd-del">4</del><ins class="rd-ins">6</ins> engineers', $experience);
        $this->assertMatchesRegularExpression('/class="rd-entry rd-removed[^"]*">\s*<p class="rd-tags">\s*<span class="rd-tag rd-tag--removed">Removed<\/span>.*?Alpha/s', $projects);
        $this->assertStringNotContainsString('rd-removed', explode('Beta', $projects)[1] ?? '');
    }

    public function test_the_marked_up_skills_show_added_and_removed_items_in_place(): void
    {
        $after = $this->resume();
        $after['skills']['top'][0]['list'] = ['PHP', 'Rust'];

        $skills = (string) $this->blade('<x-resume.diff.skills :section="$section" />', ['section' => $this->diff($after)->section('skills')]);

        $this->assertStringContainsString('PHP &middot; <del class="rd-del">Go</del> &middot; <ins class="rd-ins">Rust</ins>', $skills);
    }
}
