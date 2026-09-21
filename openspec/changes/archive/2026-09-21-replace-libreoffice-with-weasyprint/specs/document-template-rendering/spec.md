## REMOVED Requirements

### Requirement: PDF generation continues from the generated DOCX

**Reason**: PDFs are no longer produced by converting the generated DOCX. Each document's PDF is now rendered from the same body content and the same template styling its DOCX is composed from, which removes both the conversion step and the DOCX's role as a precondition. Keeping this requirement here would also split PDF behavior across two capabilities, since the new capability specifies it in full.

**Migration**: PDF behavior is specified by the `document-pdf-rendering` capability. Its requirement *"A document's PDF is rendered from its own body source"* replaces this one — including the guarantee that the PDF reflects the same content as the DOCX, which it now states directly rather than deriving from conversion. The scenario *"PDF requested with no DOCX present"* is inverted there: the request succeeds instead of failing, so any caller relying on the `DOCX file not found. Generate DOCX first.` error must drop that branch. This capability continues to govern the shared template, placeholder substitution and DOCX body composition, unchanged.
