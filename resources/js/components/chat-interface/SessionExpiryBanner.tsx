import Alert from "@mui/material/Alert";
import Button from "@mui/material/Button";

/** Shown once the session-expiry deadline (from `useSessionExpiry`) has passed. */
export default function SessionExpiryBanner() {
    return (
        <Alert
            severity="warning"
            sx={{ mx: { xs: 1.5, md: 3 }, mt: 1.5 }}
            action={
                <Button
                    color="inherit"
                    size="small"
                    onClick={() => {
                        window.location.reload();
                    }}
                >
                    Refresh
                </Button>
            }
        >
            Your session has expired. Refresh the page to continue.
        </Alert>
    );
}
