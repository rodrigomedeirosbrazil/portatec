import * as React from 'react';

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { EmptyState } from '@/components/empty-state';
import { cn } from '@/lib/utils';

export interface DataTableColumn<T> {
    /** Chave estável usada como React key da coluna (não precisa ser uma propriedade de `T`). */
    key: string;
    header: React.ReactNode;
    /** Renderiza o conteúdo da célula para a linha. */
    render: (row: T) => React.ReactNode;
    className?: string;
    headerClassName?: string;
}

export interface DataTableProps<T> {
    columns: DataTableColumn<T>[];
    data: T[];
    /** Extrai uma chave estável de React para cada linha. */
    rowKey: (row: T) => string | number;
    /** Quando definido, a linha inteira vira clicável (mantendo os elementos internos, ex.: links). */
    onRowClick?: (row: T) => void;
    /** Mensagem exibida via `<EmptyState>` quando `data` está vazio. */
    emptyMessage: string;
    /** Ação opcional do estado vazio (ex.: um `<Link>` "Novo ..."), já traduzida pelo chamador. */
    emptyAction?: React.ReactNode;
    className?: string;
}

/**
 * Tabela de dados genérica sobre o tipo da linha: colunas declarativas e
 * tipadas, render customizável por coluna, linha clicável opcional e estado
 * vazio via `<EmptyState>`. Agnóstica de domínio — sem lógica de Place,
 * Device ou Booking aqui.
 */
export function DataTable<T>({ columns, data, rowKey, onRowClick, emptyMessage, emptyAction, className }: DataTableProps<T>) {
    if (data.length === 0) {
        return <EmptyState message={emptyMessage} action={emptyAction} />;
    }

    return (
        <div className={cn('overflow-x-auto rounded-[10px] border border-border bg-card', className)}>
            <Table>
                <TableHeader>
                    <TableRow>
                        {columns.map((column) => (
                            <TableHead key={column.key} className={column.headerClassName}>
                                {column.header}
                            </TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {data.map((row) => (
                        <TableRow
                            key={rowKey(row)}
                            onClick={onRowClick ? () => onRowClick(row) : undefined}
                            className={cn(onRowClick && 'cursor-pointer')}
                        >
                            {columns.map((column) => (
                                <TableCell key={column.key} className={column.className}>
                                    {column.render(row)}
                                </TableCell>
                            ))}
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
