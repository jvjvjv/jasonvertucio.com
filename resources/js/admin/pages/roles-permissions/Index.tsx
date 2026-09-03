import { Head, router, usePage } from "@inertiajs/react";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import EditIcon from "@mui/icons-material/Edit";
import KeyIcon from "@mui/icons-material/Key";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Chip from "@mui/material/Chip";
import IconButton from "@mui/material/IconButton";
import Tab from "@mui/material/Tab";
import Tabs from "@mui/material/Tabs";
import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";
import { useState } from "react";

import PermissionFormDialog from "./PermissionFormDialog";
import RoleFormDialog from "./RoleFormDialog";
import RolePermissionsDialog from "./RolePermissionsDialog";

import type { Permission, Role } from "./types";
import type { ColumnDef } from "@/admin/components/DataTable";
import type { SharedProps } from "@/types";

import ConfirmDialog from "@/admin/components/ConfirmDialog";
import DataTable from "@/admin/components/DataTable";
import PageHeader from "@/admin/components/PageHeader";
import AdminLayout from "@/admin/layouts/AdminLayout";
import useConfirmDialog from "@/hooks/useConfirmDialog";

interface IndexProps {
    roles: Role[];
    permissions: Permission[];
}

/** Renders a name/title pair, falling back to just the name. */
function NameCell({ name, title }: { name: string; title: string | null }) {
    return (
        <Box>
            <Typography
                variant="body2"
                fontWeight={500}
                sx={{ fontFamily: "monospace" }}
            >
                {name}
            </Typography>
            {title && (
                <Typography variant="caption" color="text.secondary">
                    {title}
                </Typography>
            )}
        </Box>
    );
}

function DescriptionCell({ description }: { description: string | null }) {
    return description ? (
        <Typography variant="body2" color="text.secondary">
            {description}
        </Typography>
    ) : (
        <Typography variant="body2" color="text.disabled">
            —
        </Typography>
    );
}

/** A count chip that greys out at zero. */
function CountCell({ count, noun }: { count: number; noun: string }) {
    return count > 0 ? (
        <Chip
            label={`${String(count)} ${noun}${count !== 1 ? "s" : ""}`}
            size="small"
            color="primary"
            variant="outlined"
        />
    ) : (
        <Typography variant="body2" color="text.disabled">
            —
        </Typography>
    );
}

