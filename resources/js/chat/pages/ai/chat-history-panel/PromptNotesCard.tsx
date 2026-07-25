import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Typography from "@mui/material/Typography";

export default function PromptNotesCard() {
    return (
        <Card>
            <CardContent>
                <Typography variant="h5" sx={{ mb: 1 }}>
                    Prompt Notes
                </Typography>
                <Typography variant="body2" color="text.secondary">
                    The conversation is saved and can contribute new insights to
                    AI Memory for this bot.
                </Typography>
            </CardContent>
        </Card>
    );
}
