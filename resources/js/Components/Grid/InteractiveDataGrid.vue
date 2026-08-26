<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import {
    Save,
    RotateCcw,
    Plus,
    Trash2,
    RefreshCw,
    ChevronLeft,
    ChevronRight,
    Search,
    Key,
    Check,
    AlertCircle,
    X
} from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        connectionId: string;
        schema?: string;
        table: string;
        readOnly?: boolean;
    }>(),
    {
        schema: 'public',
        readOnly: false,
    }
);

const emit = defineEmits<{
    (e: 'notify', msg: string): void;
}>();

const isLoading = ref(false);
const columns = ref<string[]>([]);
const primaryKeys = ref<string[]>([]);
const rows = ref<Record<string, any>[]>([]);
const pagination = ref({
    current_page: 1,
    per_page: 50,
    total_rows: 0,
    total_pages: 1,
});

const sortBy = ref<string | null>(null);
const sortDir = ref<'asc' | 'desc'>('asc');
const selectedRowIndex = ref<number | null>(null);

// Inline editing state
const editingCell = ref<{ rowIndex: number; column: string; value: any } | null>(null);
const dirtyChanges = ref<Map<string, { rowIndex: number; column: string; originalValue: any; newValue: any }>>(new Map());

// Insert modal state
const showInsertModal = ref(false);
const newRowData = ref<Record<string, any>>({});

async function loadData(page = 1) {
    if (!props.connectionId || !props.table) return;

    isLoading.value = true;
    try {
        const response = await fetch(`/api/v1/connections/${props.connectionId}/table/data`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                schema: props.schema,
                table: props.table,
                page: page,
                per_page: pagination.value.per_page,
                sort_by: sortBy.value,
                sort_dir: sortDir.value,
            }),
        });

        const data = await response.json();
        if (response.ok && data.success) {
            columns.value = data.data.columns;
            primaryKeys.value = data.data.primary_keys;
            rows.value = data.data.rows;
            pagination.value = data.data.pagination;
            dirtyChanges.value.clear();
            editingCell.value = null;
        }
    } catch (e: any) {
        emit('notify', `Error cargando datos: ${e.message}`);
    } finally {
        isLoading.value = false;
    }
}

function handleSort(col: string) {
    if (sortBy.value === col) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = col;
        sortDir.value = 'asc';
    }
    loadData(1);
}

function startCellEdit(rowIndex: number, col: string) {
    if (props.readOnly) return;
    editingCell.value = {
        rowIndex,
        column: col,
        value: rows.value[rowIndex][col],
    };
}

function commitCellEdit() {
    if (!editingCell.value) return;

    const { rowIndex, column, value } = editingCell.value;
    const originalValue = rows.value[rowIndex][column];

    if (originalValue !== value) {
        const key = `${rowIndex}_${column}`;
        dirtyChanges.value.set(key, {
            rowIndex,
            column,
            originalValue,
            newValue: value,
        });
        rows.value[rowIndex][column] = value;
    }

    editingCell.value = null;
}

function isCellDirty(rowIndex: number, col: string): boolean {
    return dirtyChanges.value.has(`${rowIndex}_${col}`);
}

async function saveAllDirtyChanges() {
    if (props.readOnly || dirtyChanges.value.size === 0) return;

    isLoading.value = true;
    let savedCount = 0;

    // Group changes by row index
    const changesByRow = new Map<number, Record<string, any>>();
    dirtyChanges.value.forEach((change) => {
        if (!changesByRow.has(change.rowIndex)) {
            changesByRow.set(change.rowIndex, {});
        }
        changesByRow.get(change.rowIndex)![change.column] = change.newValue;
    });

    try {
        for (const [rIdx, updatedValues] of changesByRow.entries()) {
            const row = rows.value[rIdx];
            const pks: Record<string, any> = {};

            if (primaryKeys.value.length === 0) {
                emit('notify', 'No se puede editar: la tabla no cuenta con clave primaria (PK).');
                return;
            }

            for (const pkCol of primaryKeys.value) {
                pks[pkCol] = row[pkCol];
            }

            const response = await fetch(`/api/v1/connections/${props.connectionId}/table/row/update`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    schema: props.schema,
                    table: props.table,
                    primary_keys: pks,
                    updated_values: updatedValues,
                }),
            });

            const resData = await response.json();
            if (response.ok && resData.success) {
                savedCount++;
            } else {
                emit('notify', `Error guardando fila: ${resData.message}`);
                return;
            }
        }

        dirtyChanges.value.clear();
        emit('notify', `¡${savedCount} fila(s) guardada(s) exitosamente! 💾`);
    } catch (e: any) {
        emit('notify', `Error al persistir cambios: ${e.message}`);
    } finally {
        isLoading.value = false;
    }
}

