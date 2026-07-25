import Box from "@mui/material/Box";

/** Shown by `react-virtuoso` when the message list is empty. */
export default function EmptyPlaceholder() {
    return (
        <Box
            sx={{
                border: "1px dashed",
                borderColor: "divider",
                py: 3,
                px: 2,
                mx: 3,
                mt: 2.5,
                textAlign: "center",
                color: "text.secondary",
            }}
        >
            Send the first message to start the conversation.
        </Box>
    );
}
