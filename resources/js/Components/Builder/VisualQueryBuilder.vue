<script setup lang="ts">
import { ref, computed } from 'vue';
import { Play, Plus, Trash2, Code, Database, Filter, ArrowUpDown } from 'lucide-vue-next';

interface JoinClause {
    type: 'INNER JOIN' | 'LEFT JOIN' | 'RIGHT JOIN';
    table: string;
    leftColumn: string;
    rightColumn: string;
}

interface WhereClause {
    column: string;
    operator: '=' | '!=' | '>' | '<' | '>=' | '<=' | 'LIKE' | 'ILIKE' | 'IS NULL' | 'IS NOT NULL';
    value: string;
    boolean: 'AND' | 'OR';
}

const props = withDefaults(
    defineProps<{
        connectionId: string;
        schema?: string;
        availableTables?: string[];
    }>(),
    {
        schema: 'public',
        availableTables: () => ['users', 'orders', 'products', 'categories'],
    }
);

const emit = defineEmits<{
    (e: 'openInEditor', sql: string): void;
    (e: 'notify', msg: string): void;
}>();

const mainTable = ref(props.availableTables[0] || 'users');
const selectedColumns = ref('*');
const joins = ref<JoinClause[]>([]);
const whereClauses = ref<WhereClause[]>([]);
const orderByColumn = ref('id');
const orderByDirection = ref<'ASC' | 'DESC'>('ASC');
const limit = ref(50);
const offset = ref(0);

function addJoin() {
    joins.value.push({
        type: 'INNER JOIN',
        table: props.availableTables[1] || 'orders',
        leftColumn: 'user_id',
        rightColumn: 'id',
    });
}

function removeJoin(idx: number) {
    joins.value.splice(idx, 1);
}

function addWhere() {
    whereClauses.value.push({
        column: 'id',
        operator: '=',
        value: '1',
        boolean: 'AND',
    });
}

function removeWhere(idx: number) {
    whereClauses.value.splice(idx, 1);
}

const generatedSql = computed(() => {
    let sql = `SELECT ${selectedColumns.value}\nFROM "${props.schema}"."${mainTable.value}"`;

    for (const j of joins.value) {
        if (j.table && j.leftColumn && j.rightColumn) {
            sql += `\n${j.type} "${props.schema}"."${j.table}" ON "${j.table}"."${j.leftColumn}" = "${mainTable.value}"."${j.rightColumn}"`;
        }
    }

    if (whereClauses.value.length > 0) {
        const conditions = whereClauses.value.map((w, idx) => {
            const prefix = idx > 0 ? `  ${w.boolean} ` : 'WHERE ';
            if (w.operator === 'IS NULL' || w.operator === 'IS NOT NULL') {
                return `${prefix}"${w.column}" ${w.operator}`;
            }
            const formattedVal = isNaN(Number(w.value)) ? `'${w.value}'` : w.value;
            return `${prefix}"${w.column}" ${w.operator} ${formattedVal}`;
        });
        sql += `\n${conditions.join('\n')}`;
    }

    if (orderByColumn.value) {
        sql += `\nORDER BY "${orderByColumn.value}" ${orderByDirection.value}`;
    }

    if (limit.value > 0) {
        sql += `\nLIMIT ${limit.value}`;
    }

    if (offset.value > 0) {
        sql += ` OFFSET ${offset.value}`;
    }

    return sql + ';';
});

function handleOpenInEditor() {
    emit('openInEditor', generatedSql.value);
}
</script>

