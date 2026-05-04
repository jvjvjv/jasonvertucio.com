import { Head, Link as InertiaLink, router } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Card from "@mui/material/Card";
import Chip from "@mui/material/Chip";
import Link from "@mui/material/Link";
import Table from "@mui/material/Table";
import TableBody from "@mui/material/TableBody";
import TableCell from "@mui/material/TableCell";
import TableContainer from "@mui/material/TableContainer";
import TableHead from "@mui/material/TableHead";
import TableRow from "@mui/material/TableRow";
import Typography from "@mui/material/Typography";
import AdminLayout from "../../../layouts/AdminLayout";
import PageHeader from "../../../components/PageHeader";
import EmptyTableRow from "../../../components/EmptyTableRow";
import ConfirmDialog from "../../../components/ConfirmDialog";
import useConfirmDialog from "../../../hooks/useConfirmDialog";
import type { AiSystem } from "../../../types";

interface IndexProps {
    systems: AiSystem[];
}

export default function Index({ systems }: IndexProps) {
    const { dialogProps, confirm } = useConfirmDialog();

    const handleDelete = (id: number, name: string) => {
        confirm(`Delete AI system "${name}"? This cannot be undone.`, () => {
            router.delete(`/admin/ai/systems/${id}`);
        });
    };

    const handleDuplicate = (id: number) => {
        confirm(
            "Duplicate this AI system?",
            () => {
                router.post(`/admin/ai/systems/${id}/duplicate`);
            },
            { confirmLabel: "Duplicate", confirmColor: "primary" },
        );
    };

    return (
        <AdminLayout>
            <Head title="AI Systems | AI Tools" />
            <PageHeader
                title="AI Systems"
                backHref="/admin/ai"
                backLabel="Back to AI Tools"
            />

            <Box sx={{ display: "flex", justifyContent: "flex-end", mb: 2 }}>
                <Button
                    component={InertiaLink}
                    href="/admin/ai/systems/new"
                    variant="contained"
                >
                    Add System
                </Button>
            </Box>

            <Card>
                <TableContainer>
                    <Table size="small">
                        <TableHead>
                            <TableRow>
                                <TableCell>Name</TableCell>
                                <TableCell>Provider</TableCell>
                                <TableCell>Model</TableCell>
                                <TableCell>Default For</TableCell>
                                <TableCell>API Calls</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {systems.length === 0 ? (
                                <EmptyTableRow
                                    colSpan={6}
                                    message="No AI systems configured yet."
                                    actionLabel="Add your first one"
                                    actionHref="/admin/ai/systems/new"
                                />
                            ) : (
                                systems.map((system) => (
                                    <TableRow
                                        key={system.id}
                                        hover
                                        sx={{
                                            opacity: system.is_active ? 1 : 0.5,
                                        }}
                                    >
                                        <TableCell>
                                            <Link
                                                component={InertiaLink}
                                                href={`/admin/ai/systems/${system.id}`}
                                                underline="hover"
                                                color="inherit"
                                                sx={{ fontWeight: 500 }}
                                            >
                                                {system.name}
                                            </Link>
                                        </TableCell>
                                        <TableCell>{system.provider}</TableCell>
                                        <TableCell>
                                            <Typography
                                                variant="body2"
                                                fontFamily="monospace"
                                            >
                                                {system.model}
                                            </Typography>
                                        </TableCell>
                                        <TableCell>
                                            {system.feature_defaults_list
                                                .length > 0
                                                ? system.feature_defaults_list.map(
                                                      (f) => (
                                                          <Chip
                                                              key={f}
                                                              label={f}
                                                              size="small"
                                                              sx={{ mr: 0.5 }}
                                                          />
                                                      ),
                                                  )
                                                : "-"}
                                        </TableCell>
                                        <TableCell>
                                            {system.interaction_logs_count >
                                            0 ? (
                                                <Link
                                                    component={InertiaLink}
                                                    href={`/admin/ai/systems/${system.id}/logs`}
                                                    underline="hover"
                                                >
                                                    {
                                                        system.interaction_logs_count
                                                    }
                                                </Link>
                                            ) : (
                                                "-"
                                            )}
                                        </TableCell>
                                        <TableCell align="right">
                                            <Box
                                                sx={{
                                                    display: "flex",
                                                    justifyContent: "flex-end",
                                                    gap: 1,
                                                }}
                                            >
                                                <Button
                                                    component={InertiaLink}
                                                    href={`/admin/ai/systems/${system.id}`}
                                                    size="small"
                                                >
                                                    Edit
                                                </Button>
                                                <Button
                                                    component={InertiaLink}
                                                    href={`/admin/ai/systems/${system.id}/logs`}
                                                    size="small"
                                                >
                                                    Logs
                                                </Button>
                                                <Button
                                                    size="small"
                                                    onClick={() =>
                                                        handleDuplicate(
                                                            system.id,
                                                        )
                                                    }
                                                >
                                                    Copy
                                                </Button>
                                                <Button
                                                    size="small"
                                                    color="error"
                                                    onClick={() =>
                                                        handleDelete(
                                                            system.id,
                                                            system.name,
                                                        )
                                                    }
                                                >
                                                    Delete
                                                </Button>
                                            </Box>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </TableContainer>
            </Card>
            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
