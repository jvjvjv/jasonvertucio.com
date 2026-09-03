import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";

interface Bot {
    required_permission: string | null;
    require_visitor_identity: boolean;
}

interface BotAccessCardProps {
    bot: Bot;
}

export default function BotAccessCard({ bot }: BotAccessCardProps) {
    return (
        <Card>
            <CardContent>
                <Typography variant="h5" sx={{ mb: 1 }}>
                    Access
                </Typography>
                <Stack spacing={0.75}>
                    <Typography variant="body2" color="text.secondary">
                        {bot.required_permission == null &&
                        bot.require_visitor_identity
                            ? "Public bot"
                            : bot.required_permission != null &&
                                bot.require_visitor_identity
                              ? "Mostly public bot"
                              : "Restricted bot"}
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                        {bot.require_visitor_identity
                            ? "Name and email are required before the first guest message."
                            : "No guest identity is required by this bot."}
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                        Only chats created in this browser are listed here.
                    </Typography>
                </Stack>
            </CardContent>
        </Card>
    );
}
