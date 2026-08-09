import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Typography from "@mui/material/Typography";

interface BotHeaderCardProps {
    name: string;
    description: string | null;
}

export default function BotHeaderCard({
    name,
    description,
}: BotHeaderCardProps) {
    return (
        <Card variant="outlined">
            <CardContent>
                <Typography
                    variant="overline"
                    color="text.secondary"
                    sx={{ letterSpacing: "0.18em" }}
                >
                    AI Chat Bot
                </Typography>
                <Typography
                    variant="h3"
                    component="h1"
                    sx={{ mt: 0.25, fontSize: { xs: "1.5rem", md: "3rem" } }}
                >
                    {name}
                </Typography>
                {description ? (
                    <Typography sx={{ mt: 1 }} color="text.secondary">
                        {description}
                    </Typography>
                ) : null}
            </CardContent>
        </Card>
    );
}
