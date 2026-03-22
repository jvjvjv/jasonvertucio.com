import { Head, Link } from '@inertiajs/react';
import Grid from '@mui/material/Grid';
import Card from '@mui/material/Card';
import CardActionArea from '@mui/material/CardActionArea';
import CardContent from '@mui/material/CardContent';
import Typography from '@mui/material/Typography';
import Box from '@mui/material/Box';
import DescriptionIcon from '@mui/icons-material/Description';
import DriveFileRenameOutlineIcon from '@mui/icons-material/DriveFileRenameOutline';
import SmartToyIcon from '@mui/icons-material/SmartToy';
import PushPinIcon from '@mui/icons-material/PushPin';
import InboxIcon from '@mui/icons-material/Inbox';
import AdminLayout from '../layouts/AdminLayout';

interface NavBlock {
    can: string | null;
    href: string;
    icon: string;
    label: string;
    description: string;
}

interface DashboardProps {
    navBlocks: NavBlock[];
}

const iconMap: Record<string, React.ElementType> = {
    Description: DescriptionIcon,
    DriveFileRenameOutline: DriveFileRenameOutlineIcon,
    SmartToy: SmartToyIcon,
    PushPin: PushPinIcon,
    Inbox: InboxIcon,
};

export default function Dashboard({ navBlocks }: DashboardProps) {
    return (
        <AdminLayout title="Admin Dashboard">
            <Head title="Admin Dashboard" />
            <Grid container spacing={3}>
                {navBlocks.map((block) => {
                    const IconComponent = iconMap[block.icon];
                    return (
                        <Grid size={{ xs: 12, sm: 6, lg: 4 }} key={block.href}>
                            <Card>
                                <CardActionArea
                                    component={Link}
                                    href={block.href}
                                >
                                    <CardContent sx={{ display: 'flex', alignItems: 'center', p: 3 }}>
                                        {IconComponent && (
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
                                                <IconComponent fontSize="large" />
                                            </Box>
                                        )}
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
                    );
                })}
            </Grid>
        </AdminLayout>
    );
}
