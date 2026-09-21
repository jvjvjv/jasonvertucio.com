<?php

namespace Tests\Unit\Services\Resume;

use App\Models\CoverLetter;
use App\Services\Resume\CoverLetterBodyComposer;
use App\Services\Resume\InlineImageBuilder;
use App\Services\Resume\MarkdownToOpenXmlConverter;
use DOMDocument;
use Tests\TestCase;

class CoverLetterBodyComposerTest extends TestCase
{
    protected CoverLetterBodyComposer $composer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->composer = new CoverLetterBodyComposer(
            new MarkdownToOpenXmlConverter,
            new InlineImageBuilder,
        );
    }

    public function test_blocks_are_emitted_in_letter_order(): void
    {
        $fragment = $this->composer->compose(
            $this->coverLetter(),
            ['relationshipId' => 'rId12', 'cx' => 877_000, 'cy' => 1_828_800],
        );

        $this->assertBlocksInOrder($fragment, [
            'March 14, 2026',
            'Acme Corp',
            '123 Main St',
            'Dear Hiring Manager,',
            'I am writing about the Engineer role.',
            'Sincerely,',
            '<w:drawing>',
            'Jason Vertucio',
        ]);
    }

    public function test_fragment_is_well_formed_xml(): void
    {
        $fragment = $this->composer->compose(
            $this->coverLetter(['company_address' => "Acme & Co.\n<Suite 5>"]),
            ['relationshipId' => 'rId12', 'cx' => 100, 'cy' => 200],
        );

        $wrapped = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<w:body xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
            .' xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"'
            .' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .$fragment
            .'</w:body>';

        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($wrapped));
    }

    public function test_address_renders_one_paragraph_per_line(): void
    {
        $fragment = $this->composer->compose(
            $this->coverLetter(['company_address' => "Acme Corp\n123 Main St\nPhiladelphia, PA"])
        );

        foreach (['Acme Corp', '123 Main St', 'Philadelphia, PA'] as $line) {
            $this->assertStringContainsString($line, $fragment);
        }
    }

    public function test_blank_address_lines_are_dropped(): void
    {
        $fragment = $this->composer->compose(
            $this->coverLetter(['company_address' => "Acme Corp\n\n\n123 Main St"])
        );

        $this->assertStringContainsString('Acme Corp', $fragment);
        $this->assertStringContainsString('123 Main St', $fragment);
        $this->assertStringNotContainsString('<w:t xml:space="preserve"></w:t>', $fragment);
    }

    public function test_message_body_markdown_is_converted(): void
    {
        $fragment = $this->composer->compose(
            $this->coverLetter(['message_body' => "Opening line.\n\n- First point\n- Second point"])
        );

        $this->assertStringContainsString('w:val="ListParagraph"', $fragment);
        $this->assertStringContainsString('First point', $fragment);
        $this->assertStringContainsString('Second point', $fragment);
    }

    public function test_no_drawing_is_emitted_without_a_signature_image(): void
    {
        $fragment = $this->composer->compose($this->coverLetter());

        $this->assertStringNotContainsString('<w:drawing>', $fragment);
        $this->assertStringContainsString('Jason Vertucio', $fragment, 'The typed signature is still emitted');
    }

    public function test_signature_drawing_carries_the_supplied_dimensions(): void
    {
        $fragment = $this->composer->compose(
            $this->coverLetter(),
            ['relationshipId' => 'rId12', 'cx' => 877_000, 'cy' => 1_828_800],
        );

        $this->assertStringContainsString('cx="877000"', $fragment);
        $this->assertStringContainsString('cy="1828800"', $fragment);
        $this->assertStringContainsString('r:embed="rId12"', $fragment);
    }

    public function test_message_body_paragraphs_get_letter_spacing(): void
    {
        $fragment = $this->composer->compose(
            $this->coverLetter(['message_body' => "First paragraph.\n\nSecond paragraph."])
        );

        $this->assertSame(
            2,
            substr_count($fragment, '<w:spacing w:after="160"/>'),
            'Each message-body paragraph needs spacing; the shared template supplies none'
        );
    }

    public function test_message_body_list_items_stay_tight(): void
    {
        $fragment = $this->composer->compose(
            $this->coverLetter(['message_body' => "- First point\n- Second point"])
        );

        $this->assertStringNotContainsString('<w:spacing w:after="160"/>', $fragment);
        $this->assertStringContainsString('w:val="ListParagraph"', $fragment);
    }

    public function test_sign_off_block_is_held_together_across_pages(): void
    {
        $fragment = $this->composer->compose(
            $this->coverLetter(),
            ['relationshipId' => 'rId12', 'cx' => 657_948, 'cy' => 1_371_600],
        );

        // The closing and the signature image each bind to what follows, so a
        // page break can only fall before the block, never inside it.
        // Final body paragraph, the blank line before the closing, the closing
        // itself, and the signing space all bind forward; the typed name is
        // last so it binds to nothing.
        $this->assertSame(
            4,
            substr_count($fragment, '<w:keepNext/>'),
            'Every paragraph of the sign-off except the typed name binds forward'
        );

        $closingAt = strpos($fragment, 'Sincerely,');
        $keepNextBeforeClosing = strrpos(substr($fragment, 0, $closingAt), '<w:keepNext/>');
        $this->assertNotFalse($keepNextBeforeClosing, 'The closing paragraph carries keepNext');
    }

    public function test_sign_off_still_binds_when_there_is_no_signature_image(): void
    {
        $fragment = $this->composer->compose($this->coverLetter());

        $this->assertSame(
            4,
            substr_count($fragment, '<w:keepNext/>'),
            'The sign-off binds the same way with or without a signature image'
        );
    }

    public function test_only_the_final_body_paragraph_binds_forward(): void
    {
        $fragment = $this->composer->compose(
            $this->coverLetter(['message_body' => "First paragraph.\n\nSecond paragraph.\n\nThird paragraph."])
        );

        // Earlier body paragraphs must page-break freely, so the letter does
        // not drag its whole body onto the last page.
        $secondAt = strpos($fragment, 'Second paragraph.');
        $firstParagraphBlock = substr($fragment, 0, strrpos(substr($fragment, 0, $secondAt), '<w:p '));

        $this->assertStringNotContainsString(
            '<w:keepNext/>',
            $firstParagraphBlock,
            'Only the last body paragraph binds forward'
        );

        // The final paragraph does bind, so the last page opens with prose
        // rather than a signature sitting alone.
        $thirdAt = strpos($fragment, 'Third paragraph.');
        $thirdParagraphAt = strrpos(substr($fragment, 0, $thirdAt), '<w:p ');
        $closingAt = strpos($fragment, 'Sincerely,');

        $this->assertStringContainsString(
            '<w:keepNext/>',
            substr($fragment, $thirdParagraphAt, $closingAt - $thirdParagraphAt),
            'The final body paragraph carries keepNext'
        );
    }

    public function test_keep_next_precedes_spacing_in_paragraph_properties(): void
    {
        $fragment = $this->composer->compose(
            $this->coverLetter(['message_body' => 'Only paragraph.'])
        );

        // OOXML fixes pPr child order: keepNext before spacing.
        $this->assertMatchesRegularExpression(
            '/<w:pPr>(?:(?!<\/w:pPr>).)*<w:keepNext\/>(?:(?!<\/w:pPr>).)*<w:spacing\b/s',
            $fragment,
            'keepNext must precede spacing inside pPr'
        );
    }

    public function test_signature_reserves_less_space_than_its_own_height(): void
    {
        $fragment = $this->composer->compose(
            $this->coverLetter(),
            ['relationshipId' => 'rId12', 'cx' => 877_000, 'cy' => 1_828_800],
        );

        // The gap paragraph pins an exact line height. It is deliberately
        // shorter than the image, which is what lets the signature overlap the
        // closing above and the typed name below instead of clearing them.
        $this->assertMatchesRegularExpression(
            '/<w:spacing w:line="(\d+)" w:lineRule="exact"/',
            $fragment
        );

        preg_match('/<w:spacing w:line="(\d+)" w:lineRule="exact"/', $fragment, $m);
        $reservedEmu = ((int) $m[1]) / 20 * 12700;   // twentieths of a point -> EMU

        $this->assertLessThan(
            1_828_800,
            $reservedEmu,
            'Reserved space must be less than the signature height, so it overlaps'
        );
    }

    public function test_signature_floats_rather_than_sitting_in_the_flow(): void
    {
        $fragment = $this->composer->compose(
            $this->coverLetter(),
            ['relationshipId' => 'rId12', 'cx' => 877_000, 'cy' => 1_828_800],
        );

        $this->assertStringContainsString('<wp:wrapNone/>', $fragment);
        $this->assertStringNotContainsString('<wp:inline', $fragment);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
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
            $this->assertNotFalse($position, "Fragment should contain '{$needle}'");
            $this->assertGreaterThan($previous, $position, "'{$needle}' is out of order");
            $previous = $position;
        }
    }
}
