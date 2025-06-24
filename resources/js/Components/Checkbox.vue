<script setup>
import { computed } from 'vue';

const emit = defineEmits(['update:checked']);

const props = defineProps({
    checked: {
        type: [Array, Boolean],
        default: false,
    },
    value: {
        type: String,
        default: null,
    },
    label: {
        type: String,
        default: 'Remember me',
    },
});

const proxyChecked = computed({
    get() {
        return props.checked;
    },

    set(val) {
        emit('update:checked', val);
    },
});
</script>


<template>

    <div class="form-check">
        <input v-model="proxyChecked" type="checkbox" :value="value" id="customCheck1"
            class="form-check-input rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
        <label class="form-check-label" for="customCheck1">{{ label }}</label>
    </div>
</template>
