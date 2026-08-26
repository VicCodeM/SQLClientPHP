<script setup lang="ts">
import { ref, computed } from 'vue';
import { Plus, Trash2, Key, Code, Save, RefreshCw } from 'lucide-vue-next';

interface ColumnDef {
    name: string;
    type: string;
    is_nullable: boolean;
    is_primary: boolean;
    is_auto_increment: boolean;
    is_unique: boolean;
    default_value: string;
}

interface ForeignKeyDef {
    name: string;
    column: string;
    foreign_table: string;
    foreign_column: string;
    on_delete: string;
    on_update: string;
}

const props = withDefaults(
    defineProps<{
        connectionId: string;
        schema?: string;
        readOnly?: boolean;
    }>(),
    {
        schema: 'public',
        readOnly: false,
    }
);

const emit = defineEmits<{
    (e: 'notify', msg: string): void;
    (e: 'created', tableName: string): void;
}>();

const tableName = ref('new_table');
const isSubmitting = ref(false);

const dataTypes = [
    'UUID',
    'BIGINT',
    'INTEGER',
    'VARCHAR(255)',
    'TEXT',
    'BOOLEAN',
    'DECIMAL(12,2)',
    'TIMESTAMP',
    'JSONB',
    'DATE',
];

const columns = ref<ColumnDef[]>([
    {
        name: 'id',
        type: 'UUID',
        is_nullable: false,
        is_primary: true,
        is_auto_increment: false,
        is_unique: true,
        default_value: 'gen_random_uuid()',
    },
    {
        name: 'created_at',
        type: 'TIMESTAMP',
        is_nullable: false,
        is_primary: false,
        is_auto_increment: false,
        is_unique: false,
        default_value: 'CURRENT_TIMESTAMP',
    },
]);

const foreignKeys = ref<ForeignKeyDef[]>([]);

function addColumn() {
    columns.value.push({
        name: `column_${columns.value.length + 1}`,
        type: 'VARCHAR(255)',
        is_nullable: true,
        is_primary: false,
        is_auto_increment: false,
        is_unique: false,
        default_value: '',
    });
}

function removeColumn(index: number) {
    if (columns.value.length > 1) {
        columns.value.splice(index, 1);
    }
}

function addForeignKey() {
    foreignKeys.value.push({
        name: '',
        column: columns.value[0]?.name || 'id',
        foreign_table: 'users',
        foreign_column: 'id',
        on_delete: 'CASCADE',
        on_update: 'CASCADE',
    });
}

function removeForeignKey(index: number) {
    foreignKeys.value.splice(index, 1);
}

// Live DDL preview
const generatedDdl = computed(() => {
    const lines: string[] = [];
    const pkCols: string[] = [];

    for (const c of columns.value) {
        if (!c.name) continue;
        let line = `  "${c.name}" ${c.type}`;
        if (!c.is_nullable) line += ' NOT NULL';
        if (c.default_value) line += ` DEFAULT ${c.default_value}`;
        if (c.is_unique && !c.is_primary) line += ' UNIQUE';
        if (c.is_primary) pkCols.push(`"${c.name}"`);
        lines.push(line);
    }

    if (pkCols.length > 0) {
        lines.push(`  PRIMARY KEY (${pkCols.join(', ')})`);
    }

    for (const fk of foreignKeys.value) {
        if (fk.column && fk.foreign_table && fk.foreign_column) {
            const name = fk.name || `fk_${tableName.value}_${fk.column}`;
            lines.push(`  CONSTRAINT "${name}" FOREIGN KEY ("${fk.column}") REFERENCES "${fk.foreign_table}" ("${fk.foreign_column}") ON UPDATE ${fk.on_update} ON DELETE ${fk.on_delete}`);
        }
    }

    return `CREATE TABLE "${props.schema}"."${tableName.value}" (\n${lines.join(',\n')}\n);`;
});

