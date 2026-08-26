<script setup lang="ts">
import { ref, watch } from 'vue';
import { Database, X, Zap, Save, RefreshCw, CheckCircle, AlertTriangle } from 'lucide-vue-next';

const props = defineProps<{
    show: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'created', connection: any): void;
    (e: 'notify', msg: string): void;
}>();

const isTesting = ref(false);
const isSaving = ref(false);
const testResult = ref<{ success: boolean; message: string; latency_ms?: number } | null>(null);

const form = ref({
    name: '',
    driver: 'pgsql',
    host: '127.0.0.1',
    port: 5432,
    database_name: 'postgres',
    username: 'postgres',
    password: '',
    environment: 'development',
    is_read_only: false,
    color_tag: '#3b82f6',
});

watch(
    () => form.value.driver,
    (driver) => {
        if (driver === 'pgsql') {
            form.value.port = 5432;
            form.value.username = 'postgres';
            form.value.database_name = 'postgres';
            form.value.color_tag = '#3b82f6';
        } else if (driver === 'mysql') {
            form.value.port = 3306;
            form.value.username = 'root';
            form.value.database_name = 'mysql';
            form.value.color_tag = '#f59e0b';
        } else if (driver === 'sqlite') {
            form.value.port = 0;
            form.value.username = '';
            form.value.database_name = 'database/demo_ecommerce.sqlite';
            form.value.color_tag = '#10b981';
        } else if (driver === 'sqlcipher') {
            form.value.port = 0;
            form.value.username = '';
            form.value.database_name = 'database/encrypted_vault.db';
            form.value.color_tag = '#8b5cf6';
        }
    }
);

async function testConnection() {
    isTesting.value = true;
    testResult.value = null;

    try {
        const response = await fetch('/api/v1/connections/test', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(form.value),
        });

        const data = await response.json();
        testResult.value = {
            success: response.ok && data.success,
            message: data.message,
            latency_ms: data.latency_ms,
        };
    } catch (e: any) {
        testResult.value = {
            success: false,
            message: `Error de red: ${e.message}`,
        };
    } finally {
        isTesting.value = false;
    }
}

