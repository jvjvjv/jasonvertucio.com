<?php

namespace Tests\Unit\Services\Resume;

use App\Models\CoverLetter;
use App\Services\Resume\CoverLetterHtmlComposer;
use App\Services\Resume\MarkdownToHtmlConverter;
use App\Services\Resume\SignatureImageService;
use Tests\TestCase;

class CoverLetterHtmlComposerTest extends TestCase
{
    protected CoverLetterHtmlComposer $composer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->composer = new CoverLetterHtmlComposer(new MarkdownToHtmlConverter);
    }

    public function test_blocks_are_emitted_in_letter_order(): void
    {
        $html = $this->composer->compose(
            $this->coverLetter(),
            ['path' => '/tmp/signature.png', 'cx' => 877_000, 'cy' => 1_828_800],
        );

        $this->assertBlocksInOrder($html, [
            'March 14, 2026',
            'Acme Corp',
            '123 Main St',
            'Dear Hiring Manager,',
            'I am writing about the Engineer role.',
            'Sincerely,',
            '<img class="signature"',
            'Jason Vertucio',
        ]);
    }

    public function test_signature_css_uses_the_configured_rise_and_indent_converted_from_emu(): void
    {
        config(['resume.signature_rise' => -914400, 'resume.signature_indent' => 457200]);

        $html = $this->composer->compose(
            $this->coverLetter(),
            ['path' => '/tmp/signature.png', 'cx' => 877_000, 'cy' => 1_828_800],
        );

        $this->assertStringContainsString('top:-1in', $html);
        $this->assertStringContainsString('left:0.5in', $html);
    }

    public function test_signature_size_is_converted_from_the_ooxml_cx_cy(): void
    {
        $html = $this->composer->compose(
            $this->coverLetter(),
            ['path' => '/tmp/signature.png', 'cx' => SignatureImageService::EMU_PER_INCH, 'cy' => SignatureImageService::EMU_PER_INCH * 2],
        );

        $this->assertStringContainsString('width:1in', $html);
        $this->assertStringContainsString('height:2in', $html);
    }

    public function test_signature_is_positioned_out_of_the_text_flow(): void
    {
        $html = $this->composer->compose(
            $this->coverLetter(),
            ['path' => '/tmp/signature.png', 'cx' => 877_000, 'cy' => 1_828_800],
        );

        $this->assertMatchesRegularExpression('/<img class="signature" style="position:absolute;/', $html);
    }

    public function test_closing_signing_space_and_name_are_bound_in_one_block(): void
    {
        $html = $this->composer->compose(
            $this->coverLetter(),
            ['path' => '/tmp/signature.png', 'cx' => 877_000, 'cy' => 1_828_800],
        );

        $signOff = $this->between($html, '<div class="sign-off">', 'Jason Vertucio</p></div>');

        foreach (['Sincerely,', 'signing-space', 'Jason Vertucio'] as $needle) {
            $this->assertStringContainsString($needle, $signOff);
        }
    }

    public function test_letter_renders_without_a_signature_when_none_is_supplied(): void
    {
        $html = $this->composer->compose($this->coverLetter(), null);

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('Jason Vertucio', $html);
        $this->assertStringContainsString('Sincerely,', $html);
    }

    protected function coverLetter(array $attributes = []): CoverLetter
    {
        return new CoverLetter(array_merge([
            'company_name' => 'Acme Corp',
            'position' => 'Engineer',
            'date' => '2026-03-14',
            'company_address' => "Acme Corp\n123 Main St",
            'greeting' => 'Dear Hiring Manager,',
            'message_body' => 'I am writing about the Engineer role.',
            'closing' => 'Sincerely,',
            'signature' => 'Jason Vertucio',
        ], $attributes));
    }

    /**
     * @param  array<int, string>  $needles
     */
    protected function assertBlocksInOrder(string $haystack, array $needles): void
    {
        $previous = -1;

        foreach ($needles as $needle) {
            $position = strpos($haystack, $needle);
            $this->assertNotFalse($position, "Should contain '{$needle}'");
            $this->assertGreaterThan($previous, $position, "'{$needle}' is out of order");
            $previous = $position;
        }
    }

    protected function between(string $haystack, string $start, string $end): string
    {
        $startPos = strpos($haystack, $start);
        $this->assertNotFalse($startPos);
        $endPos = strpos($haystack, $end, $startPos);
        $this->assertNotFalse($endPos);

        return substr($haystack, $startPos, $endPos + strlen($end) - $startPos);
    }
}
