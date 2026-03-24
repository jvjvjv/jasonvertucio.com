import type { SxProps, Theme } from '@mui/material/styles';

const baseMarkdownSx = {
    '& ul, & ol': { pl: '1.5em' },
    '& ul': { listStyleType: 'disc' },
    '& ol': { listStyleType: 'decimal' },
    '& li': { mb: '0.25em' },
    '& strong, & b': { fontWeight: 700 },
    '& em, & i': { fontStyle: 'italic' },
    '& a': { color: '#4351a0', textDecoration: 'underline' },
} as const;

export const markdownSx: SxProps<Theme> = {
    ...baseMarkdownSx,
    fontSize: '0.875rem',
    '& p': { mb: '0.75em', '&:last-child': { mb: 0 } },
    '& ul, & ol': { ...baseMarkdownSx['& ul, & ol'], mb: '0.75em' },
    '& blockquote': { borderLeft: '3px solid #d1d5db', pl: '1em', color: '#6b7280', mb: '0.75em' },
    '& code': { bgcolor: 'rgba(0,0,0,0.06)', px: '0.3em', py: '0.1em', borderRadius: '3px', fontSize: '0.85em' },
    '& pre': { bgcolor: 'rgba(0,0,0,0.06)', p: '0.75em', borderRadius: '4px', overflow: 'auto', mb: '0.75em', '& code': { bgcolor: 'transparent', p: 0 } },
    '& hr': { border: 'none', borderTop: '1px solid #e5e7eb', my: '1em' },
};

export const letterMarkdownSx: SxProps<Theme> = {
    ...baseMarkdownSx,
    '& p': { mb: '1em' },
    '& ul, & ol': { ...baseMarkdownSx['& ul, & ol'], mb: '1em' },
    '& blockquote': { borderLeft: '3px solid #d1d5db', pl: '1em', color: '#6b7280', mb: '1em' },
    '& hr': { border: 'none', borderTop: '1px solid #e5e7eb', my: '1.5em' },
};
