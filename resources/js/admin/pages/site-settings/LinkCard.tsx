import ArrowDownwardIcon from "@mui/icons-material/ArrowDownward";
import ArrowUpwardIcon from "@mui/icons-material/ArrowUpward";
import DeleteIcon from "@mui/icons-material/Delete";
import DragIndicatorIcon from "@mui/icons-material/DragIndicator";
import ExpandLessIcon from "@mui/icons-material/ExpandLess";
import ExpandMoreIcon from "@mui/icons-material/ExpandMore";
import Box from "@mui/material/Box";
import Card from "@mui/material/Card";
import Collapse from "@mui/material/Collapse";
import IconButton from "@mui/material/IconButton";
import Typography from "@mui/material/Typography";

import LinkFieldGroup from "./LinkFieldGroup";

interface LinkItem {
    label?: string;
    href?: string;
    ariaLabel?: string;
    hover?: string;
    target?: string;
    can?: string;
    divider?: boolean;
    _id: number;
    _open: boolean;
}

interface LinkCardProps {
    link: LinkItem;
    index: number;
    totalCount: number;
    permissions: string[];
    onToggleCard: () => void;
    onMoveLink: (_direction: -1 | 1) => void;
    onRemoveLink: () => void;
    onUpdateLink: (_field: string, _value: string) => void;
}

export default function LinkCard({
    link,
    index,
    totalCount,
    permissions,
    onToggleCard,
    onMoveLink,
    onRemoveLink,
    onUpdateLink,
}: LinkCardProps) {
    return (
        <Card sx={link._open ? { borderColor: "primary.main" } : {}}>
            <Box
                onClick={onToggleCard}
                sx={{
                    display: "flex",
                    alignItems: "center",
                    gap: 1.5,
                    px: 2,
                    py: 1.5,
                    cursor: "pointer",
                    userSelect: "none",
                    bgcolor: link._open ? "grey.50" : "transparent",
                    borderBottom: link._open ? "1px solid" : "none",
                    borderColor: "divider",
                    "&:hover": { bgcolor: "grey.50" },
                }}
            >
                <DragIndicatorIcon
                    fontSize="small"
                    sx={{ color: "grey.400" }}
                />

                <IconButton
                    size="small"
                    disabled={index === 0}
                    onClick={(e) => {
                        e.stopPropagation();
                        onMoveLink(-1);
                    }}
                    aria-label="Move up"
                >
                    <ArrowUpwardIcon fontSize="small" />
                </IconButton>
                <IconButton
                    size="small"
                    disabled={index === totalCount - 1}
                    onClick={(e) => {
                        e.stopPropagation();
                        onMoveLink(1);
                    }}
                    aria-label="Move down"
                >
                    <ArrowDownwardIcon fontSize="small" />
                </IconButton>

                <Typography
                    variant="body2"
                    fontWeight="medium"
                    sx={{
                        flex: 1,
                        overflow: "hidden",
                        textOverflow: "ellipsis",
                        whiteSpace: "nowrap",
                        color: link.divider ? "grey.400" : "text.primary",
                        fontStyle: link.divider ? "italic" : "normal",
                    }}
                >
                    {link.divider
                        ? "— divider —"
                        : (link.label ?? "(new link)")}
                </Typography>

                {!link.divider && link.can && (
                    <Typography
                        variant="caption"
                        sx={{
                            px: 1,
                            py: 0.25,
                            bgcolor: "warning.light",
                            color: "warning.dark",
                            borderRadius: 1,
                            display: { xs: "none", sm: "inline" },
                        }}
                    >
                        {link.can}
                    </Typography>
                )}

                {link._open ? (
                    <ExpandLessIcon
                        fontSize="small"
                        sx={{ color: "grey.400" }}
                    />
                ) : (
                    <ExpandMoreIcon
                        fontSize="small"
                        sx={{ color: "grey.400" }}
                    />
                )}

                <IconButton
                    size="small"
                    color="error"
                    onClick={(e) => {
                        e.stopPropagation();
                        onRemoveLink();
                    }}
                    aria-label="Remove link"
                >
                    <DeleteIcon fontSize="small" />
                </IconButton>
            </Box>

            <Collapse in={link._open}>
                <Box sx={{ px: 2, py: 2 }}>
                    {link.divider ? (
                        <Typography
                            variant="body2"
                            color="text.secondary"
                            fontStyle="italic"
                        >
                            This is a visual divider. It renders as a horizontal
                            rule in the navigation dropdown.
                        </Typography>
                    ) : (
                        <LinkFieldGroup
                            link={link}
                            permissions={permissions}
                            onUpdateLink={onUpdateLink}
                        />
                    )}
                </Box>
            </Collapse>
        </Card>
    );
}
