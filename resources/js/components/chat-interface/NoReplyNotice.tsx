import Alert from "@mui/material/Alert";

import { CHAT_COLUMN_MAX_WIDTH } from "./chatColumn";

/**
 * Shown when the transcript ends on a user message with nothing after it.
 *
 * A turn that is cut short before the model produces any text persists no
 * assistant message at all — not even the tool calls it had already made — so
 * the transcript comes back from the server looking as though the question was
 * never asked. Saying so beats rendering silence the reader has to interpret.
 */
export default function NoReplyNotice() {
    return (
        <Alert
            severity="warning"
            variant="outlined"
            sx={{
                maxWidth: CHAT_COLUMN_MAX_WIDTH,
                margin: "0 auto",
            }}
        >
            No reply was recorded for this message. The turn was interrupted
            before the model answered — send it again to retry.
        </Alert>
    );
}
