import TextField from "@mui/material/TextField";

interface JobDetailsFormProps {
    jobTitle: string;
    companyName: string;
    jobLocation: string;
    jobDescription: string;
    onJobTitleChange: (_value: string) => void;
    onCompanyNameChange: (_value: string) => void;
    onJobLocationChange: (_value: string) => void;
    onJobDescriptionChange: (_value: string) => void;
}

export default function JobDetailsForm({
    jobTitle,
    companyName,
    jobLocation,
    jobDescription,
    onJobTitleChange,
    onCompanyNameChange,
    onJobLocationChange,
    onJobDescriptionChange,
}: JobDetailsFormProps) {
    return (
        <>
            <TextField
                label="Job Title"
                size="small"
                fullWidth
                value={jobTitle}
                onChange={(e) => {
                    onJobTitleChange(e.target.value);
                }}
                placeholder="(optional)"
                sx={{ mb: 3 }}
            />

            <TextField
                label="Company Name"
                size="small"
                fullWidth
                value={companyName}
                onChange={(e) => {
                    onCompanyNameChange(e.target.value);
                }}
                placeholder="(optional)"
                sx={{ mb: 3 }}
            />

            <TextField
                label="Job Location"
                size="small"
                fullWidth
                value={jobLocation}
                onChange={(e) => {
                    onJobLocationChange(e.target.value);
                }}
                placeholder="(optional)"
                sx={{ mb: 3 }}
            />

            <TextField
                label="Job Description"
                required
                size="small"
                fullWidth
                multiline
                rows={12}
                value={jobDescription}
                onChange={(e) => {
                    onJobDescriptionChange(e.target.value);
                }}
                placeholder="Paste or type the full job description here..."
                sx={{ mb: 3 }}
            />
        </>
    );
}
