import WarningAmberIcon from "@mui/icons-material/WarningAmber";
import Box from "@mui/material/Box";
import Chip from "@mui/material/Chip";
import ListItemButton from "@mui/material/ListItemButton";
import ListItemText from "@mui/material/ListItemText";
import Typography from "@mui/material/Typography";

interface HistoryItem {
    handle: string;
    label: string;
    is_current: boolean;
    is_stale: boolean;
    updated_at: string;
    cost_usd: number | null;
}

interface ChatHistoryListItemProps {
    item: HistoryItem;
    formatCost: (value: number | null | undefined) => string;
    onSwitch: (handle: string) => void;
}

export default function ChatHistoryListItem({
    item,
    formatCost,
    onSwitch,
}: ChatHistoryListItemProps) {
    return (
        <ListItemButton
            selected={item.is_current}
            onClick={() => {
                onSwitch(item.handle);
            }}
            sx={{
                border: "1px solid",
                borderColor: item.is_current ? "primary.main" : "divider",
                mb: 1,
            }}
        >
            <Box
                sx={{
                    display: "flex",
                    alignItems: "center",
                    gap: 1,
                    minWidth: 0,
                    flexGrow: 1,
                }}
            >
                <ListItemText
                    primary={item.label}
                    secondary={
                        <Box
                            sx={{
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "space-between",
                                mt: 0.5,
                            }}
                        >
                            <Typography
                                component="span"
                                sx={{
                                    textTransform: "uppercase",
                                    letterSpacing: "0.08em",
                                    fontSize: "0.7rem",
                                    color: "text.secondary",
                                }}
                            >
                                {item.updated_at}
                            </Typography>
                            <Typography
                                component="span"
                                sx={{
                                    fontSize: "0.72rem",
                                    color: "text.secondary",
                                    fontWeight: 600,
                                }}
                            >
                                Cost: {formatCost(item.cost_usd)}
                            </Typography>
                        </Box>
                    }
                />
                {item.is_stale && !item.is_current ? (
                    <Chip
                        icon={<WarningAmberIcon fontSize="small" />}
                        size="small"
                        label="Stale"
                        sx={{
                            flexShrink: 0,
                            "& .MuiChip-label": {
                                pl: 0.5,
                            },
                        }}
                    />
                ) : null}
            </Box>
            {item.is_current ? (
                <Typography
                    variant="caption"
                    color="primary"
                    sx={{
                        textTransform: "uppercase",
                        letterSpacing: "0.12em",
                    }}
                >
                    Current
                </Typography>
            ) : null}
        </ListItemButton>
    );
}
