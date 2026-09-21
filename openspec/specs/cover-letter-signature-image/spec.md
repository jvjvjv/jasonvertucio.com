# cover-letter-signature-image Specification

## Purpose
Places a handwritten signature image at the end of every generated cover letter, sized and colored to match the site's brand, so a cover letter reads as signed without a manual step after generation.

## Requirements

### Requirement: Cover letters end with an embedded signature image

The system SHALL embed the signature image at the end of every generated cover letter, positioned after the closing and before or in place of the typed signature name. The image SHALL be embedded in the document file itself, not linked to an external path.

#### Scenario: Signature appears in a generated cover letter

- **WHEN** a cover letter DOCX is generated
- **THEN** the document contains the signature image after the closing line
- **AND** the image data is stored inside the document file, so the document renders correctly on a machine that does not have the source image

#### Scenario: Signature survives PDF conversion

- **WHEN** a generated cover letter is converted to PDF
- **THEN** the signature image appears in the PDF at the same position and size, including its overlap of the surrounding lines

### Requirement: Signature appears on cover letters only

The signature image SHALL NOT appear in the main resume or in any targeted resume, even though all three document types render from the same shared template.

#### Scenario: Resume documents carry no signature

- **WHEN** the main resume or a targeted resume is generated
- **THEN** the output contains no signature image

### Requirement: Signature is sized to two inches tall, proportionally

The signature image SHALL be rendered at exactly 2 inches tall — close to the size of a real hand-written signature — with its width scaled to preserve the source image's aspect ratio. The sizing SHALL be derived from the source image's actual dimensions rather than hardcoded, so replacing the signature file with one of a different shape yields a correctly proportioned result.

#### Scenario: Current signature is sized from its own dimensions

- **WHEN** a cover letter is generated with the current 199×415 pixel signature source
- **THEN** the embedded image is 2 inches tall and approximately 0.96 inches wide
- **AND** the image is not distorted

#### Scenario: Signature source is replaced with a different shape

- **WHEN** the signature source file is replaced with an image of a different aspect ratio
- **AND** a cover letter is generated
- **THEN** the embedded image is still 2 inches tall, with width recomputed from the new source's proportions

### Requirement: The signature overlaps the text rather than displacing it

The signature SHALL be anchored to the typed-name paragraph and positioned above it, so that it does not occupy its full height in the text flow. The system SHALL reserve less vertical space than the image's own height and allow the image to overlap the closing above it and the typed name below it, the way a pen crosses text already printed on a page. This keeps the sign-off's cost to the page a few short lines rather than the signature's full height, so it fits in the space left by a message body of any length, and it keeps the signature with the name wherever the name lands.

#### Scenario: Reserved space is smaller than the signature

- **WHEN** a cover letter is generated with a signature
- **THEN** the vertical space reserved for it in the text flow is less than the signature's own height
- **AND** the signature is drawn over the surrounding lines rather than pushing them apart

#### Scenario: Signature stays with the typed name

- **WHEN** the sign-off moves to a different position on the page, or to a following page
- **THEN** the signature moves with the typed name, keeping the same position relative to it

#### Scenario: Signature renders fully at the foot of a page

- **WHEN** the sign-off falls at the very bottom of a page
- **THEN** the whole signature still renders, including any strokes that descend past the typed name
- **AND** it is not clipped by the page edge

### Requirement: The sign-off block is never split across pages

The final paragraph of the message body, the closing, the signature image and the typed signature name SHALL be kept together on one page. A page break MAY fall before that group but SHALL NOT fall inside it, so the closing is never stranded at the foot of one page with the signature alone on the next, and a page that carries the sign-off always carries some body prose with it.

#### Scenario: Sign-off does not fit in the remaining space

- **WHEN** a cover letter is generated whose body leaves too little room on the page for the closing, the signature and the typed name
- **THEN** the whole sign-off block moves to the following page together
- **AND** no page break falls between the closing and the signature, or between the signature and the typed name

#### Scenario: The final body paragraph moves with the sign-off

- **WHEN** the sign-off block moves to a following page
- **THEN** the last paragraph of the message body moves with it
- **AND** that page therefore opens with body prose rather than a signature standing alone at the top

#### Scenario: Earlier body paragraphs still break freely

- **WHEN** a cover letter's message body is longer than one page
- **THEN** every body paragraph except the last page-breaks normally
- **AND** the letter does not drag its whole body onto the final page

### Requirement: Signature is keyed off its background and recolored to the brand blue

The committed signature source is an opaque image of dark strokes on a light background, with no alpha channel of its own. The system SHALL therefore derive transparency from pixel luminance before embedding: light background pixels become fully transparent, dark stroke pixels become fully opaque, and intermediate anti-aliasing pixels become proportionally translucent. Every pixel that remains at all visible SHALL be set to the brand blue `#1B587C`, whatever color it was in the source.

#### Scenario: Strokes render in brand blue

- **WHEN** a cover letter is generated
- **THEN** every visible pixel of the signature is the brand blue `#1B587C`, regardless of its color in the source
- **AND** strokes that were a different color in the source — not just the dominant one — are also brand blue

#### Scenario: Background is keyed out

- **WHEN** a cover letter is generated
- **THEN** the light background surrounding the signature strokes is fully transparent, so the signature sits on the page rather than on an opaque rectangle

#### Scenario: Edges stay smooth

- **WHEN** a cover letter is generated
- **THEN** pixels that were partway between background and stroke in the source are partially transparent in proportion to their darkness
- **AND** stroke edges therefore remain smooth rather than showing a hard jagged outline

#### Scenario: A source that already has transparency

- **WHEN** the signature source is replaced with an image that already has an alpha channel
- **AND** a cover letter is generated
- **THEN** pixels the source marks as transparent remain transparent, rather than being made opaque by the luminance key

### Requirement: A failed signature does not fail the cover letter

If the signature source is missing, unreadable, or cannot be recolored, the system SHALL still generate the cover letter without the signature image and SHALL record the reason, rather than failing the whole generation.

#### Scenario: Signature source is missing

- **WHEN** a cover letter is generated and the signature source file does not exist
- **THEN** the cover letter is generated successfully without the signature image
- **AND** the omission and its reason are recorded in the application log

#### Scenario: Signature source cannot be processed

- **WHEN** a cover letter is generated and the signature source cannot be read or recolored
- **THEN** the cover letter is generated successfully without the signature image
- **AND** the failure reason is recorded in the application log