function revertChanges() {
    dirtyChanges.value.forEach((change) => {
        rows.value[change.rowIndex][change.column] = change.originalValue;
    });
    dirtyChanges.value.clear();
    editingCell.value = null;
    emit('notify', 'Cambios revertidos al estado original.');
}

async function deleteSelectedRow() {
    if (props.readOnly || selectedRowIndex.value === null) return;

    if (primaryKeys.value.length === 0) {
        emit('notify', 'No se puede eliminar: la tabla no cuenta con clave primaria (PK).');
        return;
    }

    const row = rows.value[selectedRowIndex.value];
    const pks: Record<string, any> = {};
    for (const pkCol of primaryKeys.value) {
        pks[pkCol] = row[pkCol];
    }

    if (!confirm('¿Estás seguro de eliminar el registro seleccionado? Esta acción es irreversible.')) {
        return;
    }

    isLoading.value = true;
    try {
        const response = await fetch(`/api/v1/connections/${props.connectionId}/table/row/delete`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                schema: props.schema,
                table: props.table,
                primary_keys: pks,
            }),
        });

        const data = await response.json();
        if (response.ok && data.success) {
            emit('notify', 'Fila eliminada con éxito 🗑️');
            selectedRowIndex.value = null;
            await loadData(pagination.value.current_page);
        } else {
            emit('notify', `Error: ${data.message}`);
        }
    } catch (e: any) {
        emit('notify', `Error de red: ${e.message}`);
    } finally {
        isLoading.value = false;
    }
}

function openInsertModal() {
    newRowData.value = {};
    for (const col of columns.value) {
        newRowData.value[col] = '';
    }
    showInsertModal.value = true;
}

async function submitNewRow() {
    if (props.readOnly) return;

    const valuesToInsert: Record<string, any> = {};
    for (const [k, v] of Object.entries(newRowData.value)) {
        if (v !== '' && v !== null) {
            valuesToInsert[k] = v;
        }
    }

    isLoading.value = true;
    try {
        const response = await fetch(`/api/v1/connections/${props.connectionId}/table/row/insert`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                schema: props.schema,
                table: props.table,
                values: valuesToInsert,
            }),
        });

        const data = await response.json();
        if (response.ok && data.success) {
            emit('notify', 'Nueva fila creada con éxito ➕');
            showInsertModal.value = false;
            await loadData(pagination.value.current_page);
        } else {
            emit('notify', `Error: ${data.message}`);
        }
    } catch (e: any) {
        emit('notify', `Error: ${e.message}`);
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    loadData(1);
});
</script>

