import { Link } from '@inertiajs/react';
import Grid from '@mui/material/Grid';
import Card from '@mui/material/Card';
import CardActionArea from '@mui/material/CardActionArea';
import CardContent from '@mui/material/CardContent';
import Typography from '@mui/material/Typography';
import Box from '@mui/material/Box';

export interface NavBlock {
    href: string;
    icon: React.ReactNode;
    label: string;
    description: string;
}

interface NavGridProps {
    blocks: NavBlock[];
}

export default function NavGrid({ blocks }: NavGridProps) {
    return (
        <Grid container spacing={3}>
            {blocks.map((block) => (
                <Grid size={{ xs: 12, sm: 6, lg: 4 }} key={block.href}>
                    <Card>
                        <CardActionArea component={Link} href={block.href}>
                            <CardContent sx={{ display: 'flex', alignItems: 'center', p: 3 }}>
                                <Box
                                    sx={{
                                        flexShrink: 0,
                                        width: 48,
                                        height: 48,
                                        bgcolor: 'primary.main',
                                        borderRadius: 2,
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        color: 'white',
                                        mr: 2,
                                    }}
                                >
                                    {block.icon}
                                </Box>
                                <Box>
                                    <Typography variant="subtitle1" fontWeight="bold">
                                        {block.label}
                                    </Typography>
                                    <Typography variant="body2" color="text.secondary">
                                        {block.description}
                                    </Typography>
                                </Box>
                            </CardContent>
                        </CardActionArea>
                    </Card>
                </Grid>
            ))}
        </Grid>
    );
}
