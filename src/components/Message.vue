<template>
  <div v-if="message.text" :class="['message', `message--${message.type}`]">
    {{ message.text }}
  </div>
</template>

<script lang="ts" setup>
import { defineProps } from 'vue';

defineProps<{ message: { text: string; type: string } }>();
</script>
