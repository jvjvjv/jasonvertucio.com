<?php

namespace Tests\Unit\Services\Resume;

use App\Services\Resume\StylesheetTranslator;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class StylesheetTranslatorTest extends TestCase
{
    protected string $templatePath;

    protected StylesheetTranslator $translator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->templatePath = dirname(__DIR__, 4).'/resources/resume/2026 template.docx';

        if (! file_exists($this->templatePath)) {
            $this->markTestSkipped('Shared template not found: '.$this->templatePath);
        }

        config(['resume.template' => $this->templatePath]);
        $this->translator = new StylesheetTranslator;
    }

    public function test_font_size_converts_half_points_to_points(): void
    {
        $css = $this->translator->css();

        $this->assertStringContainsString('font-size:13pt', $this->ruleFor($css, 'Heading1'));
    }

    public function test_spacing_converts_twentieths_to_points(): void
    {
        $css = $this->translator->css();

        $this->assertStringContainsString('margin-top:12pt', $this->ruleFor($css, 'Heading1'));
    }

    public function test_page_margin_converts_twips_to_inches(): void
    {
        $css = $this->translator->css();

        $this->assertMatchesRegularExpression('/@page\{size:8\.5in 11in;margin:0\.72in [^;]+;\}/', $css);
    }

    public function test_page_size_is_letter(): void
    {
        $css = $this->translator->css();

        $this->assertStringContainsString('size:8.5in 11in', $css);
    }

    public function test_company_info_is_italic_with_normal_weight_not_bold(): void
    {
        $css = $this->translator->css();
        $rule = $this->ruleFor($css, 'CompanyInfo');

        $this->assertStringContainsString('font-style:italic', $rule);
        $this->assertStringContainsString('font-weight:normal', $rule);
        $this->assertStringNotContainsString('font-weight:bold', $rule);
    }

    public function test_heading1_resolves_its_font_through_the_ancestor_chain_rather_than_being_unstyled(): void
    {
        $css = $this->translator->css();
        $rule = $this->ruleFor($css, 'Heading1');

        $this->assertStringContainsString('font-family:"Josefin Sans"', $rule);
    }

    public function test_title_folds_its_per_weight_pseudo_family_onto_the_real_face(): void
    {
        // The template's rFonts on Title is literally "Josefin Sans Bold" —
        // a per-weight pseudo-family, not a weight of "Josefin Sans". Emitted
        // verbatim, no @font-face declares it, and WeasyPrint silently falls
        // back to a substituted system font for the whole element.
        $css = $this->translator->css();
        $rule = $this->ruleFor($css, 'Title');

        $this->assertStringContainsString('font-family:"Josefin Sans"', $rule);
        $this->assertStringNotContainsString('Josefin Sans Bold', $rule);
    }

    public function test_heading2_inherits_font_family_from_heading1_without_redeclaring_it(): void
    {
        $css = $this->translator->css();
        $rule = $this->ruleFor($css, 'Heading2');

        $this->assertStringContainsString('font-family:"Josefin Sans"', $rule);
    }

    public function test_translation_is_cached_until_the_template_is_touched(): void
    {
        $tempPath = sys_get_temp_dir().'/stylesheet-translator-'.uniqid().'.docx';
        copy($this->templatePath, $tempPath);
        config(['resume.template' => $tempPath]);

        try {
            $first = $this->translator->css();

            // Corrupt the copy so a re-read would return different output,
            // proving the second call actually re-read rather than serving
            // the cached string from before the touch.
            file_put_contents($tempPath, 'not a docx');
            touch($tempPath, time() + 5);
            clearstatcache(true, $tempPath);

            $second = (new StylesheetTranslator)->css();

            $this->assertNotSame($first, $second);
            $this->assertSame('', $second);
        } finally {
            @unlink($tempPath);
        }
    }

    public function test_every_template_style_is_translated_or_known_ignorable(): void
    {
        $styleIds = $this->translator->styleIdsIn($this->templatePath);
        $this->assertNotEmpty($styleIds);

        foreach ($styleIds as $styleId) {
            $this->assertTrue(
                in_array($styleId, StylesheetTranslator::RECOGNIZED_STYLES, true)
                    || in_array($styleId, StylesheetTranslator::KNOWN_IGNORABLE_STYLES, true),
                "Style '{$styleId}' is neither translated nor declared known-ignorable."
            );
        }
    }

    public function test_unrecognized_style_does_not_fail_translation(): void
    {
        // The template's own style list already includes styles outside
        // RECOGNIZED_STYLES (e.g. Heading4); translation must not throw.
        $css = $this->translator->css();

        $this->assertNotSame('', $css);
    }

    protected function ruleFor(string $css, string $styleId): string
    {
        preg_match('/\.'.preg_quote($styleId, '/').'\{[^}]*\}/', $css, $matches);

        $this->assertNotEmpty($matches, "No CSS rule found for .{$styleId}");

        return $matches[0];
    }
}