<template>
    <div class="flex flex-col h-full w-full bg-slate-950 text-slate-200 overflow-hidden font-mono text-xs select-none">
        <!-- Top Grid Action Bar -->
        <div class="h-10 bg-slate-900 border-b border-slate-800 px-3 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
                <button
                    @click="loadData(pagination.current_page)"
                    :disabled="isLoading"
                    class="p-1.5 text-slate-400 hover:text-white hover:bg-slate-800 rounded transition"
                    title="Recargar datos"
                >
                    <RefreshCw :class="['w-4 h-4', isLoading ? 'animate-spin text-blue-400' : '']" />
                </button>

                <div class="h-4 w-px bg-slate-800" />

                <!-- Add Row Button -->
                <button
                    v-if="!readOnly"
                    @click="openInsertModal"
                    class="flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold px-2.5 py-1 rounded transition"
                >
                    <Plus class="w-3.5 h-3.5" />
                    <span>Nueva Fila</span>
                </button>

                <!-- Delete Selected Row -->
                <button
                    v-if="!readOnly && selectedRowIndex !== null"
                    @click="deleteSelectedRow"
                    class="flex items-center gap-1.5 bg-rose-600/80 hover:bg-rose-600 text-white px-2.5 py-1 rounded transition"
                >
                    <Trash2 class="w-3.5 h-3.5" />
                    <span>Eliminar</span>
                </button>

                <!-- Save / Revert Dirty Changes -->
                <div v-if="dirtyChanges.size > 0" class="flex items-center gap-2 ml-2">
                    <button
                        @click="saveAllDirtyChanges"
                        class="flex items-center gap-1.5 bg-blue-600 hover:bg-blue-500 text-white font-semibold px-2.5 py-1 rounded transition shadow-sm animate-pulse"
                    >
                        <Save class="w-3.5 h-3.5" />
                        <span>Guardar ({{ dirtyChanges.size }})</span>
                    </button>
                    <button
                        @click="revertChanges"
                        class="flex items-center gap-1 text-slate-400 hover:text-slate-200 px-2 py-1 rounded hover:bg-slate-800 transition"
                    >
                        <RotateCcw class="w-3.5 h-3.5" />
                        <span>Descartar</span>
                    </button>
                </div>
            </div>

            <!-- Pagination info and controls -->
            <div class="flex items-center gap-3 text-slate-400">
                <span>
                    Total: <strong class="text-slate-200">{{ pagination.total_rows }}</strong> filas (Pág. {{ pagination.current_page }} de {{ pagination.total_pages }})
                </span>
                <div class="flex items-center gap-1">
                    <button
                        @click="loadData(pagination.current_page - 1)"
                        :disabled="pagination.current_page <= 1"
                        class="p-1 rounded hover:bg-slate-800 disabled:opacity-30 transition"
                    >
                        <ChevronLeft class="w-4 h-4" />
                    </button>
                    <button
                        @click="loadData(pagination.current_page + 1)"
                        :disabled="pagination.current_page >= pagination.total_pages"
                        class="p-1 rounded hover:bg-slate-800 disabled:opacity-30 transition"
                    >
                        <ChevronRight class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Interactive Table Container -->
        <div class="flex-1 overflow-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-900 sticky top-0 z-10 border-b border-slate-800 text-slate-300">
                    <tr>
                        <th class="p-2 border-r border-slate-800/80 text-slate-500 w-12 text-center select-none">#</th>
                        <th
                            v-for="col in columns"
                            :key="col"
                            @click="handleSort(col)"
                            class="p-2 border-r border-slate-800/80 font-semibold cursor-pointer hover:bg-slate-800 transition select-none"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="flex items-center gap-1">
                                    <Key v-if="primaryKeys.includes(col)" class="w-3 h-3 text-amber-400 fill-amber-400/20" />
                                    <span>{{ col }}</span>
                                </span>
                                <span v-if="sortBy === col" class="text-blue-400">
                                    {{ sortDir === 'asc' ? '▲' : '▼' }}
                                </span>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, rIdx) in rows"
                        :key="rIdx"
                        @click="selectedRowIndex = rIdx"
                        :class="[
                            'border-b border-slate-900/90 transition',
                            selectedRowIndex === rIdx ? 'bg-blue-950/40' : 'hover:bg-slate-900/50'
                        ]"
                    >
                        <td class="p-2 border-r border-slate-800/40 text-slate-500 text-center select-none bg-slate-950/40">
                            {{ (pagination.current_page - 1) * pagination.per_page + rIdx + 1 }}
                        </td>
                        <td
                            v-for="col in columns"
                            :key="col"
                            @dblclick="startCellEdit(rIdx, col)"
                            :class="[
                                'p-2 border-r border-slate-800/40 max-w-xs truncate cursor-cell',
                                isCellDirty(rIdx, col) ? 'bg-amber-950/60 text-amber-200 font-semibold border-amber-600' : ''
                            ]"
                        >
                            <!-- Inline input edit mode -->
                            <div v-if="editingCell?.rowIndex === rIdx && editingCell?.column === col">
                                <input
                                    v-model="editingCell.value"
                                    @blur="commitCellEdit"
                                    @keydown.enter="commitCellEdit"
                                    @keydown.esc="editingCell = null"
                                    autofocus
                                    class="w-full bg-slate-900 border border-blue-500 px-1 py-0.5 rounded text-slate-100 outline-none"
                                />
                            </div>
                            <span v-else>
                                {{ row[col] === null ? '<NULL>' : row[col] }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal Nueva Fila -->
        <div v-if="showInsertModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm">
            <div class="bg-slate-900 border border-slate-700 rounded-lg p-5 w-full max-w-md shadow-2xl flex flex-col gap-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="font-bold text-sm text-slate-100 flex items-center gap-2">
                        <Plus class="w-4 h-4 text-emerald-400" />
                        <span>Insertar Nuevo Registro en {{ table }}</span>
                    </h3>
                    <button @click="showInsertModal = false" class="text-slate-400 hover:text-white">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="max-h-80 overflow-y-auto space-y-3 pr-1">
                    <div v-for="col in columns" :key="col" class="flex flex-col gap-1">
                        <label class="text-[11px] font-semibold text-slate-400 flex items-center gap-1">
                            <Key v-if="primaryKeys.includes(col)" class="w-3 h-3 text-amber-400" />
                            <span>{{ col }}</span>
                        </label>
                        <input
                            v-model="newRowData[col]"
                            :placeholder="primaryKeys.includes(col) ? 'Opcional si es auto-generado' : 'Valor...'"
                            class="bg-slate-950 border border-slate-800 text-slate-200 px-2 py-1.5 rounded focus:border-blue-500 outline-none"
                        />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-800 pt-3">
                    <button @click="showInsertModal = false" class="px-3 py-1.5 text-slate-400 hover:text-slate-200 transition">
                        Cancelar
                    </button>
                    <button @click="submitNewRow" class="bg-emerald-600 hover:bg-emerald-500 text-white font-semibold px-4 py-1.5 rounded transition">
                        Insertar Fila
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
