import { useState } from 'react';
import { Head, Link as InertiaLink } from "@inertiajs/react";
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Fab from '@mui/material/Fab';
import Link from "@mui/material/Link";
import Menu from '@mui/material/Menu';
import MenuItem from '@mui/material/MenuItem';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';
import Typography from '@mui/material/Typography';
import DownloadIcon from '@mui/icons-material/Download';
import DescriptionIcon from '@mui/icons-material/Description';
import PictureAsPdfIcon from '@mui/icons-material/PictureAsPdf';
import AdminLayout from '../../layouts/AdminLayout';
import { letterMarkdownSx } from '../../utils/markdownSx';

interface PersonalInfo {
    name: string;
    title: string;
    email: string;
    phone: string;
}

interface CoverLetter {
    id: number;
    date: string;
    company_address: string | null;
    greeting: string;
    closing: string | null;
    signature: string | null;
    company_name: string;
}

interface PreviewProps {
    personal: PersonalInfo;
    coverLetter: CoverLetter;
    messageBodyHtml: string;
    docxExists: boolean;
    pdfExists: boolean;
}

export default function Preview({ personal, coverLetter, messageBodyHtml, docxExists, pdfExists }: PreviewProps) {
    const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null);
    const hasDownloads = docxExists || pdfExists;

    const formattedDate = new Date(coverLetter.date + 'T00:00:00').toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });

    return (
        <AdminLayout>
            <Head title={`${coverLetter.company_name} Preview | Cover Letters`} />

            <Box sx={{ display: "flex", alignItems: "center", gap: 1, mb: 3 }}>
                <Link
                    component={InertiaLink}
                    href="/admin/cover-letters"
                    underline="hover"
                    sx={{ fontSize: "0.875rem" }}
                >
                    Cover Letters
                </Link>
                <Typography color="text.disabled">/</Typography>
                <Link
                    component={InertiaLink}
                    href={`/admin/cover-letters/${coverLetter.id}`}
                    underline="hover"
                    sx={{ fontSize: "0.875rem" }}
                >
                    Edit
                </Link>
            </Box>

            {/* Header banner */}
            <Box
                sx={{
                    bgcolor: "primary.main",
                    color: "primary.contrastText",
                    px: 4,
                    py: 3,
                    borderTopLeftRadius: 1,
                    borderTopRightRadius: 1,
                }}
            >
                <Typography variant="h4" sx={{ fontWeight: 700 }}>
                    {personal.name}
                </Typography>
                <Typography>
                    {personal.title} | {personal.email} | {personal.phone}
                </Typography>
            </Box>

            {/* Letter body */}
            <Box
                sx={{
                    bgcolor: "background.paper",
                    border: 1,
                    borderColor: "divider",
                    borderTop: 0,
                    p: 5,
                    borderBottomLeftRadius: 1,
                    borderBottomRightRadius: 1,
                    fontFamily:
                        'Corbel, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
                    fontSize: "1rem",
                    lineHeight: 1.75,
                    color: "text.primary",
                    ...letterMarkdownSx,
                }}
            >
                <p>{formattedDate}</p>

                {coverLetter.company_address && (
                    <p style={{ whiteSpace: "pre-line" }}>
                        {coverLetter.company_address}
                    </p>
                )}

                <p>{coverLetter.greeting}</p>

                <div dangerouslySetInnerHTML={{ __html: messageBodyHtml }} />

                {coverLetter.closing && <p>{coverLetter.closing}</p>}

                {coverLetter.signature && (
                    <p>
                        <strong>{coverLetter.signature}</strong>
                    </p>
                )}
            </Box>

            {/* Download FAB */}
            {hasDownloads && (
                <>
                    <Fab
                        color="primary"
                        aria-label="Download cover letter"
                        onClick={(e) => setAnchorEl(e.currentTarget)}
                        sx={{ position: "fixed", bottom: 24, right: 24 }}
                    >
                        <DownloadIcon />
                    </Fab>
                    <Menu
                        anchorEl={anchorEl}
                        open={Boolean(anchorEl)}
                        onClose={() => setAnchorEl(null)}
                        anchorOrigin={{ vertical: "top", horizontal: "center" }}
                        transformOrigin={{
                            vertical: "bottom",
                            horizontal: "center",
                        }}
                    >
                        {docxExists && (
                            <MenuItem
                                component="a"
                                href={`/admin/cover-letters/${coverLetter.id}/download/docx`}
                                onClick={() => setAnchorEl(null)}
                            >
                                <ListItemIcon>
                                    <DescriptionIcon color="primary" />
                                </ListItemIcon>
                                <ListItemText>Word Document</ListItemText>
                            </MenuItem>
                        )}
                        {pdfExists && (
                            <MenuItem
                                component="a"
                                href={`/admin/cover-letters/${coverLetter.id}/download/pdf`}
                                onClick={() => setAnchorEl(null)}
                            >
                                <ListItemIcon>
                                    <PictureAsPdfIcon color="error" />
                                </ListItemIcon>
                                <ListItemText>PDF</ListItemText>
                            </MenuItem>
                        )}
                    </Menu>
                </>
            )}
        </AdminLayout>
    );
}
