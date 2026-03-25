<template>
  <section class="panel">
    <div class="panel-header">
      <h2>{{ product.id ? 'Редактирование товара' : 'Новый товар' }}</h2>
      <button @click="$emit('reset')">Сбросить</button>
    </div>
    <form @submit.prevent="$emit('submit', product)" class="stack">
      <fieldset class="stack" :disabled="submitting">
        <label>
          Название
          <input v-model="product.name" type="text" required />
          <small v-if="fieldErrors.name" class="field-error">{{ fieldErrors.name }}</small>
        </label>
        <label>
          Описание
          <textarea v-model="product.content" rows="4" required></textarea>
          <small v-if="fieldErrors.content" class="field-error">{{ fieldErrors.content }}</small>
        </label>
        <label>
          Цена
          <input v-model.number="product.price" type="number" min="0" step="0.01" required />
          <small v-if="fieldErrors.price" class="field-error">{{ fieldErrors.price }}</small>
        </label>
      <label>Категория
        <select v-model="product.category_id" required>
          <option value="">Выберите категорию</option>
          <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.full_name }}</option>
        </select>
        <small v-if="fieldErrors.category_id" class="field-error">{{ fieldErrors.category_id }}</small>
      </label>
      <label class="inline"><input v-model="product.in_stock" type="checkbox" /> В наличии</label>
      <small v-if="fieldErrors.in_stock" class="field-error">{{ fieldErrors.in_stock }}</small>
      <small v-if="fieldErrors._common" class="field-error">{{ fieldErrors._common }}</small>
      <button type="submit" :disabled="submitting">{{ submitting ? 'Сохранение...' : (product.id ? 'Сохранить' : 'Создать') }}</button>
      </fieldset>
    </form>
  </section>
</template>

<script lang="ts" setup>
import { computed, defineProps } from 'vue';
const props = defineProps<{ product: any; categories: any[]; submitting?: boolean; errors?: Record<string, string> }>();
const fieldErrors = computed(() => props.errors || {});
</script>

