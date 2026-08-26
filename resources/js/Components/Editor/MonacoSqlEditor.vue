<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import * as monaco from 'monaco-editor';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        language?: string;
        readOnly?: boolean;
        tables?: string[];
        columns?: Record<string, string[]>;
    }>(),
    {
        language: 'sql',
        readOnly: false,
        tables: () => [],
        columns: () => ({}),
    }
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'execute'): void;
}>();

const editorContainer = ref<HTMLDivElement | null>(null);
let editorInstance: monaco.editor.IStandaloneCodeEditor | null = null;
let completionDisposable: monaco.IDisposable | null = null;

onMounted(() => {
    if (!editorContainer.value) return;

    // Register SQL Autocompletion Provider
    completionDisposable = monaco.languages.registerCompletionItemProvider('sql', {
        provideCompletionItems: (model, position) => {
            const word = model.getWordUntilPosition(position);
            const range = {
                startLineNumber: position.lineNumber,
                endLineNumber: position.lineNumber,
                startColumn: word.startColumn,
                endColumn: word.endColumn,
            };

            const suggestions: monaco.languages.CompletionItem[] = [];

            // SQL Keywords
            const sqlKeywords = [
                'SELECT', 'FROM', 'WHERE', 'JOIN', 'INNER JOIN', 'LEFT JOIN', 'RIGHT JOIN',
                'GROUP BY', 'ORDER BY', 'HAVING', 'LIMIT', 'OFFSET', 'INSERT INTO', 'VALUES',
                'UPDATE', 'SET', 'DELETE FROM', 'CREATE TABLE', 'ALTER TABLE', 'DROP TABLE',
                'COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'DISTINCT', 'AS', 'AND', 'OR', 'NOT',
                'IN', 'EXISTS', 'BETWEEN', 'LIKE', 'ILIKE', 'IS NULL', 'IS NOT NULL',
            ];

            for (const kw of sqlKeywords) {
                suggestions.push({
                    label: kw,
                    kind: monaco.languages.CompletionItemKind.Keyword,
                    insertText: kw,
                    range,
                });
            }

            // Tables Autocompletion
            for (const table of props.tables) {
                suggestions.push({
                    label: table,
                    kind: monaco.languages.CompletionItemKind.Class,
                    insertText: `"${table}"`,
                    detail: 'Tabla',
                    range,
                });
            }

            // Columns Autocompletion
            for (const [table, cols] of Object.entries(props.columns)) {
                for (const col of cols) {
                    suggestions.push({
                        label: `${table}.${col}`,
                        kind: monaco.languages.CompletionItemKind.Field,
                        insertText: `"${col}"`,
                        detail: `Columna (${table})`,
                        range,
                    });
                }
            }

            return { suggestions };
        },
    });

    editorInstance = monaco.editor.create(editorContainer.value, {
        value: props.modelValue,
        language: props.language,
        theme: 'vs-dark',
        readOnly: props.readOnly,
        automaticLayout: true,
        fontFamily: "'JetBrains Mono', 'Fira Code', Menlo, Monaco, monospace",
        fontSize: 13,
        lineHeight: 20,
        minimap: { enabled: false },
        scrollBeyondLastLine: false,
        padding: { top: 8, bottom: 8 },
        renderLineHighlight: 'all',
        tabSize: 4,
    });

    editorInstance.onDidChangeModelContent(() => {
        if (editorInstance) {
            emit('update:modelValue', editorInstance.getValue());
        }
    });

    // Atajo F5 y Ctrl+Enter para Ejecutar
    editorInstance.addCommand(monaco.KeyCode.F5, () => {
        emit('execute');
    });

    editorInstance.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.Enter, () => {
        emit('execute');
    });
});

watch(
    () => props.modelValue,
    (newVal) => {
        if (editorInstance && editorInstance.getValue() !== newVal) {
            editorInstance.setValue(newVal);
        }
    }
);

onBeforeUnmount(() => {
    if (completionDisposable) {
        completionDisposable.dispose();
    }
    if (editorInstance) {
        editorInstance.dispose();
    }
});

defineExpose({
    format: () => {
        editorInstance?.getAction('editor.action.formatDocument')?.run();
    },
    focus: () => {
        editorInstance?.focus();
    },
});
</script>

<template>
    <div ref="editorContainer" class="w-full h-full min-h-[180px] bg-slate-900 border-b border-slate-800" />
</template>
