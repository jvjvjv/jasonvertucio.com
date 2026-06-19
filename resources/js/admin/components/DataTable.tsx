import Card from "@mui/material/Card";
import Skeleton from "@mui/material/Skeleton";
import Table from "@mui/material/Table";
import TableBody from "@mui/material/TableBody";
import TableCell from "@mui/material/TableCell";
import TableContainer from "@mui/material/TableContainer";
import TableHead from "@mui/material/TableHead";
import TableRow from "@mui/material/TableRow";
import TableSortLabel from "@mui/material/TableSortLabel";

import type { PaginationLink } from "@/types";
import type { SxProps, Theme } from "@mui/material/styles";
import type { ReactNode } from "react";

import EmptyTableRow from "@/admin/components/EmptyTableRow";
import Pagination from "@/admin/components/Pagination";

export interface ColumnDef<T> {
    key: string;
    label: ReactNode;
    render?: (row: T) => ReactNode;
    sortable?: boolean;
    align?: "left" | "center" | "right";
    width?: string;
}

export interface DataTableProps<T extends { id: number | string }> {
    columns: ColumnDef<T>[];
    data: T[];
    rowActions?: (row: T) => ReactNode;
    loading?: boolean;
    emptyMessage?: string;
    emptyState?: ReactNode;
    pagination?: {
        links: PaginationLink[];
        last_page: number;
    };
    sorting?: {
        column: string;
        direction: "asc" | "desc";
        onChange: (column: string, direction: "asc" | "desc") => void;
    };
    onRowClick?: (row: T) => void;
    rowSx?: (row: T) => SxProps<Theme>;
    stickyHeader?: boolean;
    size?: "small" | "medium";
}

const SKELETON_ROWS = 5;

export default function DataTable<T extends { id: number | string }>({
    columns,
    data,
    rowActions,
    loading = false,
    emptyMessage,
    emptyState,
    pagination,
    sorting,
    onRowClick,
    rowSx,
    stickyHeader = false,
    size = "small",
}: DataTableProps<T>) {
    const totalCols = columns.length + (rowActions ? 1 : 0);

    const handleSortClick = (key: string) => {
        if (!sorting) {
            return;
        }
        const direction =
            sorting.column === key && sorting.direction === "asc"
                ? "desc"
                : "asc";
        sorting.onChange(key, direction);
    };

    return (
        <Card>
            <TableContainer
                sx={{
                    maxHeight: stickyHeader ? "70vh" : undefined,
                    overflowX: "auto",
                }}
            >
                <Table
                    size={size}
                    stickyHeader={stickyHeader}
                    sx={{ minWidth: 500 }}
                >
                    <TableHead>
                        <TableRow>
                            {columns.map((col) => (
                                <TableCell
                                    key={col.key}
                                    align={col.align ?? "left"}
                                    width={col.width}
                                >
                                    {col.sortable && sorting ? (
                                        <TableSortLabel
                                            active={sorting.column === col.key}
                                            direction={
                                                sorting.column === col.key
                                                    ? sorting.direction
                                                    : "asc"
                                            }
                                            onClick={() => {
                                                handleSortClick(col.key);
                                            }}
                                        >
                                            {col.label}
                                        </TableSortLabel>
                                    ) : (
                                        col.label
                                    )}
                                </TableCell>
                            ))}
                            {rowActions && (
                                <TableCell align="right">Actions</TableCell>
                            )}
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {loading ? (
                            Array.from({ length: SKELETON_ROWS }).map(
                                (_, i) => (
                                    <TableRow key={i}>
                                        {Array.from({
                                            length: totalCols,
                                        }).map((__, j) => (
                                            <TableCell key={j}>
                                                <Skeleton variant="text" />
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ),
                            )
                        ) : data.length === 0 ? (
                            emptyState ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={totalCols}
                                        sx={{ p: 0, border: 0 }}
                                    >
                                        {emptyState}
                                    </TableCell>
                                </TableRow>
                            ) : (
                                <EmptyTableRow
                                    colSpan={totalCols}
                                    message={
                                        emptyMessage ?? "No results found."
                                    }
                                />
                            )
                        ) : (
                            data.map((row) => (
                                <TableRow
                                    key={row.id}
                                    hover
                                    onClick={
                                        onRowClick
                                            ? () => {
                                                  onRowClick(row);
                                              }
                                            : undefined
                                    }
                                    sx={{
                                        ...(onRowClick
                                            ? { cursor: "pointer" }
                                            : {}),
                                        ...((rowSx?.(row) as object) ?? {}),
                                    }}
                                >
                                    {columns.map((col) => (
                                        <TableCell
                                            key={col.key}
                                            align={col.align ?? "left"}
                                        >
                                            {col.render
                                                ? col.render(row)
                                                : ((
                                                      row as {
                                                          [
                                                              key: string
                                                          ]: unknown;
                                                      }
                                                  )[col.key] as ReactNode)}
                                        </TableCell>
                                    ))}
                                    {rowActions && (
                                        <TableCell align="right">
                                            {rowActions(row)}
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </TableContainer>
            {pagination && (
                <Pagination
                    links={pagination.links}
                    lastPage={pagination.last_page}
                />
            )}
        </Card>
    );
}
