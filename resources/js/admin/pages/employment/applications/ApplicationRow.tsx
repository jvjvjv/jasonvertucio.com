import { Link as InertiaLink } from "@inertiajs/react";
import AutoFixHighOutlinedIcon from "@mui/icons-material/AutoFixHighOutlined";
import BackHandOutlinedIcon from "@mui/icons-material/BackHandOutlined";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import EditNoteIcon from "@mui/icons-material/EditNote";
import PersonSearchOutlinedIcon from "@mui/icons-material/PersonSearchOutlined";
import TravelExploreIcon from "@mui/icons-material/TravelExplore";
import {
    TableRow,
    TableCell,
    Box,
    IconButton,
    CircularProgress,
} from "@mui/material";
import Link from "@mui/material/Link";
import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";
import { memo } from "react";

import type { ApplicationListItem } from "@/types";

import StatusChip from "@/admin/components/StatusChip";
import UsageChip from "@/admin/components/UsageChip";
import { INTEL_PAGES } from "@/admin/pages/employment/company-intel/types";
import { resolveApplicationDisplayStatus } from "@/admin/utils/applicationStatus";
import { formatCalendarDate, getLocaleDateTime } from "@/utils/date";

const LIST_URL = "/admin/employment/applications";
const EM_DASH = "—";

interface ApplicationRowProps {
    application: ApplicationListItem;
    ghostedAfterDays: number;
    onPass: (id: number) => void;
    onDelete: (application: ApplicationListItem) => void;
    onBeginIntel: (id: number) => void;
    /** Only the row whose intel is starting re-renders. */
    isStartingIntel: boolean;
}

/**
 * One row of the list. Memoized, with stable callbacks from the page, so
 * opening a dialog or reporting an error does not re-render every row.
 */
export default memo(function ApplicationRow({
    application,
    ghostedAfterDays,
    onPass,
    onDelete,
    onBeginIntel,
    isStartingIntel,
}: ApplicationRowProps) {
    const showUrl = `${LIST_URL}/${application.id}`;
    const latestOccurredAt = application.latest_status_update?.occurred_at;
    const displayStatus = resolveApplicationDisplayStatus(
        application.status,
        latestOccurredAt,
        ghostedAfterDays,
    );

    return (
        <TableRow hover>
            <TableCell>
                <Box display="flex" alignItems="center" gap={1}>
                    <Link
                        component={InertiaLink}
                        href={showUrl}
                        underline="hover"
                        color="inherit"
                    >
                        <Typography
                            variant="body2"
                            fontWeight={600}
                            color="primary"
                        >
                            {application.company_name}
                        </Typography>
                    </Link>
                    <StatusChip
                        status={displayStatus}
                        size={"small"}
                        tip={
                            latestOccurredAt
                                ? formatCalendarDate(latestOccurredAt)
                                : undefined
                        }
                    />
                </Box>

                {application.position ? (
                    <Typography variant="caption" color="text.secondary">
                        {application.position}
                    </Typography>
                ) : null}
            </TableCell>
            <TableCell>
                {application.fit_score !== null
                    ? `${application.fit_score}%`
                    : EM_DASH}
            </TableCell>
            <TableCell>{application.resume_version ?? EM_DASH}</TableCell>
            <TableCell>
                {application.usage ? (
                    <UsageChip usage={application.usage} />
                ) : null}
            </TableCell>
            <TableCell>
                <Tooltip
                    title={
                        typeof application.last_activity_at_iso === "string"
                            ? (getLocaleDateTime(
                                  application.last_activity_at_iso,
                              ) ?? "")
                            : ""
                    }
                    arrow
                >
                    <Typography variant="caption">
                        {application.last_activity_at ?? "-"}
                    </Typography>
                </Tooltip>
            </TableCell>
            <TableCell align="right">
                <Box
                    sx={{
                        display: "flex",
                        justifyContent: "flex-end",
                        gap: 0,
                    }}
                >
                    <IconButton
                        component={InertiaLink}
                        href={`/admin/resume/targeted-resumes/${application.targeted_resume_id}/edit`}
                        size="small"
                        color="primary"
                        disabled={application.targeted_resume_id === null}
                        title="Edit targeted resume"
                        aria-label="Edit targeted resume"
                    >
                        <EditNoteIcon fontSize="small" />
                    </IconButton>
                    <IconButton
                        size="small"
                        color="warning"
                        title="Pass"
                        aria-label="Pass"
                        disabled={application.status !== "draft"}
                        onClick={() => {
                            onPass(application.id);
                        }}
                    >
                        <BackHandOutlinedIcon fontSize="small" />
                    </IconButton>
                    {application.intel?.scope !== "role" ? (
                        <IconButton
                            size="small"
                            color="primary"
                            title="Begin intel"
                            aria-label="Begin intel"
                            disabled={isStartingIntel}
                            onClick={() => {
                                onBeginIntel(application.id);
                            }}
                        >
                            {isStartingIntel ? (
                                <CircularProgress size={16} color="inherit" />
                            ) : (
                                <PersonSearchOutlinedIcon fontSize="small" />
                            )}
                        </IconButton>
                    ) : (
                        <IntelCell intel={application.intel} />
                    )}
                    <IconButton
                        component={InertiaLink}
                        href={showUrl}
                        size="small"
                        color="primary"
                        title="Open"
                        aria-label="Open"
                    >
                        <AutoFixHighOutlinedIcon fontSize="small" />
                    </IconButton>
                    <IconButton
                        size="small"
                        color="error"
                        title="Delete"
                        aria-label="Delete"
                        onClick={() => {
                            onDelete(application);
                        }}
                    >
                        <DeleteOutlineIcon fontSize="small" />
                    </IconButton>
                </Box>
            </TableCell>
        </TableRow>
    );
});
/**
 * Links to the application's intel, or to intel already gathered on the same
 * company for another application. A spinner stands in while research runs.
 */
function IntelCell({ intel }: { intel: ApplicationListItem["intel"] }) {
    if (intel === null) {
        return null;
    }

    const isCompanyOnly = intel.scope === "company";
    const label = isCompanyOnly
        ? `Intel on ${intel.company_name ?? "this company"} from another application`
        : intel.status === "failed"
          ? "Intel failed — open to retry"
          : intel.status === "ready"
            ? "Open intel"
            : "Researching — open intel";

    return (
        <Tooltip title={label} arrow>
            <IconButton
                component={InertiaLink}
                href={`${INTEL_PAGES}/${intel.id}`}
                size="small"
                color={
                    intel.status === "failed"
                        ? "error"
                        : isCompanyOnly
                          ? "default"
                          : "primary"
                }
                aria-label={label}
            >
                {intel.status === "queued" || intel.status === "running" ? (
                    <CircularProgress size={16} color="inherit" />
                ) : (
                    <TravelExploreIcon
                        fontSize="small"
                        sx={isCompanyOnly ? { opacity: 0.6 } : undefined}
                    />
                )}
            </IconButton>
        </Tooltip>
    );
}
