<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Bot, Sparkles, Send, Play, X, Key, Zap, CheckCircle, RefreshCw, Cpu, MessageSquare } from 'lucide-vue-next';

const props = defineProps<{
    show: boolean;
    connectionId?: string;
    currentSql?: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'insertSql', sql: string): void;
    (e: 'notify', msg: string): void;
}>();

const activeTab = ref<'text2sql' | 'optimize' | 'chat' | 'settings'>('text2sql');
const isLoading = ref(false);

// Models and Settings
const models = ref<Array<{ id: string; owned_by: string; context_window?: number }>>([]);
const selectedModel = ref('llama-3.3-70b-versatile');
const customApiKey = ref(localStorage.getItem('sqlclient_groq_key') || '');

// Text to SQL
const promptText = ref('');
const generatedSql = ref('');
const explanation = ref('');

// Optimize
const analysisResult = ref('');
const optimizedSql = ref('');
const suggestedIndexes = ref<string[]>([]);

// Chat
const messages = ref<Array<{ role: 'user' | 'assistant'; content: string }>>([
    {
        role: 'assistant',
        content: '¡Hola! Soy tu copiloto de base de datos impulsado por Groq Cloud. ¿En qué consulta o esquema puedo ayudarte hoy?',
    },
]);
const chatInput = ref('');

function saveApiKey() {
    localStorage.setItem('sqlclient_groq_key', customApiKey.value);
    emit('notify', 'API Key de Groq guardada localmente 🔑');
    loadModels();
}

async function loadModels() {
    try {
        const headers: Record<string, string> = {};
        if (customApiKey.value) {
            headers['X-Groq-Api-Key'] = customApiKey.value;
        }

        const response = await fetch('/api/v1/ai/models', { headers });
        const data = await response.json();
        if (response.ok && data.success) {
            models.value = data.data.models;
            if (data.data.recommended_model) {
                selectedModel.value = data.data.recommended_model;
            }
        }
    } catch (e) {
        // Fallback gracefully
    }
}

async function generateTextToSql() {
    if (!promptText.value || isLoading.value) return;

    isLoading.value = true;
    try {
        const url = props.connectionId
            ? `/api/v1/ai/text-to-sql/${props.connectionId}`
            : `/api/v1/ai/text-to-sql`;

        const headers: Record<string, string> = { 'Content-Type': 'application/json' };
        if (customApiKey.value) headers['X-Groq-Api-Key'] = customApiKey.value;

        const response = await fetch(url, {
            method: 'POST',
            headers,
            body: JSON.stringify({
                prompt: promptText.value,
                model: selectedModel.value,
            }),
        });

        const data = await response.json();
        if (response.ok && data.success) {
            generatedSql.value = data.data.sql;
            explanation.value = data.data.explanation;
        } else {
            emit('notify', `Error de IA: ${data.message}`);
        }
    } catch (e: any) {
        emit('notify', `Error de red: ${e.message}`);
    } finally {
        isLoading.value = false;
    }
}

async function runOptimization() {
    const sqlToOptimize = props.currentSql || generatedSql.value;
    if (!sqlToOptimize || isLoading.value) {
        emit('notify', 'No hay consulta SQL para optimizar.');
        return;
    }

    isLoading.value = true;
    try {
        const url = props.connectionId
            ? `/api/v1/ai/optimize/${props.connectionId}`
            : `/api/v1/ai/optimize`;

        const headers: Record<string, string> = { 'Content-Type': 'application/json' };
        if (customApiKey.value) headers['X-Groq-Api-Key'] = customApiKey.value;

        const response = await fetch(url, {
            method: 'POST',
            headers,
            body: JSON.stringify({
                sql: sqlToOptimize,
                model: selectedModel.value,
            }),
        });

        const data = await response.json();
        if (response.ok && data.success) {
            analysisResult.value = data.data.analysis;
            optimizedSql.value = data.data.optimized_sql || '';
            suggestedIndexes.value = data.data.suggested_indexes || [];
        } else {
            emit('notify', `Error: ${data.message}`);
        }
    } catch (e: any) {
        emit('notify', `Error: ${e.message}`);
    } finally {
        isLoading.value = false;
    }
}

async function sendChatMessage() {
    if (!chatInput.value.trim() || isLoading.value) return;

    const userText = chatInput.value.trim();
    chatInput.value = '';
    messages.value.push({ role: 'user', content: userText });

    isLoading.value = true;
    try {
        const headers: Record<string, string> = { 'Content-Type': 'application/json' };
        if (customApiKey.value) headers['X-Groq-Api-Key'] = customApiKey.value;

        const response = await fetch('/api/v1/ai/chat', {
            method: 'POST',
            headers,
            body: JSON.stringify({
                messages: messages.value,
                model: selectedModel.value,
            }),
        });

        const data = await response.json();
        if (response.ok && data.success) {
            messages.value.push({ role: 'assistant', content: data.data.reply });
        } else {
            messages.value.push({ role: 'assistant', content: `Error: ${data.message}` });
        }
    } catch (e: any) {
        messages.value.push({ role: 'assistant', content: `Error de conexión: ${e.message}` });
    } finally {
        isLoading.value = false;
    }
}

