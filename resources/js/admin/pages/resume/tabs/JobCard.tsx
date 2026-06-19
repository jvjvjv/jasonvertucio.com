import Box from "@mui/material/Box";
import Checkbox from "@mui/material/Checkbox";
import FormControlLabel from "@mui/material/FormControlLabel";
import IconButton from "@mui/material/IconButton";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";

import DateInput from "../../../components/DateInput";
import { SALARY_PERIODS } from "../types";

import JobBulletList from "./JobBulletList";

import type { Job } from "../types";

interface JobCardProps {
    job: Job;
    onChange: (_job: Job) => void;
    onRemove: () => void;
}

export default function JobCard({ job, onChange, onRemove }: JobCardProps) {
    return (
        <Box
            sx={{
                mb: 3,
                p: 2,
                bgcolor: "grey.50",
                borderRadius: 1,
            }}
        >
            <Box
                sx={{
                    display: "flex",
                    justifyContent: "flex-end",
                    mb: 2,
                }}
            >
                <IconButton size="small" color="error" onClick={onRemove}>
                    ✕
                </IconButton>
            </Box>
            <Box
                sx={{
                    display: "grid",
                    gap: 2,
                    gridTemplateColumns: {
                        xs: "1fr",
                        md: "1fr 1fr",
                    },
                }}
            >
                <TextField
                    label="Job Title"
                    required
                    size="small"
                    value={job.jobTitle}
                    onChange={(e) => {
                        onChange({ ...job, jobTitle: e.target.value });
                    }}
                />
                <TextField
                    label="Display Job Title"
                    size="small"
                    value={job.jobTitleLabel}
                    onChange={(e) => {
                        onChange({ ...job, jobTitleLabel: e.target.value });
                    }}
                />
                <TextField
                    label="Company"
                    required
                    size="small"
                    value={job.company}
                    onChange={(e) => {
                        onChange({ ...job, company: e.target.value });
                    }}
                />
                <TextField
                    label="Location"
                    size="small"
                    value={job.location}
                    onChange={(e) => {
                        onChange({ ...job, location: e.target.value });
                    }}
                />
                <Box
                    sx={{
                        display: "flex",
                        gap: 2,
                        gridColumn: { md: "1 / -1" },
                    }}
                >
                    <DateInput
                        label="Start Date"
                        value={job.dates[0]}
                        onChange={(value) => {
                            onChange({ ...job, dates: [value, job.dates[1]] });
                        }}
                    />
                    <DateInput
                        label="End Date"
                        value={job.dates[1]}
                        allowPresent
                        onChange={(value) => {
                            onChange({ ...job, dates: [job.dates[0], value] });
                        }}
                    />
                </Box>
                <FormControlLabel
                    control={
                        <Checkbox
                            checked={job.isFreelance}
                            onChange={(e) => {
                                onChange({
                                    ...job,
                                    isFreelance: e.target.checked,
                                });
                            }}
                        />
                    }
                    label="Freelance / Contract"
                    sx={{ gridColumn: { md: "1 / -1" } }}
                />
            </Box>

            <Box
                sx={{
                    mt: 2,
                    pt: 2,
                    borderTop: 1,
                    borderColor: "divider",
                }}
            >
                <Typography
                    variant="caption"
                    color="text.secondary"
                    sx={{ display: "block", mb: 1 }}
                >
                    Salary data is private and will not appear on the public
                    resume.
                </Typography>
                <Box
                    sx={{
                        display: "grid",
                        gap: 2,
                        gridTemplateColumns: {
                            xs: "1fr",
                            md: "1fr 1fr",
                        },
                    }}
                >
                    {(["salaryStart", "salaryEnd"] as const).map(
                        (salaryKey) => (
                            <Box
                                key={salaryKey}
                                sx={{ display: "flex", gap: 1 }}
                            >
                                <TextField
                                    label={
                                        salaryKey === "salaryStart"
                                            ? "Starting Salary"
                                            : "Ending Salary"
                                    }
                                    type="number"
                                    size="small"
                                    value={job[salaryKey].amount ?? ""}
                                    onChange={(e) => {
                                        onChange({
                                            ...job,
                                            [salaryKey]: {
                                                ...job[salaryKey],
                                                amount: e.target.value
                                                    ? parseFloat(e.target.value)
                                                    : null,
                                            },
                                        });
                                    }}
                                    slotProps={{
                                        htmlInput: {
                                            min: 0,
                                            step: 0.01,
                                        },
                                    }}
                                    sx={{ flex: 1 }}
                                />
                                <TextField
                                    select
                                    size="small"
                                    value={job[salaryKey].period}
                                    onChange={(e) => {
                                        onChange({
                                            ...job,
                                            [salaryKey]: {
                                                ...job[salaryKey],
                                                period: e.target.value,
                                            },
                                        });
                                    }}
                                    sx={{ minWidth: 120 }}
                                >
                                    {SALARY_PERIODS.map((period) => (
                                        <MenuItem
                                            key={period.value}
                                            value={period.value}
                                        >
                                            {period.label}
                                        </MenuItem>
                                    ))}
                                </TextField>
                            </Box>
                        ),
                    )}
                </Box>
            </Box>

            <JobBulletList
                bullets={job.bullets}
                onChange={(bullets) => {
                    onChange({ ...job, bullets });
                }}
            />
        </Box>
    );
}