async function createTableOnServer() {
    if (props.readOnly || isSubmitting.value) return;

    isSubmitting.value = true;
    try {
        const response = await fetch(`/api/v1/connections/${props.connectionId}/table/create`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                table_name: tableName.value,
                schema: props.schema,
                columns: columns.value,
                foreign_keys: foreignKeys.value,
            }),
        });

        const data = await response.json();
        if (response.ok && data.success) {
            emit('notify', `¡Tabla '${tableName.value}' creada con éxito! 🎉`);
            emit('created', tableName.value);
        } else {
            emit('notify', `Error: ${data.message}`);
        }
    } catch (e: any) {
        emit('notify', `Error de red: ${e.message}`);
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <div class="flex h-full w-full bg-slate-950 text-slate-200 font-mono text-xs overflow-hidden select-none">
        <!-- Left: Designer form -->
        <div class="w-7/12 flex flex-col border-r border-slate-800 overflow-hidden">
            <!-- Header bar -->
            <div class="h-10 bg-slate-900 border-b border-slate-800 px-3 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2">
                    <span class="text-slate-400 font-semibold">Nombre de la Tabla:</span>
                    <input
                        v-model="tableName"
                        placeholder="nombre_tabla"
                        class="bg-slate-950 border border-slate-700 text-blue-400 font-bold px-2 py-0.5 rounded outline-none focus:border-blue-500"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="addColumn"
                        class="flex items-center gap-1 bg-slate-800 hover:bg-slate-700 text-slate-200 px-2.5 py-1 rounded transition"
                    >
                        <Plus class="w-3.5 h-3.5 text-emerald-400" />
                        <span>Añadir Columna</span>
                    </button>
                    <button
                        @click="addForeignKey"
                        class="flex items-center gap-1 bg-slate-800 hover:bg-slate-700 text-slate-200 px-2.5 py-1 rounded transition"
                    >
                        <Plus class="w-3.5 h-3.5 text-amber-400" />
                        <span>Añadir FK</span>
                    </button>
                    <button
                        v-if="!readOnly"
                        @click="createTableOnServer"
                        :disabled="isSubmitting"
                        class="flex items-center gap-1.5 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white font-semibold px-3 py-1 rounded transition shadow-sm"
                    >
                        <Save v-if="!isSubmitting" class="w-3.5 h-3.5" />
                        <RefreshCw v-else class="w-3.5 h-3.5 animate-spin" />
                        <span>Crear Tabla</span>
                    </button>
                </div>
            </div>

            <!-- Columns Table -->
            <div class="flex-1 overflow-auto p-3 space-y-4">
                <div>
                    <h4 class="font-semibold text-slate-400 uppercase text-[11px] mb-2 flex items-center gap-1.5">
                        <span>Columnas ({{ columns.length }})</span>
                    </h4>
                    <table class="w-full text-left border-collapse border border-slate-800 bg-slate-900/60 rounded">
                        <thead class="bg-slate-900 text-slate-400 border-b border-slate-800 text-[11px]">
                            <tr>
                                <th class="p-2 border-r border-slate-800">Nombre</th>
                                <th class="p-2 border-r border-slate-800">Tipo</th>
                                <th class="p-2 border-r border-slate-800 text-center">PK</th>
                                <th class="p-2 border-r border-slate-800 text-center">Null</th>
                                <th class="p-2 border-r border-slate-800 text-center">Unique</th>
                                <th class="p-2 border-r border-slate-800">Default</th>
                                <th class="p-2 text-center w-10">✕</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(col, idx) in columns" :key="idx" class="border-b border-slate-800/60 hover:bg-slate-800/30">
                                <td class="p-1.5 border-r border-slate-800">
                                    <input v-model="col.name" class="w-full bg-slate-950 border border-slate-800 px-1.5 py-0.5 rounded text-slate-100 outline-none" />
                                </td>
                                <td class="p-1.5 border-r border-slate-800">
                                    <select v-model="col.type" class="w-full bg-slate-950 border border-slate-800 px-1 py-0.5 rounded text-slate-100 outline-none">
                                        <option v-for="dt in dataTypes" :key="dt" :value="dt">{{ dt }}</option>
                                    </select>
                                </td>
                                <td class="p-1.5 border-r border-slate-800 text-center">
                                    <input type="checkbox" v-model="col.is_primary" class="rounded bg-slate-950 border-slate-700 text-blue-600 focus:ring-0" />
                                </td>
                                <td class="p-1.5 border-r border-slate-800 text-center">
                                    <input type="checkbox" v-model="col.is_nullable" class="rounded bg-slate-950 border-slate-700 text-blue-600 focus:ring-0" />
                                </td>
                                <td class="p-1.5 border-r border-slate-800 text-center">
                                    <input type="checkbox" v-model="col.is_unique" class="rounded bg-slate-950 border-slate-700 text-blue-600 focus:ring-0" />
                                </td>
                                <td class="p-1.5 border-r border-slate-800">
                                    <input v-model="col.default_value" placeholder="NULL" class="w-full bg-slate-950 border border-slate-800 px-1.5 py-0.5 rounded text-slate-300 outline-none" />
                                </td>
                                <td class="p-1.5 text-center">
                                    <button @click="removeColumn(idx)" class="text-slate-500 hover:text-red-400 p-0.5">
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Foreign keys section -->
                <div v-if="foreignKeys.length > 0">
                    <h4 class="font-semibold text-slate-400 uppercase text-[11px] mb-2 flex items-center gap-1.5">
                        <Key class="w-3 h-3 text-amber-400" />
                        <span>Claves Foráneas ({{ foreignKeys.length }})</span>
                    </h4>
                    <div v-for="(fk, fkIdx) in foreignKeys" :key="fkIdx" class="bg-slate-900 border border-slate-800 p-2.5 rounded mb-2 flex items-center gap-2">
                        <input v-model="fk.column" placeholder="Columna origen" class="bg-slate-950 border border-slate-800 px-2 py-1 rounded text-slate-200 w-32" />
                        <span class="text-slate-500">→</span>
                        <input v-model="fk.foreign_table" placeholder="Tabla destino" class="bg-slate-950 border border-slate-800 px-2 py-1 rounded text-slate-200 w-32" />
                        <input v-model="fk.foreign_column" placeholder="Columna destino" class="bg-slate-950 border border-slate-800 px-2 py-1 rounded text-slate-200 w-28" />
                        <select v-model="fk.on_delete" class="bg-slate-950 border border-slate-800 px-2 py-1 rounded text-slate-300 text-xs">
                            <option value="CASCADE">CASCADE</option>
                            <option value="SET NULL">SET NULL</option>
                            <option value="RESTRICT">RESTRICT</option>
                            <option value="NO ACTION">NO ACTION</option>
                        </select>
                        <button @click="removeForeignKey(fkIdx)" class="text-slate-500 hover:text-red-400 p-1">
                            <Trash2 class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Live DDL Preview -->
        <div class="w-5/12 flex flex-col bg-slate-900/70 overflow-hidden">
            <div class="h-10 bg-slate-900 border-b border-slate-800 px-3 flex items-center gap-2 text-slate-400 font-semibold shrink-0">
                <Code class="w-4 h-4 text-blue-400" />
                <span>Previsualización DDL en Vivo</span>
            </div>
            <div class="flex-1 p-3 overflow-auto">
                <pre class="text-emerald-400 text-xs font-mono bg-slate-950 p-3 rounded border border-slate-800/80 leading-relaxed overflow-x-auto whitespace-pre">{{ generatedDdl }}</pre>
            </div>
        </div>
    </div>
</template>