function handleInsert(sql: string) {
    emit('insertSql', sql);
    emit('close');
}

onMounted(() => {
    loadModels();
});
</script>

<template>
    <div v-if="show" class="fixed inset-y-0 right-0 z-50 w-[420px] bg-slate-900 border-l border-slate-800 shadow-2xl flex flex-col font-mono text-xs text-slate-200 select-none">
        <!-- Header -->
        <div class="h-12 bg-slate-950 border-b border-slate-800 px-4 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2 font-bold text-slate-100">
                <Bot class="w-5 h-5 text-indigo-400" />
                <span>Asistente IA <span class="text-[10px] bg-indigo-950 text-indigo-300 border border-indigo-800 px-1.5 py-0.5 rounded font-normal">Groq Cloud</span></span>
            </div>
            <button @click="$emit('close')" class="text-slate-400 hover:text-white p-1">
                <X class="w-4 h-4" />
            </button>
        </div>

        <!-- Mode Navigation Tabs -->
        <div class="h-9 bg-slate-950/80 border-b border-slate-800 flex items-center px-2 gap-1 shrink-0 text-[11px]">
            <button
                @click="activeTab = 'text2sql'"
                :class="['px-2.5 py-1 rounded transition flex items-center gap-1', activeTab === 'text2sql' ? 'bg-indigo-600 text-white font-semibold' : 'text-slate-400 hover:text-slate-200']"
            >
                <Sparkles class="w-3 h-3" />
                <span>Text-to-SQL</span>
            </button>
            <button
                @click="activeTab = 'optimize'"
                :class="['px-2.5 py-1 rounded transition flex items-center gap-1', activeTab === 'optimize' ? 'bg-indigo-600 text-white font-semibold' : 'text-slate-400 hover:text-slate-200']"
            >
                <Zap class="w-3 h-3" />
                <span>Optimizar</span>
            </button>
            <button
                @click="activeTab = 'chat'"
                :class="['px-2.5 py-1 rounded transition flex items-center gap-1', activeTab === 'chat' ? 'bg-indigo-600 text-white font-semibold' : 'text-slate-400 hover:text-slate-200']"
            >
                <MessageSquare class="w-3 h-3" />
                <span>Chat</span>
            </button>
            <button
                @click="activeTab = 'settings'"
                :class="['px-2 py-1 rounded transition ml-auto flex items-center gap-1', activeTab === 'settings' ? 'bg-slate-800 text-white font-semibold' : 'text-slate-400 hover:text-slate-200']"
            >
                <Cpu class="w-3 h-3" />
            </button>
        </div>

        <!-- Tab 1: Text-to-SQL -->
        <div v-if="activeTab === 'text2sql'" class="flex-1 flex flex-col p-4 overflow-y-auto space-y-4">
            <div>
                <label class="text-slate-400 block mb-1.5 font-semibold">¿Qué consulta deseas generar?</label>
                <textarea
                    v-model="promptText"
                    placeholder="Ej: 'Obtener los 10 clientes con más compras en el último mes agrupados por país...'"
                    class="w-full bg-slate-950 border border-slate-800 p-2.5 rounded text-slate-100 placeholder-slate-600 h-24 outline-none focus:border-indigo-500 transition leading-relaxed text-xs"
                />
                <button
                    @click="generateTextToSql"
                    :disabled="isLoading || !promptText"
                    class="mt-2 w-full flex items-center justify-center gap-1.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-semibold py-1.5 rounded transition shadow-sm"
                >
                    <Sparkles v-if="!isLoading" class="w-3.5 h-3.5" />
                    <RefreshCw v-else class="w-3.5 h-3.5 animate-spin" />
                    <span>Generar Consulta SQL</span>
                </button>
            </div>

            <!-- Result -->
            <div v-if="generatedSql" class="bg-slate-950 border border-slate-800 p-3 rounded space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-indigo-400 font-semibold text-[11px]">SQL Generado:</span>
                    <button
                        @click="handleInsert(generatedSql)"
                        class="flex items-center gap-1 bg-blue-600 hover:bg-blue-500 text-white px-2 py-0.5 rounded text-[11px]"
                    >
                        <Play class="w-3 h-3 fill-current" />
                        <span>Insertar en Editor</span>
                    </button>
                </div>
                <pre class="bg-slate-900 p-2.5 rounded text-emerald-400 text-xs overflow-x-auto whitespace-pre-wrap leading-relaxed">{{ generatedSql }}</pre>
                <p v-if="explanation" class="text-slate-400 text-[11px] leading-relaxed italic">{{ explanation }}</p>
            </div>
        </div>

        <!-- Tab 2: Optimize -->
        <div v-else-if="activeTab === 'optimize'" class="flex-1 flex flex-col p-4 overflow-y-auto space-y-4">
            <div>
                <label class="text-slate-400 block mb-1.5 font-semibold">Consulta a Analizar:</label>
                <pre class="bg-slate-950 border border-slate-800 p-2 rounded text-slate-300 text-[11px] max-h-24 overflow-auto">{{ currentSql || 'Escribe o ejecuta una consulta en el editor.' }}</pre>
                <button
                    @click="runOptimization"
                    :disabled="isLoading || !currentSql"
                    class="mt-2 w-full flex items-center justify-center gap-1.5 bg-amber-600 hover:bg-amber-500 disabled:opacity-50 text-white font-semibold py-1.5 rounded transition shadow-sm"
                >
                    <Zap v-if="!isLoading" class="w-3.5 h-3.5" />
                    <RefreshCw v-else class="w-3.5 h-3.5 animate-spin" />
                    <span>Analizar Rendimiento</span>
                </button>
            </div>

            <div v-if="analysisResult" class="bg-slate-950 border border-slate-800 p-3 rounded space-y-3">
                <div>
                    <h5 class="text-amber-400 font-bold text-[11px] mb-1">Diagnóstico:</h5>
                    <p class="text-slate-300 text-[11px] leading-relaxed whitespace-pre-wrap">{{ analysisResult }}</p>
                </div>

                <div v-if="suggestedIndexes.length > 0">
                    <h5 class="text-indigo-400 font-bold text-[11px] mb-1">Índices Recomendados:</h5>
                    <div v-for="(idx, i) in suggestedIndexes" :key="i" class="bg-slate-900 p-1.5 rounded text-emerald-400 text-[10px] mb-1 font-mono">
                        {{ idx }}
                    </div>
                </div>

                <div v-if="optimizedSql">
                    <div class="flex items-center justify-between mb-1">
                        <h5 class="text-emerald-400 font-bold text-[11px]">SQL Reescrito:</h5>
                        <button @click="handleInsert(optimizedSql)" class="text-blue-400 hover:underline text-[10px]">Insertar</button>
                    </div>
                    <pre class="bg-slate-900 p-2 rounded text-emerald-400 text-xs overflow-auto">{{ optimizedSql }}</pre>
                </div>
            </div>
        </div>

        <!-- Tab 3: Chat -->
        <div v-else-if="activeTab === 'chat'" class="flex-1 flex flex-col overflow-hidden">
            <div class="flex-1 overflow-y-auto p-4 space-y-3">
                <div
                    v-for="(m, idx) in messages"
                    :key="idx"
                    :class="[
                        'p-2.5 rounded-lg text-xs leading-relaxed max-w-[90%]',
                        m.role === 'user'
                            ? 'ml-auto bg-indigo-600 text-white rounded-br-none'
                            : 'mr-auto bg-slate-950 border border-slate-800 text-slate-200 rounded-bl-none'
                    ]"
                >
                    <p class="whitespace-pre-wrap">{{ m.content }}</p>
                </div>
            </div>

            <!-- Chat input bar -->
            <div class="p-3 bg-slate-950 border-t border-slate-800 flex items-center gap-2">
                <input
                    v-model="chatInput"
                    @keyup.enter="sendChatMessage"
                    placeholder="Pregúntame sobre SQL, JOINs, tuning..."
                    class="flex-1 bg-slate-900 border border-slate-800 px-3 py-1.5 rounded text-slate-100 outline-none focus:border-indigo-500 text-xs"
                />
                <button
                    @click="sendChatMessage"
                    :disabled="isLoading || !chatInput.trim()"
                    class="bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 p-2 rounded text-white"
                >
                    <Send class="w-3.5 h-3.5" />
                </button>
            </div>
        </div>

        <!-- Tab 4: Settings -->
        <div v-else-if="activeTab === 'settings'" class="flex-1 p-4 space-y-4">
            <div>
                <label class="text-slate-400 block mb-1 font-semibold">Modelo de Groq Cloud Activo:</label>
                <select v-model="selectedModel" class="w-full bg-slate-950 border border-slate-800 p-2 rounded text-slate-200 outline-none">
                    <option v-for="m in models" :key="m.id" :value="m.id">
                        {{ m.id }} ({{ m.owned_by }})
                    </option>
                </select>
            </div>

            <div>
                <label class="text-slate-400 block mb-1 font-semibold">API Key Personal (Groq Console):</label>
                <div class="flex gap-2">
                    <input
                        type="password"
                        v-model="customApiKey"
                        placeholder="gsk_..."
                        class="flex-1 bg-slate-950 border border-slate-800 p-2 rounded text-slate-200 outline-none"
                    />
                    <button @click="saveApiKey" class="bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded font-semibold text-xs">
                        Guardar
                    </button>
                </div>
                <p class="text-[10px] text-slate-500 mt-1">Obtén tu API Key gratuita en <a href="https://console.groq.com" target="_blank" class="text-indigo-400 underline">console.groq.com</a></p>
            </div>
        </div>
    </div>
</template>
