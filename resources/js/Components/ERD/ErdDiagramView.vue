<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Database, Table, Key, RefreshCw, Layers, ArrowRight } from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        connectionId: string;
        schema?: string;
    }>(),
    {
        schema: 'public',
    }
);

const emit = defineEmits<{
    (e: 'notify', msg: string): void;
}>();

const isLoading = ref(false);
const nodes = ref<any[]>([]);
const edges = ref<any[]>([]);

async function loadErd() {
    if (!props.connectionId) return;

    isLoading.value = true;
    try {
        const response = await fetch(`/api/v1/connections/${props.connectionId}/schema/erd?schema=${props.schema}`);
        const data = await response.json();

        if (response.ok && data.success) {
            nodes.value = data.data.nodes;
            edges.value = data.data.edges;
        } else {
            emit('notify', `Error al cargar ERD: ${data.message}`);
        }
    } catch (e: any) {
        emit('notify', `Error de red: ${e.message}`);
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    loadErd();
});
</script>

<template>
    <div class="flex flex-col h-full w-full bg-slate-950 text-slate-200 font-mono text-xs overflow-hidden select-none">
        <!-- Top Toolbar -->
        <div class="h-10 bg-slate-900 border-b border-slate-800 px-3 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
                <Layers class="w-4 h-4 text-blue-400" />
                <span class="font-bold text-slate-200">Diagrama Entidad-Relación (ERD)</span>
                <span class="text-[11px] bg-slate-800 px-2 py-0.5 rounded text-slate-400">Esquema: {{ schema }}</span>
            </div>

            <div class="flex items-center gap-3">
                <span class="text-slate-400 text-[11px]">
                    Tablas: <strong class="text-blue-400">{{ nodes.length }}</strong> | Relaciones FK: <strong class="text-amber-400">{{ edges.length }}</strong>
                </span>
                <button @click="loadErd" :disabled="isLoading" class="p-1.5 hover:bg-slate-800 rounded transition text-slate-300">
                    <RefreshCw :class="['w-4 h-4', isLoading ? 'animate-spin text-blue-400' : '']" />
                </button>
            </div>
        </div>

        <!-- ERD Canvas -->
        <div class="flex-1 overflow-auto p-6 bg-radial from-slate-900/40 to-slate-950">
            <!-- Relationships summary cards -->
            <div v-if="edges.length > 0" class="mb-6 flex flex-wrap gap-2">
                <div v-for="edge in edges" :key="edge.id" class="bg-slate-900/80 border border-slate-800 px-2.5 py-1 rounded flex items-center gap-1.5 text-[11px]">
                    <span class="text-blue-400 font-semibold">{{ edge.source }}.{{ edge.source_columns.join(',') }}</span>
                    <ArrowRight class="w-3 h-3 text-amber-400" />
                    <span class="text-emerald-400 font-semibold">{{ edge.target }}.{{ edge.target_columns.join(',') }}</span>
                    <span class="text-[9px] bg-slate-800 px-1 py-0.5 rounded text-slate-400 uppercase ml-1">{{ edge.on_delete }}</span>
                </div>
            </div>

            <!-- Grid of Table Nodes -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                <div
                    v-for="node in nodes"
                    :key="node.id"
                    class="bg-slate-900/90 border border-slate-800 hover:border-slate-700 rounded-lg shadow-xl overflow-hidden flex flex-col transition hover:scale-[1.01]"
                >
                    <!-- Table Header -->
                    <div class="bg-slate-800/80 px-3 py-2 border-b border-slate-700/80 flex items-center justify-between">
                        <div class="flex items-center gap-2 font-bold text-slate-100">
                            <Table class="w-4 h-4 text-blue-400" />
                            <span>{{ node.name }}</span>
                        </div>
                        <span class="text-[10px] text-slate-400 bg-slate-950/60 px-1.5 py-0.5 rounded">
                            ~{{ node.estimated_rows }} filas
                        </span>
                    </div>

                    <!-- Column list -->
                    <div class="p-2 space-y-1 max-h-64 overflow-y-auto">
                        <div
                            v-for="col in node.columns"
                            :key="col.name"
                            class="flex items-center justify-between px-2 py-1 rounded hover:bg-slate-800/50 text-[11px]"
                        >
                            <div class="flex items-center gap-1.5">
                                <Key v-if="col.is_primary" class="w-3 h-3 text-amber-400 fill-amber-400/20 shrink-0" />
                                <span :class="col.is_primary ? 'text-amber-300 font-semibold' : 'text-slate-300'">{{ col.name }}</span>
                            </div>
                            <span class="text-[10px] text-slate-500 font-mono">{{ col.full_type }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
