import AdminPanelSettingsIcon from "@mui/icons-material/AdminPanelSettings";
import CodeIcon from "@mui/icons-material/Code";
import DescriptionIcon from "@mui/icons-material/Description";
import DriveFileRenameOutlineIcon from "@mui/icons-material/DriveFileRenameOutline";
import EditNoteIcon from "@mui/icons-material/EditNote";
import ForumIcon from "@mui/icons-material/Forum";
import InboxIcon from "@mui/icons-material/Inbox";
import InsightsIcon from "@mui/icons-material/Insights";
import MemoryIcon from "@mui/icons-material/Memory";
import PsychologyIcon from "@mui/icons-material/Psychology";
import PushPinIcon from "@mui/icons-material/PushPin";
import RuleIcon from "@mui/icons-material/Rule";
import SmartToyIcon from "@mui/icons-material/SmartToy";
import TrackChangesIcon from "@mui/icons-material/TrackChanges";
import VisibilityIcon from "@mui/icons-material/Visibility";

import type { ReactNode } from "react";

const registry: { [key: string]: ReactNode } = {
    AdminPanelSettings: <AdminPanelSettingsIcon fontSize="large" />,
    Code: <CodeIcon fontSize="large" />,
    Description: <DescriptionIcon fontSize="large" />,
    DriveFileRenameOutline: <DriveFileRenameOutlineIcon fontSize="large" />,
    EditNote: <EditNoteIcon fontSize="large" />,
    Forum: <ForumIcon fontSize="large" />,
    Inbox: <InboxIcon fontSize="large" />,
    Insights: <InsightsIcon fontSize="large" />,
    Memory: <MemoryIcon fontSize="large" />,
    Psychology: <PsychologyIcon fontSize="large" />,
    PushPin: <PushPinIcon fontSize="large" />,
    Rule: <RuleIcon fontSize="large" />,
    SmartToy: <SmartToyIcon fontSize="large" />,
    TrackChanges: <TrackChangesIcon fontSize="large" />,
    Visibility: <VisibilityIcon fontSize="large" />,
};

export function getIcon(name: string): ReactNode {
    return registry[name] ?? <DescriptionIcon fontSize="large" />;
}
