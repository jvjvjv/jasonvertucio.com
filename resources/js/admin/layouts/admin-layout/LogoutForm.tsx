import Button from "@mui/material/Button";
import MenuItem from "@mui/material/MenuItem";

interface LogoutFormProps {
    variant: "bar" | "menu";
}

/**
 * Logging out is a POST carrying the forgery token, submitted as an ordinary
 * form so the browser follows the redirect to the public site. A link would
 * make a GET, which must not end a session.
 */
export default function LogoutForm({ variant }: LogoutFormProps) {
    const token =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content") ?? "";

    return (
        <form method="post" action="/logout" style={{ display: "contents" }}>
            <input type="hidden" name="_token" value={token} />
            {variant === "bar" ? (
                <Button type="submit" color="inherit">
                    Log out
                </Button>
            ) : (
                <MenuItem
                    component="button"
                    type="submit"
                    sx={{ width: "100%" }}
                >
                    Log out
                </MenuItem>
            )}
        </form>
    );
}
