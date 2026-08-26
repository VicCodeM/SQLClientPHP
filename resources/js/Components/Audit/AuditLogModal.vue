<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Shield, X, RefreshCw, Clock, Globe } from 'lucide-vue-next';

const props = defineProps<{
    show: boolean;
    connectionId?: string;
}>();

defineEmits<{
    (e: 'close'): void;
}>();

const logs = ref<any[]>([]);
const isLoading = ref(false);

async function loadLogs() {
    isLoading.value = true;
    try {
        let url = `/api/v1/audit-logs?per_page=40`;
        if (props.connectionId) url += `&connection_id=${props.connectionId}`;

        const response = await fetch(url);
        const data = await response.json();
        if (response.ok && data.success) {
            logs.value = data.data;
        }
    } catch (e) {
        // error handling
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    loadLogs();
});
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm font-mono text-xs select-none">
        <div class="bg-slate-900 border border-slate-700 rounded-lg w-full max-w-3xl shadow-2xl flex flex-col max-h-[85vh] overflow-hidden text-slate-200">
            <!-- Header -->
            <div class="h-12 bg-slate-950 border-b border-slate-800 px-4 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2 font-bold text-slate-100">
                    <Shield class="w-4 h-4 text-purple-400" />
                    <span>Pista de Auditoría de Seguridad</span>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="loadLogs" class="p-1 hover:bg-slate-800 rounded text-slate-400">
                        <RefreshCw :class="['w-4 h-4', isLoading ? 'animate-spin' : '']" />
                    </button>
                    <button @click="$emit('close')" class="text-slate-400 hover:text-white p-1">
                        <X class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- Audit Logs Table -->
            <div class="flex-1 overflow-y-auto p-4 space-y-3">
                <div
                    v-for="l in logs"
                    :key="l.id"
                    class="bg-slate-950 border border-slate-800/80 p-3 rounded-lg flex flex-col gap-2"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-purple-950 text-purple-300 border border-purple-800">
                                {{ l.action }}
                            </span>
                            <span class="text-slate-300 font-semibold">{{ l.user?.name || 'Sistema' }}</span>
                        </div>
                        <div class="flex items-center gap-3 text-slate-500 text-[10px]">
                            <span class="flex items-center gap-1"><Globe class="w-3 h-3" /> {{ l.ip_address || '127.0.0.1' }}</span>
                            <span class="flex items-center gap-1"><Clock class="w-3 h-3" /> {{ new Date(l.created_at).toLocaleString() }}</span>
                        </div>
                    </div>

                    <pre class="bg-slate-900 p-2 rounded text-slate-300 text-[11px] overflow-auto max-h-32">{{ JSON.stringify(l.details, null, 2) }}</pre>
                </div>

                <div v-if="logs.length === 0" class="text-center text-slate-500 py-12">
                    No hay registros de auditoría aún.
                </div>
            </div>
        </div>
    </div>
</template>
