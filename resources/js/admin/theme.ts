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
        fontFamily: 'Corbel, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
        h1: { fontFamily: '"Convection Condensed", sans-serif' },
        h2: { fontFamily: '"Convection Condensed", sans-serif' },
        h3: { fontFamily: '"Convection Condensed", sans-serif' },
        h4: { fontFamily: '"Convection Condensed", sans-serif' },
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
        MuiPaper: {
            styleOverrides: {
                root: {
                    boxShadow: 'none',
                },
            },
        },
    },
});
