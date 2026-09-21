<?php

namespace Tests\Unit\Services\Resume;

use App\Services\Resume\InlineImageBuilder;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

class InlineImageBuilderTest extends TestCase
{
    protected const NAMESPACE_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    protected const NAMESPACE_WP = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';

    protected const NAMESPACE_A = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    protected const NAMESPACE_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    protected InlineImageBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new InlineImageBuilder;
    }

    public function test_paragraph_is_well_formed_xml(): void
    {
        $dom = $this->parse($this->builder->paragraph('rId12', 877_000, 1_828_800, 'Signature'));

        $this->assertInstanceOf(DOMDocument::class, $dom);
    }

    public function test_drawing_carries_the_requested_dimensions(): void
    {
        $dom = $this->parse($this->builder->paragraph('rId12', 877_000, 1_828_800, 'Signature'));
        $xpath = $this->xpath($dom);

        $extent = $xpath->query('//wp:extent')->item(0);

        $this->assertSame('877000', $extent->getAttribute('cx'));
        $this->assertSame('1828800', $extent->getAttribute('cy'));

        $ext = $xpath->query('//a:xfrm/a:ext')->item(0);
        $this->assertSame('877000', $ext->getAttribute('cx'));
        $this->assertSame('1828800', $ext->getAttribute('cy'));
    }

    public function test_drawing_references_the_supplied_relationship_id(): void
    {
        $dom = $this->parse($this->builder->paragraph('rId42', 100, 200));
        $xpath = $this->xpath($dom);

        $blip = $xpath->query('//a:blip')->item(0);

        $this->assertSame('rId42', $blip->getAttributeNS(self::NAMESPACE_R, 'embed'));
    }

    public function test_paragraph_applies_an_optional_style(): void
    {
        $dom = $this->parse($this->builder->paragraph('rId12', 100, 200, 'Signature', 'Normal'));
        $xpath = $this->xpath($dom);

        $style = $xpath->query('//w:pPr/w:pStyle')->item(0);

        $this->assertNotNull($style);
        $this->assertSame('Normal', $style->getAttributeNS(self::NAMESPACE_W, 'val'));
    }

    public function test_paragraph_omits_pPr_when_no_style_is_given(): void
    {
        $dom = $this->parse($this->builder->paragraph('rId12', 100, 200));
        $xpath = $this->xpath($dom);

        $this->assertSame(0, $xpath->query('//w:pPr')->length);
    }

    public function test_image_name_is_xml_escaped(): void
    {
        $dom = $this->parse($this->builder->paragraph('rId12', 100, 200, 'Jason\'s "signature" & mark'));

        $this->assertInstanceOf(DOMDocument::class, $dom, 'A name with quotes and ampersands must not break the XML');
    }

    public function test_floating_run_takes_no_space_in_the_text_flow(): void
    {
        $dom = $this->parse($this->builder->floatingRun('rId12', 877_000, 1_828_800, -228600, 91440, 'Signature'));
        $xpath = $this->xpath($dom);

        // wrapNone is what keeps the image out of the flow; allowOverlap is
        // what lets it sit on top of the text rather than displacing it.
        $this->assertSame(1, $xpath->query('//wp:anchor/wp:wrapNone')->length);
        $this->assertSame('1', $xpath->query('//wp:anchor')->item(0)->getAttribute('allowOverlap'));
        $this->assertSame(0, $xpath->query('//wp:inline')->length, 'A floating run must not be inline');
    }

    public function test_floating_run_carries_its_offsets_and_dimensions(): void
    {
        $dom = $this->parse($this->builder->floatingRun('rId12', 877_000, 1_828_800, -228600, 91440));
        $xpath = $this->xpath($dom);

        $this->assertSame('-228600', $xpath->query('//wp:positionV/wp:posOffset')->item(0)->textContent);
        $this->assertSame('91440', $xpath->query('//wp:positionH/wp:posOffset')->item(0)->textContent);

        $extent = $xpath->query('//wp:extent')->item(0);
        $this->assertSame('877000', $extent->getAttribute('cx'));
        $this->assertSame('1828800', $extent->getAttribute('cy'));
    }

    public function test_floating_run_references_the_supplied_relationship_id(): void
    {
        $dom = $this->parse($this->builder->floatingRun('rId42', 100, 200));
        $xpath = $this->xpath($dom);

        $this->assertSame('rId42', $xpath->query('//a:blip')->item(0)->getAttributeNS(self::NAMESPACE_R, 'embed'));
    }

    protected function parse(string $fragment): DOMDocument
    {
        $wrapped = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<w:body xmlns:w="'.self::NAMESPACE_W.'"'
            .' xmlns:wp="'.self::NAMESPACE_WP.'"'
            .' xmlns:a="'.self::NAMESPACE_A.'"'
            .' xmlns:r="'.self::NAMESPACE_R.'">'
            .$fragment
            .'</w:body>';

        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($wrapped), 'Fragment must be well-formed XML');

        return $dom;
    }

    protected function xpath(DOMDocument $dom): DOMXPath
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::NAMESPACE_W);
        $xpath->registerNamespace('wp', self::NAMESPACE_WP);
        $xpath->registerNamespace('a', self::NAMESPACE_A);
        $xpath->registerNamespace('r', self::NAMESPACE_R);

        return $xpath;
    }
}
