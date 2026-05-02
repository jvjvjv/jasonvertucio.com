import { createTheme } from '@mui/material/styles';

export const theme = createTheme({
    palette: {
        primary: {
            main: '#1b587c',
        },
        secondary: {
            main: '#b35e06',
        },
        text: {
            primary: '#25292c',
        },
        background: {
            default: '#f9fafb',
        },
    },
    typography: {
        fontFamily: '"Montserrat", "Arial", sans-serif',
        h1: { fontFamily: '"Josefin Sans", "Impact", "Arial", sans-serif' },
        h2: { fontFamily: '"Josefin Sans", "Impact", "Arial", sans-serif' },
        h3: { fontFamily: '"Josefin Sans", "Impact", "Arial", sans-serif' },
        h4: { fontFamily: '"Josefin Sans", "Impact", "Arial", sans-serif' },
    },
    components: {
        MuiButton: {
            styleOverrides: {
                root: {
                    textTransform: 'none',
                    borderRadius: '0.5rem',
                },
            },
        },
        MuiCard: {
            styleOverrides: {
                root: {
                    boxShadow: 'none',
                    border: '1px solid #e5e7eb',
                },
            },
        },
        MuiLink: {
            styleOverrides: {
                root: ({theme}) => ({
                    color: theme.palette.primary.main,
                })
            }
        },
        MuiPaper: {
            styleOverrides: {
                root: {
                    boxShadow: 'none',
                },
            },
        },
    },
});
