<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Bookmark, Plus, Trash2, X, Play, Tag, Search, Save } from 'lucide-vue-next';

const props = defineProps<{
    show: boolean;
    currentSql?: string;
    workspaceId?: string;
    connectionId?: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'useSnippet', sql: string): void;
    (e: 'notify', msg: string): void;
}>();

const snippets = ref<any[]>([]);
const search = ref('');
const showCreateForm = ref(false);

const newSnippet = ref({
    title: '',
    description: '',
    query_text: props.currentSql || '',
    tags: '',
});

async function loadSnippets() {
    try {
        let url = `/api/v1/snippets`;
        if (search.value) url += `?search=${encodeURIComponent(search.value)}`;

        const response = await fetch(url);
        const data = await response.json();
        if (response.ok && data.success) {
            snippets.value = data.data;
        }
    } catch (e: any) {
        emit('notify', `Error: ${e.message}`);
    }
}

async function saveSnippet() {
    if (!newSnippet.value.title || !newSnippet.value.query_text) {
        emit('notify', 'Por favor ingresa título y consulta SQL.');
        return;
    }

    try {
        const response = await fetch('/api/v1/snippets', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                workspace_id: props.workspaceId || '00000000-0000-0000-0000-000000000000',
                connection_id: props.connectionId,
                title: newSnippet.value.title,
                description: newSnippet.value.description,
                query_text: newSnippet.value.query_text,
                tags: newSnippet.value.tags ? newSnippet.value.tags.split(',').map((t) => t.trim()) : [],
            }),
        });

        const data = await response.json();
        if (response.ok && data.success) {
            emit('notify', 'Snippet guardado exitosamente 🔖');
            showCreateForm.value = false;
            newSnippet.value = { title: '', description: '', query_text: '', tags: '' };
            await loadSnippets();
        } else {
            emit('notify', `Error: ${data.message}`);
        }
    } catch (e: any) {
        emit('notify', `Error: ${e.message}`);
    }
}

async function deleteSnippet(id: string) {
    if (!confirm('¿Eliminar este snippet?')) return;
    try {
        const response = await fetch(`/api/v1/snippets/${id}`, { method: 'DELETE' });
        const data = await response.json();
        if (response.ok && data.success) {
            emit('notify', 'Snippet eliminado 🗑️');
            await loadSnippets();
        }
    } catch (e: any) {
        emit('notify', `Error: ${e.message}`);
    }
}

function useSnippetSql(sql: string) {
    emit('useSnippet', sql);
    emit('close');
}

onMounted(() => {
    loadSnippets();
});
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm font-mono text-xs select-none">
        <div class="bg-slate-900 border border-slate-700 rounded-lg w-full max-w-2xl shadow-2xl flex flex-col max-h-[85vh] overflow-hidden text-slate-200">
            <!-- Header -->
            <div class="h-12 bg-slate-950 border-b border-slate-800 px-4 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2 font-bold text-slate-100">
                    <Bookmark class="w-4 h-4 text-amber-400" />
                    <span>Snippets y Consultas Guardadas</span>
                </div>
                <button @click="$emit('close')" class="text-slate-400 hover:text-white">
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Toolbar -->
            <div class="p-3 bg-slate-900/60 border-b border-slate-800 flex items-center justify-between gap-3">
                <div class="relative flex-1">
                    <Search class="w-3.5 h-3.5 text-slate-500 absolute left-2.5 top-2" />
                    <input
                        v-model="search"
                        @input="loadSnippets"
                        placeholder="Buscar por título, tag o código SQL..."
                        class="w-full bg-slate-950 border border-slate-800 pl-8 pr-2 py-1 rounded text-slate-200 outline-none focus:border-blue-500"
                    />
                </div>
                <button
                    @click="showCreateForm = !showCreateForm"
                    class="flex items-center gap-1.5 bg-blue-600 hover:bg-blue-500 text-white font-semibold px-3 py-1 rounded transition shrink-0"
                >
                    <Plus class="w-3.5 h-3.5" />
                    <span>Nuevo Snippet</span>
                </button>
            </div>

            <!-- Create Form Drawer -->
            <div v-if="showCreateForm" class="p-4 bg-slate-950 border-b border-slate-800 space-y-3">
                <h4 class="font-bold text-slate-100">Guardar Nueva Consulta</h4>
                <div class="grid grid-cols-2 gap-2">
                    <input v-model="newSnippet.title" placeholder="Título del snippet..." class="bg-slate-900 border border-slate-800 p-1.5 rounded text-slate-100" />
                    <input v-model="newSnippet.tags" placeholder="Tags (ej: users, analytics, reports)" class="bg-slate-900 border border-slate-800 p-1.5 rounded text-slate-100" />
                </div>
                <textarea v-model="newSnippet.description" placeholder="Descripción breve..." class="w-full bg-slate-900 border border-slate-800 p-1.5 rounded text-slate-200 h-14 outline-none" />
                <textarea v-model="newSnippet.query_text" placeholder="SELECT * FROM ..." class="w-full bg-slate-900 border border-slate-800 p-2 rounded text-emerald-400 font-mono h-24 outline-none" />
                <div class="flex justify-end gap-2">
                    <button @click="showCreateForm = false" class="px-3 py-1 text-slate-400 hover:text-white">Cancelar</button>
                    <button @click="saveSnippet" class="flex items-center gap-1 bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1 rounded font-semibold">
                        <Save class="w-3.5 h-3.5" />
                        <span>Guardar</span>
                    </button>
                </div>
            </div>

            <!-- Snippets List -->
            <div class="flex-1 overflow-y-auto p-4 space-y-3">
                <div
                    v-for="s in snippets"
                    :key="s.id"
                    class="bg-slate-950 border border-slate-800 hover:border-slate-700 p-3 rounded-lg flex flex-col gap-2 transition"
                >
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-slate-100 text-xs">{{ s.title }}</span>
                        <div class="flex items-center gap-1.5">
                            <button @click="useSnippetSql(s.query_text)" class="flex items-center gap-1 bg-blue-600 hover:bg-blue-500 text-white px-2 py-0.5 rounded text-[11px]">
                                <Play class="w-3 h-3 fill-current" />
                                <span>Abrir en Editor</span>
                            </button>
                            <button @click="deleteSnippet(s.id)" class="text-slate-500 hover:text-red-400 p-1">
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                    <p v-if="s.description" class="text-slate-400 text-[11px]">{{ s.description }}</p>
                    <pre class="bg-slate-900 p-2 rounded text-emerald-400 text-[11px] whitespace-pre-wrap max-h-24 overflow-auto">{{ s.query_text }}</pre>
                    <div v-if="s.tags && s.tags.length" class="flex flex-wrap gap-1 mt-1">
                        <span v-for="t in s.tags" :key="t" class="bg-slate-900 text-slate-400 px-1.5 py-0.5 rounded text-[9px] flex items-center gap-1">
                            <Tag class="w-2.5 h-2.5 text-amber-400" /> {{ t }}
                        </span>
                    </div>
                </div>
                <div v-if="snippets.length === 0" class="text-center text-slate-500 py-8">
                    No hay snippets guardados.
                </div>
            </div>
        </div>
    </div>
</template>
