import AddCommentIcon from "@mui/icons-material/AddComment";
import BackIcon from "@mui/icons-material/ArrowBack";
import ChatIcon from "@mui/icons-material/Chat";
import InfoIcon from "@mui/icons-material/Info";
import Badge from "@mui/material/Badge";
import Box from "@mui/material/Box";
import IconButton from "@mui/material/IconButton";
import Tab from "@mui/material/Tab";
import Tabs from "@mui/material/Tabs";

interface ChatTabsProps {
    activeTab: number;
    badgeColor: "success" | "warning" | "error" | "info";
    onTabChange: (_tab: number) => void;
    onReset: () => void;
    previousHref?: string | null;
}

export default function ChatTabs({
    activeTab,
    badgeColor,
    onTabChange,
    onReset,
    previousHref,
}: ChatTabsProps) {
    return (
        <Box
            sx={{
                position: "sticky",
                top: 0,
                zIndex: 10,
                display: "flex",
                alignItems: "center",
                gap: 1,
                bgcolor: "background.paper",
                borderBottom: 1,
                borderColor: "divider",
            }}
        >
            {previousHref ? (
                <Box sx={{ ml: 1.5 }}>
                    <IconButton
                        component="a"
                        href={previousHref}
                        aria-label="Go back"
                        size="large"
                    >
                        <BackIcon />
                    </IconButton>
                </Box>
            ) : null}

            <Tabs
                value={activeTab}
                onChange={(_, value: number) => {
                    onTabChange(value);
                }}
                aria-label="Chat page tabs"
                sx={{
                    "& .MuiTab-root": {
                        minWidth: 0,
                        px: 2,
                        py: 1.5,
                    },
                }}
            >
                <Tab
                    icon={
                        <Badge
                            variant="dot"
                            color={badgeColor}
                            overlap="circular"
                        >
                            <ChatIcon />
                        </Badge>
                    }
                />
                <Tab icon={<InfoIcon />} />
            </Tabs>
            <Box sx={{ flexGrow: 1 }} />
            <Box sx={{ pr: 1 }}>
                <IconButton
                    aria-label="Start a new chat"
                    size="small"
                    onClick={onReset}
                >
                    <AddCommentIcon fontSize="small" />
                </IconButton>
            </Box>
        </Box>
    );
}
