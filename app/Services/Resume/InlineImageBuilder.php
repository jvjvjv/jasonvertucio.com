<?php

namespace App\Services\Resume;

/**
 * Builds the OOXML for an inline image run.
 *
 * Kept separate from DocumentRenderer because the body composers decide where
 * an image goes, while the renderer only knows how to get its bytes into the
 * package and hand back a relationship id.
 */
class InlineImageBuilder
{
    /**
     * Wrap an inline image in its own paragraph.
     *
     * $keepNext binds the paragraph to the one after it, so an image at the
     * end of a block is not split onto a page of its own.
     */
    public function paragraph(
        string $relationshipId,
        int $cx,
        int $cy,
        string $name = 'Image',
        ?string $styleId = null,
        bool $keepNext = false,
    ): string {
        $properties = '';

        if ($styleId !== null) {
            $properties .= '<w:pStyle w:val="'.$this->escape($styleId).'"/>';
        }

        if ($keepNext) {
            $properties .= '<w:keepNext/>';
        }

        $pPr = $properties === '' ? '' : '<w:pPr>'.$properties.'</w:pPr>';

        return '<w:p>'.$pPr.$this->run($relationshipId, $cx, $cy, $name).'</w:p>';
    }

    /**
     * Build a run containing a floating drawing that overlaps surrounding text.
     *
     * `wrapNone` plus `allowOverlap` means the image contributes nothing to the
     * text flow and may sit on top of whatever is already there — the way ink
     * crosses printed text on a signed page. The caller reserves however much
     * vertical space it wants the image to appear to occupy; the rest of the
     * image simply overlaps.
     *
     * @param  int  $offsetV  EMU relative to the anchoring paragraph. Negative lifts the image above it.
     * @param  int  $offsetH  EMU relative to the column's left edge.
     */
    public function floatingRun(
        string $relationshipId,
        int $cx,
        int $cy,
        int $offsetV = 0,
        int $offsetH = 0,
        string $name = 'Image',
    ): string {
        $escapedName = $this->escape($name);

        return '<w:r><w:drawing>'
            .'<wp:anchor distT="0" distB="0" distL="0" distR="0" simplePos="0"'
            .' relativeHeight="251658240" behindDoc="0" locked="0" layoutInCell="1" allowOverlap="1">'
            .'<wp:simplePos x="0" y="0"/>'
            .'<wp:positionH relativeFrom="column"><wp:posOffset>'.$offsetH.'</wp:posOffset></wp:positionH>'
            .'<wp:positionV relativeFrom="paragraph"><wp:posOffset>'.$offsetV.'</wp:posOffset></wp:positionV>'
            .'<wp:extent cx="'.$cx.'" cy="'.$cy.'"/>'
            .'<wp:effectExtent l="0" t="0" r="0" b="0"/>'
            .'<wp:wrapNone/>'
            .'<wp:docPr id="1" name="'.$escapedName.'" descr="'.$escapedName.'"/>'
            .'<wp:cNvGraphicFramePr>'
            .'<a:graphicFrameLocks xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" noChangeAspect="1"/>'
            .'</wp:cNvGraphicFramePr>'
            .$this->graphic($relationshipId, $cx, $cy, $escapedName)
            .'</wp:anchor>'
            .'</w:drawing></w:r>';
    }

    /**
     * Build a single run containing an inline drawing.
     */
    public function run(string $relationshipId, int $cx, int $cy, string $name = 'Image'): string
    {
        $escapedName = $this->escape($name);
        $escapedRelationshipId = $this->escape($relationshipId);

        return '<w:r><w:drawing>'
            .'<wp:inline distT="0" distB="0" distL="0" distR="0">'
            .'<wp:extent cx="'.$cx.'" cy="'.$cy.'"/>'
            .'<wp:effectExtent l="0" t="0" r="0" b="0"/>'
            .'<wp:docPr id="1" name="'.$escapedName.'" descr="'.$escapedName.'"/>'
            .'<wp:cNvGraphicFramePr>'
            .'<a:graphicFrameLocks xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" noChangeAspect="1"/>'
            .'</wp:cNvGraphicFramePr>'
            .$this->graphic($escapedRelationshipId, $cx, $cy, $escapedName)
            .'</wp:inline>'
            .'</w:drawing></w:r>';
    }

    /**
     * The picture payload shared by the inline and floating forms.
     */
    protected function graphic(string $relationshipId, int $cx, int $cy, string $escapedName): string
    {
        $escapedRelationshipId = $this->escape($relationshipId);

        return '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            .'<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:nvPicPr>'
            .'<pic:cNvPr id="0" name="'.$escapedName.'" descr="'.$escapedName.'"/>'
            .'<pic:cNvPicPr><a:picLocks noChangeAspect="1" noChangeArrowheads="1"/></pic:cNvPicPr>'
            .'</pic:nvPicPr>'
            .'<pic:blipFill>'
            .'<a:blip r:embed="'.$escapedRelationshipId.'"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/>'
            .'<a:srcRect/>'
            .'<a:stretch><a:fillRect/></a:stretch>'
            .'</pic:blipFill>'
            .'<pic:spPr bwMode="auto">'
            .'<a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm>'
            .'<a:prstGeom prst="rect"><a:avLst/></a:prstGeom>'
            .'<a:noFill/>'
            .'</pic:spPr>'
            .'</pic:pic>'
            .'</a:graphicData>'
            .'</a:graphic>';
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
