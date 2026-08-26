<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import MonacoSqlEditor from '@/Components/Editor/MonacoSqlEditor.vue';
import InteractiveDataGrid from '@/Components/Grid/InteractiveDataGrid.vue';
import TableDesigner from '@/Components/Designer/TableDesigner.vue';
import ErdDiagramView from '@/Components/ERD/ErdDiagramView.vue';
import VisualQueryBuilder from '@/Components/Builder/VisualQueryBuilder.vue';
import type { Connection, QueryTab } from '@/types';
import {
    Play,
    Plus,
    X,
    Database,
    Table,
    Layers,
    Shield,
    Terminal,
    Check,
    FileJson,
    FileSpreadsheet,
    Code,
    RefreshCw,
    Network,
    Sliders,
    PenTool
} from 'lucide-vue-next';

const props = defineProps<{
    connections: Connection[];
    activeConnectionId?: string;
}>();

const selectedConnectionId = ref<string>(
    props.activeConnectionId || props.connections[0]?.id || ''
);

const activeConnection = computed(() =>
    props.connections.find((c) => c.id === selectedConnectionId.value)
);

const tabs = ref<QueryTab[]>([
    {
        id: 'tab-1',
        title: 'Consulta 1.sql',
        type: 'sql',
        connectionId: selectedConnectionId.value,
        databaseName: activeConnection.value?.database_name || 'main',
        queryText: 'SELECT 1 AS id, \'Bienvenido a SQLClient Web\' AS message, NOW() AS timestamp;',
        isExecuting: false,
        isDirty: false,
    },
]);

const activeTabId = ref<string>('tab-1');

const activeTab = computed(() =>
    tabs.value.find((t) => t.id === activeTabId.value)
);

const toastMessage = ref<string | null>(null);

function showToast(msg: string) {
    toastMessage.value = msg;
    setTimeout(() => {
        toastMessage.value = null;
    }, 2500);
}

function createNewTab(sql = 'SELECT * FROM users LIMIT 50;') {
    const newId = `tab-${Date.now()}`;
    tabs.value.push({
        id: newId,
        title: `Consulta ${tabs.value.length + 1}.sql`,
        type: 'sql',
        connectionId: selectedConnectionId.value,
        databaseName: activeConnection.value?.database_name || 'main',
        queryText: sql,
        isExecuting: false,
        isDirty: false,
    });
    activeTabId.value = newId;
}

function openTableDataTab(tableName: string) {
    const existingTab = tabs.value.find(
        (t) => t.type === 'table_data' && t.tableName === tableName && t.connectionId === selectedConnectionId.value
    );

    if (existingTab) {
        activeTabId.value = existingTab.id;
        return;
    }

    const newId = `table-data-${tableName}-${Date.now()}`;
    tabs.value.push({
        id: newId,
        title: `${tableName} (Datos)`,
        type: 'table_data',
        connectionId: selectedConnectionId.value,
        databaseName: activeConnection.value?.database_name || 'main',
        tableName: tableName,
        queryText: '',
        isExecuting: false,
        isDirty: false,
    });
    activeTabId.value = newId;
}

function openErdTab() {
    const existing = tabs.value.find((t) => t.type === 'erd' && t.connectionId === selectedConnectionId.value);
    if (existing) {
        activeTabId.value = existing.id;
        return;
    }

    const newId = `erd-${Date.now()}`;
    tabs.value.push({
        id: newId,
        title: 'Diagrama ERD',
        type: 'erd',
        connectionId: selectedConnectionId.value,
        databaseName: activeConnection.value?.database_name || 'main',
        queryText: '',
        isExecuting: false,
        isDirty: false,
    });
    activeTabId.value = newId;
}

function openTableDesignerTab() {
    const newId = `designer-${Date.now()}`;
    tabs.value.push({
        id: newId,
        title: 'Diseñador de Tablas',
        type: 'table_designer',
        connectionId: selectedConnectionId.value,
        databaseName: activeConnection.value?.database_name || 'main',
        queryText: '',
        isExecuting: false,
        isDirty: false,
    });
    activeTabId.value = newId;
}

