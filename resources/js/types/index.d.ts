export interface User {
    id: string;
    name: string;
    email: string;
    is_master_admin: boolean;
}

export interface Workspace {
    id: string;
    name: string;
    slug: string;
    description?: string;
    settings?: Record<string, any>;
}

export interface ConnectionGroup {
    id: string;
    workspace_id: string;
    name: string;
    color?: string;
    sort_order: number;
}

export interface Connection {
    id: string;
    workspace_id: string;
    group_id?: string;
    ssh_tunnel_id?: string;
    name: string;
    driver: 'pgsql' | 'mysql' | 'sqlite' | 'sqlcipher' | 'sqlsrv';
    host?: string;
    port?: number;
    database_name: string;
    username?: string;
    is_read_only: boolean;
    use_ssh_tunnel: boolean;
    environment: 'development' | 'staging' | 'production';
    color_tag?: string;
}

export interface QueryTab {
    id: string;
    title: string;
    type: 'sql' | 'table_data' | 'erd' | 'table_designer';
    connectionId: string;
    databaseName: string;
    schemaName?: string;
    tableName?: string;
    queryText: string;
    result?: QueryResult;
    isExecuting: boolean;
    isDirty: boolean;
}

export interface QueryResult {
    columns: string[];
    rows: Record<string, any>[];
    affected_rows: number;
    duration_ms: number;
    is_select: boolean;
    message?: string;
}

export interface SchemaObjectNode {
    id: string;
    name: string;
    type: 'database' | 'schema' | 'table' | 'view' | 'function' | 'trigger' | 'sequence';
    driver?: string;
    schemaName?: string;
    databaseName?: string;
    children?: SchemaObjectNode[];
    isExpanded?: boolean;
    isLoading?: boolean;
}