export default function Index({ roles, permissions }: IndexProps) {
    const page = usePage<SharedProps>();
    const granted = page.props.auth.user?.permissions ?? [];
    const can = (permission: string) => granted.includes(permission);

    const [activeTab, setActiveTab] = useState(0);
    const [roleDialogOpen, setRoleDialogOpen] = useState(false);
    const [editingRole, setEditingRole] = useState<Role | null>(null);
    const [permissionDialogOpen, setPermissionDialogOpen] = useState(false);
    const [editingPermission, setEditingPermission] =
        useState<Permission | null>(null);
    const [assignOpen, setAssignOpen] = useState(false);
    const [assigningRole, setAssigningRole] = useState<Role | null>(null);

    const { dialogProps, confirm } = useConfirmDialog();

    const openRoleDialog = (role: Role | null) => {
        setEditingRole(role);
        setRoleDialogOpen(true);
    };

    const openPermissionDialog = (permission: Permission | null) => {
        setEditingPermission(permission);
        setPermissionDialogOpen(true);
    };

    const openAssignDialog = (role: Role) => {
        setAssigningRole(role);
        setAssignOpen(true);
    };

    const handleDeleteRole = (role: Role) => {
        confirm(
            `Delete the role "${role.name}"? This cannot be undone.`,
            () => {
                router.delete(`/admin/roles/${String(role.id)}`);
            },
            { confirmLabel: "Delete", confirmColor: "error" },
        );
    };

    const handleDeletePermission = (permission: Permission) => {
        confirm(
            `Delete the permission "${permission.name}"? Any code that checks for it will stop granting access. This cannot be undone.`,
            () => {
                router.delete(`/admin/permissions/${String(permission.id)}`);
            },
            { confirmLabel: "Delete", confirmColor: "error" },
        );
    };

    const roleColumns: ColumnDef<Role>[] = [
        {
            key: "name",
            label: "Role",
            render: (row) => <NameCell name={row.name} title={row.title} />,
        },
        {
            key: "description",
            label: "Description",
            render: (row) => <DescriptionCell description={row.description} />,
        },
        {
            key: "permissions",
            label: "Permissions",
            align: "center",
            render: (row) => (
                <CountCell count={row.permissions.length} noun="permission" />
            ),
        },
        {
            key: "users_count",
            label: "Users",
            align: "center",
            render: (row) => <CountCell count={row.users_count} noun="user" />,
        },
    ];

    const permissionColumns: ColumnDef<Permission>[] = [
        {
            key: "name",
            label: "Permission",
            render: (row) => <NameCell name={row.name} title={row.title} />,
        },
        {
            key: "description",
            label: "Description",
            render: (row) => <DescriptionCell description={row.description} />,
        },
        {
            key: "roles_count",
            label: "Held by",
            align: "center",
            render: (row) => <CountCell count={row.roles_count} noun="role" />,
        },
    ];

    return (
        <AdminLayout>
            <Head title="Roles & Permissions" />
            <PageHeader
                title="Roles & Permissions"
                backHref="/admin"
                backLabel="Back to Admin Dashboard"
            />

            <Box
                sx={{
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "space-between",
                    borderBottom: 1,
                    borderColor: "divider",
                    mb: 2,
                }}
            >
                <Tabs
                    value={activeTab}
                    onChange={(_, value: number) => {
                        setActiveTab(value);
                    }}
                >
                    <Tab label={`Roles (${String(roles.length)})`} />
                    <Tab
                        label={`Permissions (${String(permissions.length)})`}
                    />
                </Tabs>

                {activeTab === 0 && can("create-roles") && (
                    <Button
                        variant="contained"
                        size="small"
                        onClick={() => {
                            openRoleDialog(null);
                        }}
                    >
                        New Role
                    </Button>
                )}
                {activeTab === 1 && can("create-permissions") && (
                    <Button
                        variant="contained"
                        size="small"
                        onClick={() => {
                            openPermissionDialog(null);
                        }}
                    >
                        New Permission
                    </Button>
                )}
            </Box>

            {activeTab === 0 && (
                <DataTable
                    columns={roleColumns}
                    data={roles}
                    emptyMessage="No roles found."
                    rowActions={(role) => (
                        <Box
                            sx={{
                                display: "flex",
                                justifyContent: "flex-end",
                                gap: 0.5,
                            }}
                        >
                            {can("assign-permissions") && (
                                <Tooltip title="Edit permissions">
                                    <IconButton
                                        size="small"
                                        onClick={() => {
                                            openAssignDialog(role);
                                        }}
                                    >
                                        <KeyIcon fontSize="small" />
                                    </IconButton>
                                </Tooltip>
                            )}
                            {can("edit-roles") && (
                                <Tooltip title="Edit">
                                    <IconButton
                                        size="small"
                                        onClick={() => {
                                            openRoleDialog(role);
                                        }}
                                    >
                                        <EditIcon fontSize="small" />
                                    </IconButton>
                                </Tooltip>
                            )}
                            {can("delete-roles") && (
                                <Tooltip title="Delete">
                                    <IconButton
                                        size="small"
                                        color="error"
                                        onClick={() => {
                                            handleDeleteRole(role);
                                        }}
                                    >
                                        <DeleteOutlineIcon fontSize="small" />
                                    </IconButton>
                                </Tooltip>
                            )}
                        </Box>
                    )}
                />
            )}

            {activeTab === 1 && (
                <DataTable
                    columns={permissionColumns}
                    data={permissions}
                    emptyMessage="No permissions found."
                    rowActions={(permission) => (
                        <Box
                            sx={{
                                display: "flex",
                                justifyContent: "flex-end",
                                gap: 0.5,
                            }}
                        >
                            {can("edit-permissions") && (
                                <Tooltip title="Edit">
                                    <IconButton
                                        size="small"
                                        onClick={() => {
                                            openPermissionDialog(permission);
                                        }}
                                    >
                                        <EditIcon fontSize="small" />
                                    </IconButton>
                                </Tooltip>
                            )}
                            {can("delete-permissions") && (
                                <Tooltip title="Delete">
                                    <IconButton
                                        size="small"
                                        color="error"
                                        onClick={() => {
                                            handleDeletePermission(permission);
                                        }}
                                    >
                                        <DeleteOutlineIcon fontSize="small" />
                                    </IconButton>
                                </Tooltip>
                            )}
                        </Box>
                    )}
                />
            )}

            <RoleFormDialog
                open={roleDialogOpen}
                role={editingRole}
                onClose={() => {
                    setRoleDialogOpen(false);
                }}
            />

            <PermissionFormDialog
                open={permissionDialogOpen}
                permission={editingPermission}
                onClose={() => {
                    setPermissionDialogOpen(false);
                }}
            />

            <RolePermissionsDialog
                open={assignOpen}
                role={assigningRole}
                permissions={permissions}
                onClose={() => {
                    setAssignOpen(false);
                }}
            />

            <ConfirmDialog {...dialogProps} />
        </AdminLayout>
    );
}