function openQueryBuilderTab() {
    const newId = `builder-${Date.now()}`;
    tabs.value.push({
        id: newId,
        title: 'Query Builder Visual',
        type: 'query_builder',
        connectionId: selectedConnectionId.value,
        databaseName: activeConnection.value?.database_name || 'main',
        queryText: '',
        isExecuting: false,
        isDirty: false,
    });
    activeTabId.value = newId;
}

function closeTab(id: string) {
    if (tabs.value.length === 1) return;
    const idx = tabs.value.findIndex((t) => t.id === id);
    tabs.value = tabs.value.filter((t) => t.id !== id);
    if (activeTabId.value === id) {
        activeTabId.value = tabs.value[Math.max(0, idx - 1)].id;
    }
}

async function executeCurrentQuery() {
    if (!activeTab.value || !activeConnection.value || activeTab.value.type !== 'sql') return;

    activeTab.value.isExecuting = true;

    try {
        const response = await fetch(`/api/v1/connections/${activeConnection.value.id}/query/execute`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                sql: activeTab.value.queryText,
            }),
        });

        const data = await response.json();

        if (response.ok && data.success) {
            activeTab.value.result = data.data;
            showToast(`Ejecutado con éxito en ${data.data.duration_ms} ms`);
        } else {
            showToast(`Error: ${data.message || 'Error en consulta'}`);
        }
    } catch (e: any) {
        showToast(`Error de red: ${e.message}`);
    } finally {
        if (activeTab.value) {
            activeTab.value.isExecuting = false;
        }
    }
}

function copyToClipboard(text: string, label: string) {
    navigator.clipboard.writeText(text);
    showToast(`¡Copiado como ${label}! 📋`);
}

function copyAsJson() {
    if (!activeTab.value?.result?.rows) return;
    const json = JSON.stringify(activeTab.value.result.rows, null, 2);
    copyToClipboard(json, 'JSON');
}

function copyAsCsv() {
    if (!activeTab.value?.result?.rows || !activeTab.value?.result?.columns) return;
    const cols = activeTab.value.result.columns;
    const rows = activeTab.value.result.rows;

    const csvLines = [cols.join(',')];
    for (const r of rows) {
        csvLines.push(cols.map((c) => JSON.stringify(r[c] ?? '')).join(','));
    }
    copyToClipboard(csvLines.join('\n'), 'CSV');
}

function copyAsInsert() {
    if (!activeTab.value?.result?.rows || !activeTab.value?.result?.columns) return;
    const cols = activeTab.value.result.columns;
    const rows = activeTab.value.result.rows;
    const table = activeTab.value.tableName || 'my_table';

    const insertStatements = rows.map((r) => {
        const vals = cols.map((c) => {
            const v = r[c];
            if (v === null || v === undefined) return 'NULL';
            if (typeof v === 'number') return v;
            return `'${String(v).replace(/'/g, "''")}'`;
        });
        return `INSERT INTO "${table}" ("${cols.join('", "')}") VALUES (${vals.join(', ')});`;
    });

    copyToClipboard(insertStatements.join('\n'), 'INSERTs');
}
</script>

