import ButtonBase from "@mui/material/ButtonBase";

import { navRowSx } from "./navRowSx";

/**
 * Logging out is a POST carrying the forgery token, submitted as an ordinary
 * form so the browser follows the redirect to the public site. A link would
 * make a GET, which must not end a session.
 */
export default function LogoutForm() {
    const token =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content") ?? "";

    return (
        <form method="post" action="/logout" style={{ display: "contents" }}>
            <input type="hidden" name="_token" value={token} />
            <ButtonBase type="submit" sx={navRowSx({ level: "area" })}>
                Log out
            </ButtonBase>
        </form>
    );
}
