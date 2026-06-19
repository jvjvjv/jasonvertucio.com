import Box from "@mui/material/Box";
import IconButton from "@mui/material/IconButton";
import TextField from "@mui/material/TextField";

import DateInput from "../../../components/DateInput";

import type { Education } from "../types";

interface EducationCardProps {
    education: Education;
    onChange: (_education: Education) => void;
    onRemove: () => void;
}

export default function EducationCard({
    education,
    onChange,
    onRemove,
}: EducationCardProps) {
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
                    label="Institution"
                    required
                    size="small"
                    value={education.institution}
                    onChange={(e) => {
                        onChange({
                            ...education,
                            institution: e.target.value,
                        });
                    }}
                />
                <TextField
                    label="Location"
                    size="small"
                    placeholder="City, State"
                    value={education.location}
                    onChange={(e) => {
                        onChange({
                            ...education,
                            location: e.target.value,
                        });
                    }}
                />
                <TextField
                    label="Degree"
                    size="small"
                    value={education.degree}
                    onChange={(e) => {
                        onChange({
                            ...education,
                            degree: e.target.value,
                        });
                    }}
                />
                <TextField
                    label="Level"
                    size="small"
                    placeholder="BS, BA, Masters, Certificate, Non-Degree..."
                    value={education.level}
                    onChange={(e) => {
                        onChange({
                            ...education,
                            level: e.target.value,
                        });
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
                        value={education.dates[0]}
                        onChange={(value) => {
                            onChange({
                                ...education,
                                dates: [value, education.dates[1]],
                            });
                        }}
                    />
                    <DateInput
                        label="End Date"
                        value={education.dates[1]}
                        allowPresent
                        onChange={(value) => {
                            onChange({
                                ...education,
                                dates: [education.dates[0], value],
                            });
                        }}
                    />
                </Box>
                <TextField
                    label="Description"
                    size="small"
                    fullWidth
                    multiline
                    rows={2}
                    value={education.description}
                    onChange={(e) => {
                        onChange({
                            ...education,
                            description: e.target.value,
                        });
                    }}
                    sx={{ gridColumn: { md: "1 / -1" } }}
                />
            </Box>
        </Box>
    );
}