<template>
    <Head title="SQL Studio" />

    <div class="flex flex-col h-screen w-screen bg-slate-950 text-slate-200 overflow-hidden">
        <!-- Toast Notification -->
        <transition enter-active-class="transform ease-out duration-300 transition" enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2" enter-to-class="translate-y-0 opacity-100 sm:translate-x-0" leave-active-class="transition ease-in duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="toastMessage" class="fixed bottom-5 right-5 z-50 flex items-center gap-2 bg-blue-600 text-white px-4 py-2.5 rounded-lg shadow-xl border border-blue-500 font-medium text-sm">
                <Check class="w-4 h-4" />
                <span>{{ toastMessage }}</span>
            </div>
        </transition>

        <!-- Top Navigation Header -->
        <header class="h-12 bg-slate-900 border-b border-slate-800 flex items-center justify-between px-3 shrink-0">
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2 font-bold text-blue-400">
                    <Database class="w-5 h-5 text-blue-500" />
                    <span>SQLClient <span class="text-xs bg-blue-900/60 text-blue-300 px-1.5 py-0.5 rounded border border-blue-700/50">Studio</span></span>
                </div>

                <div class="h-4 w-px bg-slate-800" />

                <!-- Connection Selector -->
                <div class="flex items-center gap-2">
                    <select v-model="selectedConnectionId" class="bg-slate-950 border border-slate-800 text-xs text-slate-200 rounded px-2.5 py-1 focus:ring-1 focus:ring-blue-500 outline-none">
                        <option v-for="c in connections" :key="c.id" :value="c.id">
                            {{ c.driver.toUpperCase() }}: {{ c.name }} ({{ c.database_name }})
                        </option>
                    </select>

                    <!-- Environment Badge -->
                    <span v-if="activeConnection" :class="[
                        'text-[10px] font-semibold uppercase px-2 py-0.5 rounded border',
                        activeConnection.environment === 'production' ? 'bg-red-950/80 text-red-400 border-red-800' :
                        activeConnection.environment === 'staging' ? 'bg-amber-950/80 text-amber-400 border-amber-800' :
                        'bg-emerald-950/80 text-emerald-400 border-emerald-800'
                    ]">
                        {{ activeConnection.environment }}
                    </span>

                    <span v-if="activeConnection?.is_read_only" class="text-[10px] font-semibold bg-purple-950/80 text-purple-300 border border-purple-800 px-2 py-0.5 rounded flex items-center gap-1">
                        <Shield class="w-3 h-3" /> Read-Only
                    </span>
                </div>
            </div>

            <!-- Toolbar Actions -->
            <div class="flex items-center gap-1.5">
                <!-- Visual Tools Buttons -->
                <button @click="openErdTab" class="flex items-center gap-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold px-2.5 py-1.5 rounded transition">
                    <Network class="w-3.5 h-3.5 text-blue-400" />
                    <span>ERD</span>
                </button>
                <button @click="openTableDesignerTab" class="flex items-center gap-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold px-2.5 py-1.5 rounded transition">
                    <PenTool class="w-3.5 h-3.5 text-amber-400" />
                    <span>Diseñar Tabla</span>
                </button>
                <button @click="openQueryBuilderTab" class="flex items-center gap-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold px-2.5 py-1.5 rounded transition">
                    <Sliders class="w-3.5 h-3.5 text-emerald-400" />
                    <span>Query Builder</span>
                </button>

                <div class="h-4 w-px bg-slate-800 mx-1" />

                <button v-if="activeTab?.type === 'sql'" @click="executeCurrentQuery" :disabled="activeTab?.isExecuting" class="flex items-center gap-1.5 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-xs font-semibold px-3 py-1.5 rounded transition shadow-sm">
                    <Play v-if="!activeTab?.isExecuting" class="w-3.5 h-3.5 fill-current" />
                    <RefreshCw v-else class="w-3.5 h-3.5 animate-spin" />
                    <span>Ejecutar (F5)</span>
                </button>

                <div class="h-4 w-px bg-slate-800 mx-1" />

                <button @click="copyAsJson" title="Copiar resultados como JSON" class="p-1.5 text-slate-400 hover:text-slate-200 hover:bg-slate-800 rounded transition text-xs flex items-center gap-1">
                    <FileJson class="w-4 h-4 text-amber-400" />
                </button>
                <button @click="copyAsCsv" title="Copiar resultados como CSV" class="p-1.5 text-slate-400 hover:text-slate-200 hover:bg-slate-800 rounded transition text-xs flex items-center gap-1">
                    <FileSpreadsheet class="w-4 h-4 text-emerald-400" />
                </button>
                <button @click="copyAsInsert" title="Copiar como sentencias INSERT" class="p-1.5 text-slate-400 hover:text-slate-200 hover:bg-slate-800 rounded transition text-xs flex items-center gap-1">
                    <Code class="w-4 h-4 text-blue-400" />
                </button>
            </div>
        </header>

        <!-- Main Studio Body -->
        <div class="flex flex-1 overflow-hidden">
            <!-- Sidebar: Segregated Object Explorer -->
            <aside class="w-64 bg-slate-900/90 border-r border-slate-800 flex flex-col shrink-0">
                <div class="p-2.5 border-b border-slate-800 text-xs font-semibold text-slate-400 uppercase tracking-wider flex items-center justify-between">
                    <span>Explorador de Motores</span>
                    <Layers class="w-4 h-4" />
                </div>

                <div class="flex-1 overflow-y-auto p-2 space-y-3 font-mono text-xs">
                    <!-- Section PostgreSQL -->
                    <div class="space-y-1">
                        <div class="flex items-center gap-1.5 text-blue-400 font-semibold px-2 py-1 bg-slate-800/50 rounded">
                            <span>🐘 PostgreSQL</span>
                        </div>
                        <div class="pl-4 space-y-1 text-slate-400">
                            <div class="flex items-center gap-1 hover:text-slate-200 cursor-pointer"><Database class="w-3.5 h-3.5 text-blue-400" /> production_db</div>
                            <div @dblclick="openTableDataTab('users')" class="flex items-center gap-1 hover:text-slate-200 cursor-pointer pl-3"><Table class="w-3 h-3 text-slate-500" /> users</div>
                            <div @dblclick="openTableDataTab('orders')" class="flex items-center gap-1 hover:text-slate-200 cursor-pointer pl-3"><Table class="w-3 h-3 text-slate-500" /> orders</div>
                        </div>
                    </div>

                    <!-- Section MySQL -->
                    <div class="space-y-1">
                        <div class="flex items-center gap-1.5 text-amber-400 font-semibold px-2 py-1 bg-slate-800/50 rounded">
                            <span>🐬 MySQL / MariaDB</span>
                        </div>
                        <div class="pl-4 space-y-1 text-slate-400">
                            <div class="flex items-center gap-1 hover:text-slate-200 cursor-pointer"><Database class="w-3.5 h-3.5 text-amber-400" /> shop_ecommerce</div>
                            <div @dblclick="openTableDataTab('products')" class="flex items-center gap-1 hover:text-slate-200 cursor-pointer pl-3"><Table class="w-3 h-3 text-slate-500" /> products</div>
                        </div>
                    </div>

                    <!-- Section SQLCipher -->
                    <div class="space-y-1">
                        <div class="flex items-center gap-1.5 text-purple-400 font-semibold px-2 py-1 bg-slate-800/50 rounded">
                            <span>🔒 SQLCipher Vault</span>
                        </div>
                        <div class="pl-4 space-y-1 text-slate-400">
                            <div class="flex items-center gap-1 hover:text-slate-200 cursor-pointer"><Database class="w-3.5 h-3.5 text-purple-400" /> encrypted_vault.db</div>
                        </div>
                    </div>

                    <!-- Section SQLite -->
                    <div class="space-y-1">
                        <div class="flex items-center gap-1.5 text-emerald-400 font-semibold px-2 py-1 bg-slate-800/50 rounded">
                            <span>🪶 SQLite</span>
                        </div>
                        <div class="pl-4 space-y-1 text-slate-400">
                            <div class="flex items-center gap-1 hover:text-slate-200 cursor-pointer"><Database class="w-3.5 h-3.5 text-emerald-400" /> local_app.sqlite</div>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Center Panel: Multi-Tab Workspace -->
            <main class="flex-1 flex flex-col overflow-hidden bg-slate-950">
                <!-- Tab Header Bar -->
                <div class="h-9 bg-slate-900/70 border-b border-slate-800 flex items-center px-1 gap-1 overflow-x-auto">
                    <div
                        v-for="t in tabs"
                        :key="t.id"
                        @click="activeTabId = t.id"
                        :class="[
                            'flex items-center gap-2 px-3 py-1 text-xs rounded-t border-t border-x cursor-pointer transition font-mono',
                            activeTabId === t.id
                                ? 'bg-slate-950 text-blue-400 border-slate-700 font-semibold shadow-sm'
                                : 'bg-slate-900/40 text-slate-400 border-transparent hover:text-slate-200 hover:bg-slate-800/50'
                        ]"
                    >
                        <Terminal v-if="t.type === 'sql'" class="w-3.5 h-3.5" />
                        <Table v-else-if="t.type === 'table_data'" class="w-3.5 h-3.5 text-emerald-400" />
                        <Network v-else-if="t.type === 'erd'" class="w-3.5 h-3.5 text-blue-400" />
                        <PenTool v-else-if="t.type === 'table_designer'" class="w-3.5 h-3.5 text-amber-400" />
                        <Sliders v-else-if="t.type === 'query_builder'" class="w-3.5 h-3.5 text-emerald-400" />
                        <span>{{ t.title }}</span>
                        <button @click.stop="closeTab(t.id)" class="hover:text-red-400 p-0.5 rounded">
                            <X class="w-3 h-3" />
                        </button>
                    </div>

                    <button @click="() => createNewTab()" class="p-1 text-slate-400 hover:text-white hover:bg-slate-800 rounded transition ml-1" title="Nueva Pestaña SQL">
                        <Plus class="w-4 h-4" />
                    </button>
                </div>

                <!-- Tab Body: ERD Mode -->
                <div v-if="activeTab?.type === 'erd'" class="flex-1 overflow-hidden">
                    <ErdDiagramView
                        :connection-id="selectedConnectionId"
                        @notify="showToast"
                    />
                </div>

                <!-- Tab Body: Table Designer Mode -->
                <div v-else-if="activeTab?.type === 'table_designer'" class="flex-1 overflow-hidden">
                    <TableDesigner
                        :connection-id="selectedConnectionId"
                        :read-only="activeConnection?.is_read_only"
                        @notify="showToast"
                        @created="(tbl) => { openTableDataTab(tbl); }"
                    />
                </div>

                <!-- Tab Body: Visual Query Builder Mode -->
                <div v-else-if="activeTab?.type === 'query_builder'" class="flex-1 overflow-hidden">
                    <VisualQueryBuilder
                        :connection-id="selectedConnectionId"
                        @notify="showToast"
                        @open-in-editor="(sql) => createNewTab(sql)"
                    />
                </div>

                <!-- Tab Body: Table Data Grid Mode -->
                <div v-else-if="activeTab?.type === 'table_data' && activeTab.tableName" class="flex-1 overflow-hidden">
                    <InteractiveDataGrid
                        :connection-id="selectedConnectionId"
                        :table="activeTab.tableName"
                        :read-only="activeConnection?.is_read_only"
                        @notify="showToast"
                    />
                </div>

                <!-- Tab Body: SQL Editor Mode -->
                <template v-else-if="activeTab?.type === 'sql'">
                    <!-- Monaco SQL Editor Component -->
                    <div class="h-1/2 min-h-[200px]">
                        <MonacoSqlEditor
                            v-model="activeTab.queryText"
                            :read-only="activeConnection?.is_read_only"
                            @execute="executeCurrentQuery"
                        />
                    </div>

                    <!-- Bottom Results Grid -->
                    <div class="flex-1 flex flex-col bg-slate-950 overflow-hidden border-t border-slate-800">
                        <!-- Status summary bar -->
                        <div class="h-7 bg-slate-900/60 border-b border-slate-800/80 px-3 flex items-center justify-between text-[11px] text-slate-400 font-mono">
                            <div class="flex items-center gap-3">
                                <span v-if="activeTab?.result">
                                    Filas: <strong class="text-blue-400">{{ activeTab.result.affected_rows }}</strong> | Latencia: <strong class="text-emerald-400">{{ activeTab.result.duration_ms }} ms</strong>
                                </span>
                                <span v-else>Listo para ejecutar consulta.</span>
                            </div>
                        </div>

                        <!-- Data Grid for query results -->
                        <div class="flex-1 overflow-auto font-mono text-xs">
                            <table v-if="activeTab?.result?.rows?.length" class="w-full text-left border-collapse">
                                <thead class="bg-slate-900 sticky top-0 border-b border-slate-800 text-slate-300">
                                    <tr>
                                        <th class="p-2 border-r border-slate-800/60 text-slate-500 w-12 text-center">#</th>
                                        <th v-for="col in activeTab.result.columns" :key="col" class="p-2 border-r border-slate-800/60 font-semibold">
                                            {{ col }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, rIdx) in activeTab.result.rows" :key="rIdx" class="border-b border-slate-900/80 hover:bg-slate-900/50 transition">
                                        <td class="p-2 border-r border-slate-800/40 text-slate-500 text-center select-none bg-slate-950/40">{{ rIdx + 1 }}</td>
                                        <td v-for="col in activeTab.result.columns" :key="col" class="p-2 border-r border-slate-800/40 text-slate-200 max-w-xs truncate">
                                            {{ row[col] === null ? '<NULL>' : row[col] }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div v-else class="h-full flex items-center justify-center text-slate-500 text-xs">
                                No hay resultados para mostrar. Presiona F5 o 'Ejecutar'.
                            </div>
                        </div>
                    </div>
                </template>
            </main>
        </div>
    </div>
</template>
