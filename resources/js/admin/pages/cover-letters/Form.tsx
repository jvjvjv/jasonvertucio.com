import Box from "@mui/material/Box";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";

interface ResumeVersion {
    id: number;
    version: string;
    is_current: boolean;
}

interface FormData {
    resume_version_id: string | number;
    company_name: string;
    position: string;
    date: string;
    company_address: string;
    greeting: string;
    message_body: string;
    closing: string;
    signature: string;
}

interface CoverLetterFormProps {
    data: FormData;
    setData: (_key: keyof FormData, _value: string | number) => void;
    errors: Partial<{ [key: string]: string }>;
    resumeVersions: ResumeVersion[];
}

export default function CoverLetterForm({
    data,
    setData,
    errors,
    resumeVersions,
}: CoverLetterFormProps) {
    return (
        <>
            <Box
                sx={{
                    display: "grid",
                    gap: 2,
                    gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
                    mb: 2,
                }}
            >
                <TextField
                    label="Resume Version"
                    select
                    required
                    size="small"
                    value={data.resume_version_id}
                    onChange={(e) => {
                        setData("resume_version_id", e.target.value);
                    }}
                    error={!!errors.resume_version_id}
                    helperText={errors.resume_version_id}
                >
                    {resumeVersions.map((rv) => (
                        <MenuItem key={rv.id} value={rv.id}>
                            {rv.version}
                            {rv.is_current ? " (current)" : ""}
                        </MenuItem>
                    ))}
                </TextField>
                <TextField
                    label="Date"
                    type="date"
                    required
                    size="small"
                    slotProps={{ inputLabel: { shrink: true } }}
                    value={data.date}
                    onChange={(e) => {
                        setData("date", e.target.value);
                    }}
                    error={!!errors.date}
                    helperText={errors.date}
                />
            </Box>

            <Box
                sx={{
                    display: "grid",
                    gap: 2,
                    gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
                    mb: 2,
                }}
            >
                <TextField
                    label="Company Name"
                    required
                    size="small"
                    value={data.company_name}
                    onChange={(e) => {
                        setData("company_name", e.target.value);
                    }}
                    error={!!errors.company_name}
                    helperText={errors.company_name}
                    placeholder="Acme Corp"
                />
                <TextField
                    label="Position"
                    required
                    size="small"
                    value={data.position}
                    onChange={(e) => {
                        setData("position", e.target.value);
                    }}
                    error={!!errors.position}
                    helperText={errors.position}
                    placeholder="Senior Software Engineer"
                />
            </Box>

            <TextField
                label="Company Address"
                size="small"
                fullWidth
                multiline
                rows={3}
                value={data.company_address}
                onChange={(e) => {
                    setData("company_address", e.target.value);
                }}
                error={!!errors.company_address}
                helperText={
                    errors.company_address ??
                    "One line per address part. Leave blank to omit."
                }
                placeholder="123 Main Street&#10;Suite 100&#10;City, ST 12345"
                sx={{ mb: 2 }}
            />

            <TextField
                label="Greeting"
                required
                size="small"
                fullWidth
                value={data.greeting}
                onChange={(e) => {
                    setData("greeting", e.target.value);
                }}
                error={!!errors.greeting}
                helperText={errors.greeting}
                placeholder="Dear Hiring Manager,"
                sx={{ mb: 2 }}
            />

            <TextField
                label="Message Body"
                required
                size="small"
                fullWidth
                multiline
                rows={16}
                value={data.message_body}
                onChange={(e) => {
                    setData("message_body", e.target.value);
                }}
                error={!!errors.message_body}
                helperText={
                    errors.message_body ??
                    "Markdown supported. Use blank lines for paragraph breaks."
                }
                sx={{ mb: 2 }}
            />

            <Box
                sx={{
                    display: "grid",
                    gap: 2,
                    gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
                    mb: 2,
                }}
            >
                <TextField
                    label="Closing"
                    size="small"
                    value={data.closing}
                    onChange={(e) => {
                        setData("closing", e.target.value);
                    }}
                    error={!!errors.closing}
                    helperText={errors.closing}
                    placeholder="Sincerely,"
                />
                <TextField
                    label="Signature"
                    size="small"
                    value={data.signature}
                    onChange={(e) => {
                        setData("signature", e.target.value);
                    }}
                    error={!!errors.signature}
                    helperText={errors.signature}
                    placeholder="Jason Vertucio"
                />
            </Box>
        </>
    );
}

export type { FormData, ResumeVersion };