async function saveConnection() {
    if (!form.value.name || !form.value.database_name) {
        emit('notify', 'Por favor ingresa un nombre y base de datos.');
        return;
    }

    isSaving.value = true;
    try {
        const response = await fetch('/api/v1/connections', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(form.value),
        });

        const data = await response.json();
        if (response.ok && data.success) {
            emit('notify', '¡Conexión guardada con éxito! 🎉');
            emit('created', data.data);
            emit('close');
        } else {
            emit('notify', `Error: ${data.message}`);
        }
    } catch (e: any) {
        emit('notify', `Error: ${e.message}`);
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-sm font-mono text-xs select-none">
        <div class="bg-slate-900 border border-slate-700 rounded-lg w-full max-w-lg shadow-2xl flex flex-col overflow-hidden text-slate-200">
            <!-- Header -->
            <div class="h-12 bg-slate-950 border-b border-slate-800 px-4 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2 font-bold text-slate-100">
                    <Database class="w-4 h-4 text-blue-400" />
                    <span>Nueva Conexión de Base de Datos</span>
                </div>
                <button @click="$emit('close')" class="text-slate-400 hover:text-white">
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Form Body -->
            <div class="p-4 space-y-3 overflow-y-auto max-h-[75vh]">
                <!-- Driver & Name -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-slate-400 block mb-1">Motor (Driver)</label>
                        <select v-model="form.driver" class="w-full bg-slate-950 border border-slate-800 p-2 rounded text-blue-400 font-semibold outline-none focus:border-blue-500">
                            <option value="pgsql">🐘 PostgreSQL</option>
                            <option value="mysql">🐬 MySQL / MariaDB</option>
                            <option value="sqlite">🪶 SQLite</option>
                            <option value="sqlcipher">🔒 SQLCipher (Cifrada)</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-slate-400 block mb-1">Nombre Visual</label>
                        <input v-model="form.name" placeholder="Mi Base de Producción" class="w-full bg-slate-950 border border-slate-800 p-2 rounded text-slate-100 outline-none focus:border-blue-500" />
                    </div>
                </div>

                <!-- Host & Port (for pgsql / mysql) -->
                <div v-if="form.driver !== 'sqlite' && form.driver !== 'sqlcipher'" class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label class="text-slate-400 block mb-1">Host / Servidor</label>
                        <input v-model="form.host" placeholder="127.0.0.1" class="w-full bg-slate-950 border border-slate-800 p-2 rounded text-slate-100 outline-none focus:border-blue-500" />
                    </div>
                    <div>
                        <label class="text-slate-400 block mb-1">Puerto</label>
                        <input type="number" v-model.number="form.port" class="w-full bg-slate-950 border border-slate-800 p-2 rounded text-slate-100 outline-none" />
                    </div>
                </div>

                <!-- Database Name / File -->
                <div>
                    <label class="text-slate-400 block mb-1">
                        {{ form.driver === 'sqlite' || form.driver === 'sqlcipher' ? 'Ruta de Archivo SQLite (.sqlite o :memory:)' : 'Nombre de Base de Datos' }}
                    </label>
                    <input v-model="form.database_name" placeholder="nombre_db" class="w-full bg-slate-950 border border-slate-800 p-2 rounded text-slate-100 outline-none focus:border-blue-500" />
                </div>

                <!-- Username & Password -->
                <div v-if="form.driver !== 'sqlite'" class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-slate-400 block mb-1">Usuario</label>
                        <input v-model="form.username" placeholder="usuario" class="w-full bg-slate-950 border border-slate-800 p-2 rounded text-slate-100 outline-none" />
                    </div>
                    <div>
                        <label class="text-slate-400 block mb-1">{{ form.driver === 'sqlcipher' ? 'Clave de Cifrado (Passphrase)' : 'Contraseña' }}</label>
                        <input type="password" v-model="form.password" placeholder="••••••••" class="w-full bg-slate-950 border border-slate-800 p-2 rounded text-slate-100 outline-none" />
                    </div>
                </div>

                <!-- Environment & Read-Only -->
                <div class="grid grid-cols-2 gap-3 pt-1">
                    <div>
                        <label class="text-slate-400 block mb-1">Entorno</label>
                        <select v-model="form.environment" class="w-full bg-slate-950 border border-slate-800 p-2 rounded text-slate-200 outline-none">
                            <option value="development">🌱 Development</option>
                            <option value="staging">🚧 Staging</option>
                            <option value="production">🔥 Production</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2 pt-6">
                        <input type="checkbox" id="read_only" v-model="form.is_read_only" class="rounded bg-slate-950 border-slate-700 text-purple-600 focus:ring-0 w-4 h-4" />
                        <label for="read_only" class="text-slate-300 font-semibold cursor-pointer">Modo Sólo Lectura 🛡️</label>
                    </div>
                </div>

                <!-- Test Connection Feedback Box -->
                <div v-if="testResult" :class="['p-3 rounded border text-xs flex items-center gap-2', testResult.success ? 'bg-emerald-950/80 border-emerald-800 text-emerald-300' : 'bg-rose-950/80 border-rose-800 text-rose-300']">
                    <CheckCircle v-if="testResult.success" class="w-4 h-4 shrink-0" />
                    <AlertTriangle v-else class="w-4 h-4 shrink-0" />
                    <span>{{ testResult.message }}</span>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="h-14 bg-slate-950 border-t border-slate-800 px-4 flex items-center justify-between shrink-0">
                <button
                    @click="testConnection"
                    :disabled="isTesting"
                    class="flex items-center gap-1.5 bg-slate-800 hover:bg-slate-700 disabled:opacity-50 text-slate-200 px-3 py-1.5 rounded transition font-semibold"
                >
                    <Zap v-if="!isTesting" class="w-3.5 h-3.5 text-amber-400" />
                    <RefreshCw v-else class="w-3.5 h-3.5 animate-spin" />
                    <span>Probar Conexión</span>
                </button>

                <div class="flex items-center gap-2">
                    <button @click="$emit('close')" class="px-3 py-1.5 text-slate-400 hover:text-white">Cancelar</button>
                    <button
                        @click="saveConnection"
                        :disabled="isSaving"
                        class="flex items-center gap-1.5 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white font-semibold px-4 py-1.5 rounded transition shadow-sm"
                    >
                        <Save v-if="!isSaving" class="w-3.5 h-3.5" />
                        <RefreshCw v-else class="w-3.5 h-3.5 animate-spin" />
                        <span>Guardar Conexión</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