<template>
    <div class="flex h-full w-full bg-slate-950 text-slate-200 font-mono text-xs overflow-hidden select-none">
        <!-- Left: Builder Controls -->
        <div class="w-7/12 flex flex-col border-r border-slate-800 overflow-y-auto p-4 space-y-4">
            <!-- Main Table & Columns -->
            <div class="bg-slate-900/80 border border-slate-800 p-3 rounded-lg space-y-3">
                <h4 class="font-bold text-slate-100 flex items-center gap-1.5 text-xs">
                    <Database class="w-4 h-4 text-blue-400" />
                    <span>Tabla Principal & Selección de Campos</span>
                </h4>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[11px] text-slate-400 block mb-1">Tabla Base</label>
                        <select v-model="mainTable" class="w-full bg-slate-950 border border-slate-800 p-1.5 rounded text-slate-200 outline-none">
                            <option v-for="t in availableTables" :key="t" :value="t">{{ t }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[11px] text-slate-400 block mb-1">Columnas (Separadas por coma)</label>
                        <input v-model="selectedColumns" placeholder="*" class="w-full bg-slate-950 border border-slate-800 p-1.5 rounded text-slate-200 outline-none" />
                    </div>
                </div>
            </div>

            <!-- JOINs section -->
            <div class="bg-slate-900/80 border border-slate-800 p-3 rounded-lg space-y-3">
                <div class="flex items-center justify-between">
                    <h4 class="font-bold text-slate-100 flex items-center gap-1.5 text-xs">
                        <span>Uniones (JOINs)</span>
                    </h4>
                    <button @click="addJoin" class="flex items-center gap-1 text-[11px] bg-slate-800 hover:bg-slate-700 px-2 py-0.5 rounded text-slate-200">
                        <Plus class="w-3 h-3 text-emerald-400" />
                        <span>Añadir JOIN</span>
                    </button>
                </div>
                <div v-if="joins.length === 0" class="text-slate-500 text-[11px]">No hay JOINs configurados.</div>
                <div v-for="(j, idx) in joins" :key="idx" class="flex items-center gap-2 bg-slate-950 p-2 rounded border border-slate-800 text-[11px]">
                    <select v-model="j.type" class="bg-slate-900 border border-slate-800 p-1 rounded text-blue-400">
                        <option value="INNER JOIN">INNER</option>
                        <option value="LEFT JOIN">LEFT</option>
                        <option value="RIGHT JOIN">RIGHT</option>
                    </select>
                    <input v-model="j.table" placeholder="Tabla" class="bg-slate-900 border border-slate-800 p-1 rounded text-slate-200 w-24" />
                    <span class="text-slate-500">ON</span>
                    <input v-model="j.leftColumn" placeholder="join_col" class="bg-slate-900 border border-slate-800 p-1 rounded text-slate-200 w-24" />
                    <span class="text-slate-500">=</span>
                    <input v-model="j.rightColumn" placeholder="base_col" class="bg-slate-900 border border-slate-800 p-1 rounded text-slate-200 w-24" />
                    <button @click="removeJoin(idx)" class="text-slate-500 hover:text-red-400 p-1">
                        <Trash2 class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>

            <!-- WHERE Conditions -->
            <div class="bg-slate-900/80 border border-slate-800 p-3 rounded-lg space-y-3">
                <div class="flex items-center justify-between">
                    <h4 class="font-bold text-slate-100 flex items-center gap-1.5 text-xs">
                        <Filter class="w-3.5 h-3.5 text-amber-400" />
                        <span>Filtros (WHERE)</span>
                    </h4>
                    <button @click="addWhere" class="flex items-center gap-1 text-[11px] bg-slate-800 hover:bg-slate-700 px-2 py-0.5 rounded text-slate-200">
                        <Plus class="w-3 h-3 text-emerald-400" />
                        <span>Añadir Filtro</span>
                    </button>
                </div>
                <div v-if="whereClauses.length === 0" class="text-slate-500 text-[11px]">Sin filtros WHERE.</div>
                <div v-for="(w, idx) in whereClauses" :key="idx" class="flex items-center gap-2 bg-slate-950 p-2 rounded border border-slate-800 text-[11px]">
                    <select v-if="idx > 0" v-model="w.boolean" class="bg-slate-900 border border-slate-800 p-1 rounded text-amber-400 font-semibold">
                        <option value="AND">AND</option>
                        <option value="OR">OR</option>
                    </select>
                    <input v-model="w.column" placeholder="Columna" class="bg-slate-900 border border-slate-800 p-1 rounded text-slate-200 w-28" />
                    <select v-model="w.operator" class="bg-slate-900 border border-slate-800 p-1 rounded text-slate-200">
                        <option value="=">=</option>
                        <option value="!=">!=</option>
                        <option value=">">&gt;</option>
                        <option value="<">&lt;</option>
                        <option value=">=">&gt;=</option>
                        <option value="<=">&lt;=</option>
                        <option value="LIKE">LIKE</option>
                        <option value="IS NULL">IS NULL</option>
                        <option value="IS NOT NULL">IS NOT NULL</option>
                    </select>
                    <input v-if="w.operator !== 'IS NULL' && w.operator !== 'IS NOT NULL'" v-model="w.value" placeholder="Valor" class="bg-slate-900 border border-slate-800 p-1 rounded text-slate-200 flex-1" />
                    <button @click="removeWhere(idx)" class="text-slate-500 hover:text-red-400 p-1">
                        <Trash2 class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>

            <!-- Order & Limit -->
            <div class="bg-slate-900/80 border border-slate-800 p-3 rounded-lg grid grid-cols-3 gap-3">
                <div>
                    <label class="text-[11px] text-slate-400 block mb-1 flex items-center gap-1">
                        <ArrowUpDown class="w-3 h-3" /> Ordenar Por
                    </label>
                    <input v-model="orderByColumn" placeholder="id" class="w-full bg-slate-950 border border-slate-800 p-1.5 rounded text-slate-200 outline-none" />
                </div>
                <div>
                    <label class="text-[11px] text-slate-400 block mb-1">Dirección</label>
                    <select v-model="orderByDirection" class="w-full bg-slate-950 border border-slate-800 p-1.5 rounded text-slate-200 outline-none">
                        <option value="ASC">ASC</option>
                        <option value="DESC">DESC</option>
                    </select>
                </div>
                <div>
                    <label class="text-[11px] text-slate-400 block mb-1">Límite</label>
                    <input type="number" v-model.number="limit" class="w-full bg-slate-950 border border-slate-800 p-1.5 rounded text-slate-200 outline-none" />
                </div>
            </div>
        </div>

        <!-- Right: Generated SQL & Actions -->
        <div class="w-5/12 flex flex-col bg-slate-900/70 overflow-hidden">
            <div class="h-10 bg-slate-900 border-b border-slate-800 px-3 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2 text-slate-300 font-semibold">
                    <Code class="w-4 h-4 text-emerald-400" />
                    <span>SQL Generado</span>
                </div>
                <button
                    @click="handleOpenInEditor"
                    class="flex items-center gap-1.5 bg-blue-600 hover:bg-blue-500 text-white font-semibold px-3 py-1 rounded transition text-xs shadow-sm"
                >
                    <Play class="w-3 h-3 fill-current" />
                    <span>Abrir en Editor</span>
                </button>
            </div>
            <div class="flex-1 p-3 overflow-auto">
                <pre class="text-emerald-400 text-xs font-mono bg-slate-950 p-3 rounded border border-slate-800/80 leading-relaxed overflow-x-auto whitespace-pre">{{ generatedSql }}</pre>
            </div>
        </div>
    </div>
</template>
