<template>
  <form @submit.prevent="$emit('submit', category)" class="stack">
    <fieldset class="stack" :disabled="submitting">
    <label>
      Название
      <input v-model="category.name" type="text" required />
      <small v-if="fieldErrors.name" class="field-error">{{ fieldErrors.name }}</small>
    </label>
    <label>Родительская категория
      <select v-model="category.parent_id">
        <option value="">Корневая категория</option>
        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.full_name }}</option>
      </select>
      <small v-if="fieldErrors.parent_id" class="field-error">{{ fieldErrors.parent_id }}</small>
    </label>
    <small v-if="fieldErrors._common" class="field-error">{{ fieldErrors._common }}</small>
    <button type="submit" :disabled="submitting">{{ submitting ? 'Сохранение...' : (category.id ? 'Сохранить категорию' : 'Создать категорию') }}</button>
    <button type="button" @click="$emit('reset')" :disabled="submitting">Сбросить</button>
    </fieldset>
  </form>
</template>

<script lang="ts" setup>
import { computed, defineProps } from 'vue';
const props = defineProps<{ category: any; categories: any[]; submitting?: boolean; errors?: Record<string, string> }>();
const fieldErrors = computed(() => props.errors || {});
</script>

