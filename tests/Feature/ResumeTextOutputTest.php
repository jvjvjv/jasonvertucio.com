<?php

namespace Tests\Feature;

use App\Support\SafeInlineHtml;
use Tests\TestCase;

class ResumeTextOutputTest extends TestCase
{
    private const HOSTILE = 'Leads <strong>teams</strong> and <em>ships</em>.<script>alert(1)</script> <a href="javascript:alert(2)">link</a> <b onclick="alert(3)">bold</b> <img src=x onerror=alert(4)>';

    private function assertSafe(string $html, bool $checkAnchors = true): void
    {
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('<img', $html);
        if ($checkAnchors) {
            $this->assertStringNotContainsString('<a ', $html);
        }
        $this->assertStringContainsString('<strong>teams</strong>', $html);
        $this->assertStringContainsString('<em>ships</em>', $html);
        $this->assertStringContainsString('<b>bold</b>', $html);
    }

    public function test_the_helper_keeps_only_bare_emphasis_tags(): void
    {
        $this->assertSafe(SafeInlineHtml::clean(self::HOSTILE));
        $this->assertSame('a<br>b', SafeInlineHtml::clean('a<br />b'));
        $this->assertSame('R&amp;D &lt; QA', SafeInlineHtml::clean('R&amp;D < QA'));
        $this->assertSame('', SafeInlineHtml::clean(null));
        $this->assertSame('', SafeInlineHtml::clean(['not', 'text']));
    }

    public function test_a_malformed_tag_cannot_reach_the_page_as_markup(): void
    {
        $html = SafeInlineHtml::clean('<b<script>alert(1)</script> <b title=">" onclick=alert(1)>x</b>');

        $this->assertStringNotContainsString('<script', $html);
        $this->assertDoesNotMatchRegularExpression('/<[a-z]+[^>]*\son[a-z]+=/i', $html);
    }

    public function test_the_resume_header_summary_is_sanitized(): void
    {
        $html = $this->blade('<x-resume.header name="N" title="T" :summary="$summary" />', ['summary' => self::HOSTILE])->__toString();

        $this->assertSafe($html);
    }

    public function test_the_resume_skills_titles_and_lists_are_sanitized(): void
    {
        $skills = [
            'top' => [['title' => self::HOSTILE, 'list' => [self::HOSTILE, 'PHP']]],
            'other' => [['title' => self::HOSTILE, 'list' => [self::HOSTILE]]],
        ];

        $html = $this->blade('<x-resume.skills :skills="$skills" />', ['skills' => $skills])->__toString();

        $this->assertSafe($html);
        $this->assertStringContainsString(' &middot; PHP', $html);
    }

    public function test_the_homepage_about_summary_is_sanitized(): void
    {
        $aboutMe = [
            'address' => [], 'telegram' => null, 'social' => [],
            'email' => ['email_address' => 'a@example.com', 'subject' => 's', 'body' => 'b'],
            'sections' => [],
        ];

        $html = $this->blade(
            '<x-about :about-me="$aboutMe" title="T" :summary="$summary" />',
            ['aboutMe' => $aboutMe, 'summary' => self::HOSTILE],
        )->__toString();

        $this->assertSafe($html, checkAnchors: false);
    }
}
