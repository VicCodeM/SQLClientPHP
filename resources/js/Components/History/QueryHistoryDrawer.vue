<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { History, Play, Trash2, X, Search, CheckCircle, XCircle, Clock } from 'lucide-vue-next';

const props = defineProps<{
    show: boolean;
    connectionId?: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'useQuery', sql: string): void;
    (e: 'notify', msg: string): void;
}>();

const historyList = ref<any[]>([]);
const search = ref('');
const statusFilter = ref('');
const isLoading = ref(false);

async function loadHistory() {
    isLoading.value = true;
    try {
        let url = `/api/v1/history?per_page=50`;
        if (props.connectionId) url += `&connection_id=${props.connectionId}`;
        if (search.value) url += `&search=${encodeURIComponent(search.value)}`;
        if (statusFilter.value) url += `&status=${statusFilter.value}`;

        const response = await fetch(url);
        const data = await response.json();
        if (response.ok && data.success) {
            historyList.value = data.data;
        }
    } catch (e: any) {
        emit('notify', `Error al cargar historial: ${e.message}`);
    } finally {
        isLoading.value = false;
    }
}

function selectQuery(sql: string) {
    emit('useQuery', sql);
    emit('close');
}

onMounted(() => {
    loadHistory();
});
</script>

<template>
    <div v-if="show" class="fixed inset-y-0 right-0 z-50 w-96 bg-slate-900 border-l border-slate-800 shadow-2xl flex flex-col font-mono text-xs text-slate-200">
        <!-- Header -->
        <div class="h-12 bg-slate-950 border-b border-slate-800 px-4 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2 font-bold text-slate-100">
                <History class="w-4 h-4 text-blue-400" />
                <span>Historial de Consultas</span>
            </div>
            <button @click="$emit('close')" class="text-slate-400 hover:text-white p-1">
                <X class="w-4 h-4" />
            </button>
        </div>

        <!-- Filter bar -->
        <div class="p-3 border-b border-slate-800 space-y-2 bg-slate-900/50">
            <div class="relative">
                <Search class="w-3.5 h-3.5 text-slate-500 absolute left-2.5 top-2" />
                <input
                    v-model="search"
                    @input="loadHistory"
                    placeholder="Buscar SQL..."
                    class="w-full bg-slate-950 border border-slate-800 pl-8 pr-2 py-1 rounded text-slate-200 outline-none focus:border-blue-500"
                />
            </div>
            <div class="flex items-center gap-2">
                <select v-model="statusFilter" @change="loadHistory" class="bg-slate-950 border border-slate-800 px-2 py-1 rounded text-slate-300 w-full outline-none">
                    <option value="">Todos los estados</option>
                    <option value="success">Solo Exitosas</option>
                    <option value="error">Solo Errores</option>
                </select>
            </div>
        </div>

        <!-- History Items -->
        <div class="flex-1 overflow-y-auto p-3 space-y-2.5">
            <div
                v-for="item in historyList"
                :key="item.id"
                class="bg-slate-950 border border-slate-800/80 hover:border-slate-700 p-2.5 rounded flex flex-col gap-2 transition"
            >
                <div class="flex items-center justify-between text-[10px] text-slate-400">
                    <span class="flex items-center gap-1">
                        <CheckCircle v-if="item.status === 'success'" class="w-3 h-3 text-emerald-400" />
                        <XCircle v-else class="w-3 h-3 text-rose-400" />
                        <span>{{ item.duration_ms }} ms</span>
                    </span>
                    <span class="text-slate-500 flex items-center gap-1">
                        <Clock class="w-2.5 h-2.5" /> {{ new Date(item.executed_at).toLocaleTimeString() }}
                    </span>
                </div>

                <pre class="text-[11px] text-slate-200 bg-slate-900 p-2 rounded max-h-20 overflow-x-auto whitespace-pre-wrap font-mono">{{ item.query_text }}</pre>

                <div class="flex items-center justify-between pt-1">
                    <span class="text-[10px] text-slate-500">{{ item.affected_rows }} filas</span>
                    <button
                        @click="selectQuery(item.query_text)"
                        class="flex items-center gap-1 text-[11px] bg-blue-600 hover:bg-blue-500 text-white px-2 py-0.5 rounded transition"
                    >
                        <Play class="w-3 h-3 fill-current" />
                        <span>Usar</span>
                    </button>
                </div>
            </div>
            <div v-if="historyList.length === 0" class="text-center text-slate-500 py-8">
                No se encontraron consultas en el historial.
            </div>
        </div>
    </div>
</template>
