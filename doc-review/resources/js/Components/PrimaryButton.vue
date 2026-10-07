<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center rounded-md border border-transparent bg-uh-teal px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-uh-green focus:bg-uh-green focus:outline-none focus:ring-2 focus:ring-uh-teal focus:ring-offset-2 active:bg-uh-forest"
    >
        <slot />
    </button>

</template>
